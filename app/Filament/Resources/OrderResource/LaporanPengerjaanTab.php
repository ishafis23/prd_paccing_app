<?php

namespace App\Filament\Resources\OrderResource;

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderUnitReport;
use App\Services\UnitReportService;
use App\Support\FotoLaporanSlot;
use Filament\Forms;
use Filament\Infolists\Components\Actions\Action as InfolistAction;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Tab "Laporan Pengerjaan" di halaman lihat Order (Fase 4): keterangan tiap
 * unit + foto, dengan aksi edit/lengkapi keterangan (menambal yang teknisi
 * lupa) dan upload foto susulan. Owner/Admin/Finance saja.
 */
class LaporanPengerjaanTab
{
    public static function boleh(): bool
    {
        return auth()->user()?->hasAnyRole([
            RoleName::Owner->value,
            RoleName::Admin->value,
            RoleName::Finance->value,
        ]) ?? false;
    }

    public static function tab(): Tabs\Tab
    {
        return Tabs\Tab::make('Laporan Pengerjaan')
            ->visible(fn (): bool => static::boleh())
            ->schema([
                Section::make('Keterangan per Unit')
                    ->description('Keterangan ringkas tiap unit yang dikerjakan teknisi, beserta fotonya.')
                    ->headerActions([
                        static::aksiSiapkan(),
                        static::aksiEdit(),
                        static::aksiFotoSusulan(),
                    ])
                    ->schema([
                        \Filament\Infolists\Components\View::make('order.laporan-unit')
                            ->state(fn (Order $record): Order => $record),
                    ]),
            ]);
    }

    private static function aksiSiapkan(): InfolistAction
    {
        return InfolistAction::make('siapkanDataUnit')
            ->label('Siapkan Data Unit')
            ->icon('heroicon-o-plus-circle')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Membuat baris keterangan untuk setiap unit order ini. Setelah dibuat, unit yang menurut template butuh keterangan wajib diisi teknisi.')
            ->visible(fn (Order $record): bool => static::boleh()
                && $record->status !== OrderStatus::Batal
                && ! app(UnitReportService::class)->punyaDataUnit($record))
            ->action(function (Order $record): void {
                app(UnitReportService::class)->siapkan($record);
                Notification::make()->success()->title('Data unit disiapkan')->send();
            });
    }

