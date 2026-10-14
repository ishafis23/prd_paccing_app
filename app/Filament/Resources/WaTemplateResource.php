<?php

namespace App\Filament\Resources;

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\WaTemplateResource\Pages;
use App\Models\WaTemplate;
use App\Services\BusinessInfoService;
use App\Services\NotifikasiWaService;
use App\Services\WaTemplateService;
use App\Services\WhatsappService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * dev-plan/27 fase 27-A: "Template Pesan WA" — admin mengelola isi pesan
 * notifikasi (berangkat/pembayaran/dll) tanpa deploy ulang. Placeholder
 * diisi otomatis oleh WaTemplateService saat kirim.
 */
class WaTemplateResource extends BaseResource
{
    protected static ?string $model = WaTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static ?string $navigationLabel = 'Template Pesan WA';

    protected static ?string $modelLabel = 'Template Pesan WA';

    protected static ?string $pluralModelLabel = 'Template Pesan WA';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->hasAnyRole([RoleName::Owner->value, RoleName::Admin->value]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('kode')
                ->label('Kode')
                ->helperText('Huruf kecil & underscore, dipakai sistem — mis. berangkat, pembayaran_lunas.')
                ->required()
                ->maxLength(60)
                ->rule('regex:/^[a-z0-9_]+$/')
                ->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('nama')
                ->label('Nama')
                ->required()
                ->maxLength(150),
            Forms\Components\Textarea::make('isi')
                ->label('Isi Pesan')
                ->required()
                ->rows(12)
                ->columnSpanFull()
                ->helperText('Placeholder tersedia: '.app(WaTemplateService::class)->placeholderList()
                    .'. Placeholder yang tidak terisi otomatis dibuang.'),
            Forms\Components\Toggle::make('aktif')
                ->label('Aktif (dipakai untuk kirim otomatis)')
                ->default(true)
                ->helperText('Bila dimatikan, notifikasi otomatis untuk kode ini dilewati.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('kode')
                    ->label('Kode')
                    ->searchable()
                    ->sortable()
                    ->badge(),
                Tables\Columns\TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable(),
                Tables\Columns\TextColumn::make('isi')
                    ->label('Cuplikan')
                    ->limit(60)
                    ->toggleable(),
                Tables\Columns\ToggleColumn::make('aktif')
                    ->label('Aktif'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diubah')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('kode')
            ->actions([
                Tables\Actions\Action::make('tesKirim')
                    ->label('Tes Kirim')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->modalHeading('Tes Kirim Template')
                    ->modalDescription('Kirim contoh pesan template ini ke nomor yang diisi. Hasil tercatat di Riwayat Kirim WA.')
                    ->form([
                        Forms\Components\TextInput::make('target')
                            ->label('Nomor Tujuan')
                            ->tel()
                            ->required()
                            ->default(fn (): string => (string) preg_replace('/\D/', '', (string) config('wa.test_target')))
                            ->helperText('Terisi dari WA_TEST_TARGET bila ada. Boleh diganti.'),
                        Forms\Components\Textarea::make('pesan')
                            ->label('Isi Pesan (contoh — boleh diedit)')
                            ->rows(11)
                            ->required()
                            ->default(fn (WaTemplate $record): string => app(WaTemplateService::class)->render($record->isi, self::contohData())),
                    ])
                    ->action(function (array $data): void {
                        $user = auth()->user();
                        $device = $user?->waDevice;

                        if ($device === null || ! $device->terhubung()) {
                            $device = app(NotifikasiWaService::class)->deviceNotifikasi();
                        }

                        if ($device === null) {
                            Notification::make()
                                ->title('Belum ada device WA')
                                ->body('Sambungkan device atau tandai "Device Notifikasi Otomatis" dulu.')
                                ->danger()->send();

                            return;
                        }

                        try {
                            $log = app(WhatsappService::class)->kirimTeks(
                                $user, $device, (string) $data['target'], (string) $data['pesan'], null, 'tes',
                            );

                            Notification::make()
                                ->title($log->sukses() ? 'Pesan tes terkirim' : 'Gagal mengirim')
                                ->body($log->sukses()
                                    ? 'Ke '.$log->target.' — tercatat di Riwayat Kirim WA.'
                                    : (string) $log->error_message)
                                ->status($log->sukses() ? 'success' : 'danger')
                                ->send();
                        } catch (BusinessRuleException $e) {
                            Notification::make()->title('Tidak bisa kirim')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    /** Contoh nilai placeholder untuk pratinjau "Tes Kirim". */
    public static function contohData(): array
    {
        return [
            'nama' => 'Budi Santoso',
            'nama_usaha' => app(BusinessInfoService::class)->namaUsaha(),
            'layanan' => 'Cuci AC · 1 unit',
            'alamat' => 'Jl. Contoh No. 1, Makassar',
            'teknisi' => 'Teknisi Andi',
            'jadwal' => now()->addMinutes(30)->format('d M Y H:i'),
            'nomor_invoice' => 'INV-202610-0001',
            'total' => 'Rp 150.000',
            'tautan_invoice' => 'https://contoh.tld/lihat-invoice',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWaTemplates::route('/'),
            'create' => Pages\CreateWaTemplate::route('/create'),
            'edit' => Pages\EditWaTemplate::route('/{record}/edit'),
        ];
    }
}
