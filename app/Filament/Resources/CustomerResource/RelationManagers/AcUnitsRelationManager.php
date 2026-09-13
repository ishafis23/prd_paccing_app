<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Enums\UnitType;
use App\Exceptions\BusinessRuleException;
use App\Models\CustomerAcUnit;
use App\Models\ServiceCatalog;
use App\Models\Team;
use App\Services\CustomerAcUnitImportService;
use App\Services\OrderDispatchImportService;
use App\Support\EnumOptions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;

/**
 * Daftar Unit AC milik customer — awalnya khusus Company (sekolah/kantor
 * banyak unit), sekarang dibuka utk semua jenis customer termasuk rumahan
 * (dev-plan/12 §3.10 lanjutan) supaya order/laporan bisa menunjuk ke unit
 * spesifik kalau customer punya lebih dari satu AC. Dasar histori
 * pencucian per unit & portal corporate (menyusul, lihat §3.6).
 */
class AcUnitsRelationManager extends RelationManager
{
    protected static string $relationship = 'acUnits';

    protected static ?string $title = 'Unit AC';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('kode_unit')
                    ->label('Kode Unit')
                    ->required()
                    ->maxLength(50),
                Forms\Components\TextInput::make('kode_ruangan')
                    ->label('Ruangan/Lokasi')
                    ->required()
                    ->maxLength(100),
                Forms\Components\Select::make('jenis_unit')
                    ->label('Jenis Unit')
                    ->options(EnumOptions::for(UnitType::class)),
                Forms\Components\TextInput::make('pk')
                    ->label('Kapasitas (PK)')
                    ->placeholder('mis. 1 PK, 1.5 PK')
                    ->maxLength(20),
                Forms\Components\Textarea::make('catatan')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('kode_unit')
            ->columns([
                Tables\Columns\TextColumn::make('kode_unit')->label('Kode Unit')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('kode_ruangan')->label('Ruangan/Lokasi')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('jenis_unit')->label('Jenis Unit')->badge(),
                Tables\Columns\TextColumn::make('pk')->label('PK'),
                Tables\Columns\TextColumn::make('catatan')->limit(40)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                Action::make('unduhTemplateUnitAc')
                    ->label('Unduh Template')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn () => app(CustomerAcUnitImportService::class)->unduhTemplate()),
                Action::make('importUnitAc')
                    ->label('Import Excel')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->modalHeading('Import Unit AC dari Excel')
                    ->modalSubmitActionLabel('Import Sekarang')
                    ->modalWidth('xl')
                    ->form([
                        Forms\Components\FileUpload::make('file')
                            ->label('File Excel / CSV')
                            ->disk('local')
                            ->directory('import-unit-ac')
                            ->maxSize(5120)
                            ->required()
                            ->helperText('.xlsx / .csv — maks. 5 MB & '.CustomerAcUnitImportService::MAX_BARIS.' baris. Data akan ditambahkan ke customer ini. Unduh Template dulu untuk format kolom yang benar.'),
                    ])
                    ->action(function (array $data): void {
                        $service = app(CustomerAcUnitImportService::class);

                        $hasil = $service->import(
                            Storage::disk('local')->path($data['file']),
                            $this->getOwnerRecord(),
                            auth()->user(),
                        );

                        Storage::disk('local')->delete($data['file']);

                        Notification::make()
                            ->title(sprintf(
                                'Import selesai: %d dibuat, %d dilewati, %d gagal',
                                $hasil['berhasil'],
                                $hasil['dilewati'],
                                $hasil['gagal']
                            ))
                            ->{$hasil['gagal'] > 0 ? 'warning' : 'success'}()
                            ->send();

                        if ($hasil['rincian'] !== []) {
                            $rincian = array_slice($hasil['rincian'], 0, 15);
                            $sisa = count($hasil['rincian']) - count($rincian);

                            if ($sisa > 0) {
                                $rincian[] = "... dan {$sisa} baris lainnya.";
                            }

                            Notification::make()
                                ->title('Rincian baris dilewati/gagal')
                                ->body(implode("\n", $rincian))
                                ->warning()
                                ->send();
                        }
                    }),

                Action::make('unduhTemplateOrderMassal')
                    ->label('Unduh Template Order Massal')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn () => app(OrderDispatchImportService::class)->unduhTemplate()),
                Action::make('importOrderMassal')
                    ->label('Buat Order Massal')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->modalHeading('Buat Order Massal dari Unit AC')
                    ->modalDescription('Satu order utk banyak unit sekaligus (dev-plan/12 §3.4) — kode_unit di file harus sudah terdaftar di tab ini. Jenis layanan/harga/jadwal/tim berlaku sama utk semua unit (kecuali harga di-override per baris).')
                    ->modalSubmitActionLabel('Buat Order')
                    ->modalWidth('xl')
                    ->form([
                        Forms\Components\Select::make('service_catalog_id')
                            ->label('Jenis Layanan')
                            ->options(fn () => ServiceCatalog::query()->where('aktif', true)->get()
                                ->mapWithKeys(fn (ServiceCatalog $c) => [$c->id => "{$c->jenis_layanan->value} - {$c->jenis_unit?->value} {$c->pk} (Rp".number_format($c->harga, 0, ',', '.').')']))
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('harga')
                            ->label('Harga per Unit (opsional, override harga katalog)')
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(0),
                        Forms\Components\DatePicker::make('tanggal_jadwal'),
                        Forms\Components\TimePicker::make('jam_jadwal'),
                        Forms\Components\Select::make('team_id')
                            ->label('Assign Tim (opsional)')
                            ->options(fn () => Team::where('aktif', true)->pluck('nama', 'id'))
                            ->searchable(),
                        Forms\Components\Textarea::make('catatan_admin')
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('file')
                            ->label('File Excel / CSV')
                            ->disk('local')
                            ->directory('import-order-massal')
                            ->maxSize(5120)
                            ->required()
                            ->columnSpanFull()
                            ->helperText('.xlsx / .csv — maks. '.OrderDispatchImportService::MAX_BARIS.' baris. Unduh Template dulu untuk format kolom yang benar.'),
                    ])
                    ->action(function (array $data): void {
                        $service = app(OrderDispatchImportService::class);

                        try {
                            $hasil = $service->import(
                                Storage::disk('local')->path($data['file']),
                                $this->getOwnerRecord(),
                                [
                                    'service_catalog_id' => $data['service_catalog_id'],
                                    'harga' => $data['harga'] ?? null,
                                    'tanggal_jadwal' => $data['tanggal_jadwal'] ?? null,
                                    'jam_jadwal' => $data['jam_jadwal'] ?? null,
                                    'team_id' => $data['team_id'] ?? null,
                                    'catatan_admin' => $data['catatan_admin'] ?? null,
                                ],
                                auth()->user(),
                            );
                        } catch (BusinessRuleException|AuthorizationException $e) {
                            Storage::disk('local')->delete($data['file']);
                            Notification::make()->danger()->title('Gagal membuat order massal')->body($e->getMessage())->send();

                            return;
                        }

                        Storage::disk('local')->delete($data['file']);

                        if ($hasil['order'] === null) {
                            Notification::make()
                                ->warning()
                                ->title('Tidak ada baris valid, order tidak dibuat')
                                ->body(implode("\n", array_slice($hasil['rincian'], 0, 15)))
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title("Order #{$hasil['order']->id} dibuat: {$hasil['berhasil']} unit, {$hasil['gagal']} gagal")
                            ->body($hasil['rincian'] !== [] ? implode("\n", array_slice($hasil['rincian'], 0, 15)) : null)
                            ->{$hasil['gagal'] > 0 ? 'warning' : 'success'}()
                            ->send();
                    }),
            ])
            ->actions([
                Action::make('histori')
                    ->label('Histori')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->modalHeading(fn (CustomerAcUnit $record): string => "Histori Pencucian — {$record->kode_unit}")
                    ->modalContent(fn (CustomerAcUnit $record) => view('filament.customer-ac-unit-histori', [
                        'items' => $record->orderItems()
                            ->with(['order.teknisi'])
                            ->latest('id')
                            ->get(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