    private static function aksiEdit(): InfolistAction
    {
        return InfolistAction::make('editKeteranganUnit')
            ->label('Edit Keterangan Unit')
            ->icon('heroicon-o-pencil-square')
            ->color('primary')
            ->modalWidth('4xl')
            ->visible(fn (Order $record): bool => static::boleh()
                && app(UnitReportService::class)->punyaDataUnit($record))
            ->fillForm(function (Order $record): array {
                return ['units' => app(UnitReportService::class)->unitAktif($record)
                    ->map(fn (OrderUnitReport $u): array => [
                        'id' => $u->id,
                        'label' => "Unit {$u->unit_no} — ".($u->jenis_pekerjaan ?: $u->orderItem->nama_layanan),
                        'lokasi_label' => $u->lokasi_label,
                        'posisi' => $u->posisi,
                        'jenis_pekerjaan' => $u->jenis_pekerjaan,
                        'bagian' => $u->bagian ?: 'indoor',
                        'suhu' => $u->suhu,
                        'rpm' => $u->rpm === null ? null : (int) $u->rpm,
                        'kondisi' => $u->kondisi,
                        'catatan_kondisi' => $u->catatan_kondisi,
                    ])->values()->all()];
            })
            ->form([
                Forms\Components\Repeater::make('units')
                    ->label('')
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                    ->columns(2)
                    ->schema([
                        Forms\Components\Hidden::make('id'),
                        Forms\Components\Hidden::make('label'),
                        Forms\Components\TextInput::make('lokasi_label')->label('Lokasi (CK mana)')->maxLength(255),
                        Forms\Components\TextInput::make('posisi')->maxLength(255),
                        Forms\Components\TextInput::make('jenis_pekerjaan')->label('Pekerjaan')->maxLength(255),
                        Forms\Components\Select::make('bagian')->options(OrderUnitReport::BAGIAN)->required(),
                        Forms\Components\TextInput::make('suhu')->label('Suhu (°C)')->numeric()
                            ->helperText('Hanya dipakai untuk slot indoor lengkap; selain itu dikosongkan otomatis.'),
                        Forms\Components\TextInput::make('rpm')->label('RPM (opsional)')->numeric(),
                        Forms\Components\Select::make('kondisi')->options(OrderUnitReport::KONDISI)
                            ->helperText('Kosongkan bila belum diperiksa — hanya lokasi/posisi/pekerjaan yang disimpan.'),
                        Forms\Components\Textarea::make('catatan_kondisi')->label('Catatan kondisi')
                            ->helperText('Wajib bila kondisi tidak normal.')->rows(2),
                    ]),
            ])
            ->action(function (array $data, Order $record): void {
                $service = app(UnitReportService::class);
                $oleh = auth()->user();
                $gagal = [];

                foreach ($data['units'] ?? [] as $baris) {
                    $unit = OrderUnitReport::query()->where('order_id', $record->id)->find($baris['id'] ?? 0);
                    if ($unit === null) {
                        continue;
                    }

                    try {
                        if (blank($baris['kondisi'] ?? null)) {
                            $service->simpanDeskriptif($unit, $baris, $oleh);
                        } else {
                            $service->simpan($unit, $baris, $oleh);
                        }
                    } catch (ValidationException $e) {
                        $gagal[] = "Unit {$unit->unit_no}: ".collect($e->errors())->flatten()->implode(' ');
                    } catch (BusinessRuleException|AuthorizationException $e) {
                        $gagal[] = "Unit {$unit->unit_no}: ".$e->getMessage();
                    }
                }

                if ($gagal !== []) {
                    Notification::make()->danger()->title('Sebagian keterangan unit tidak tersimpan')->body(implode("\n", $gagal))->persistent()->send();

                    throw new Halt;
                }

                Notification::make()->success()->title('Keterangan unit tersimpan')->send();
            });
    }

    private static function aksiFotoSusulan(): InfolistAction
    {
        return InfolistAction::make('fotoSusulan')
            ->label('Upload Foto Susulan')
            ->icon('heroicon-o-camera')
            ->color('gray')
            ->visible(fn (Order $record): bool => static::boleh() && $record->workReports()->exists())
            ->form([
                Forms\Components\Select::make('order_item_id')
                    ->label('Layanan')
                    ->options(fn (Order $record) => $record->orderItems
                        ->reject(fn (OrderItem $i) => $i->dibatalkan() || $i->penyesuaian)
                        ->mapWithKeys(fn (OrderItem $i) => [$i->id => $i->nama_layanan])
                        ->all())
                    ->required()
                    ->live(),
                Forms\Components\Select::make('slot')
                    ->label('Slot foto')
                    ->options(fn (Forms\Get $get, Order $record): array => ($item = $record->orderItems->firstWhere('id', (int) $get('order_item_id'))) !== null
                        ? FotoLaporanSlot::untuk($item->kategori)
                        : [])
                    ->required(),
                Forms\Components\FileUpload::make('foto')
                    ->label('Foto')
                    ->image()
                    ->disk('public')
                    ->directory('work-reports')
                    ->maxSize(5120)
                    ->required(),
            ])
            ->action(function (array $data, Order $record): void {
                try {
                    app(UnitReportService::class)->tambahFotoSusulan(
                        $record,
                        auth()->user(),
                        (int) $data['order_item_id'],
                        (string) $data['slot'],
                        (string) $data['foto'],
                    );
                    $record->unsetRelation('workReports');
                    Notification::make()->success()->title('Foto susulan ditambahkan')->send();
                } catch (BusinessRuleException|AuthorizationException $e) {
                    Notification::make()->danger()->title('Gagal menambah foto')->body($e->getMessage())->send();

                    throw new Halt;
                }
            });
    }
}
