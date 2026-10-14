<?php

namespace App\Filament\Resources;

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\WaLogResource\Pages;
use App\Models\WaLog;
use App\Services\WhatsappService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * dev-plan/23: Riwayat Kirim WA — SEMUA percobaan (sukses & gagal) tersimpan.
 * Baris gagal bisa DIKIRIM ULANG memakai payload tersimpan (tanpa render PDF
 * / susun pesan lagi). Lihat WhatsappService::kirimUlang().
 */
class WaLogResource extends BaseResource
{
    protected static ?string $model = WaLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static ?string $navigationLabel = 'Riwayat Kirim WA';

    protected static ?string $modelLabel = 'Riwayat Kirim WA';

    protected static ?string $pluralModelLabel = 'Riwayat Kirim WA';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->hasAnyRole([RoleName::Owner->value, RoleName::Admin->value]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Pengirim')
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('device.device_name')
                    ->label('Device')
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('target')
                    ->label('Tujuan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('jenis_kirim')
                    ->label('Jenis')
                    ->badge()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === WaLog::STATUS_SUKSES ? 'Sukses' : 'Gagal')
                    ->color(fn (string $state): string => $state === WaLog::STATUS_SUKSES ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('percobaan')
                    ->label('Percobaan')
                    ->badge()
                    ->color(fn (int $state): string => $state > 1 ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(40)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->placeholder('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        WaLog::STATUS_SUKSES => 'Sukses',
                        WaLog::STATUS_GAGAL => 'Gagal',
                    ]),
                Tables\Filters\SelectFilter::make('jenis_kirim')
                    ->label('Jenis')
                    ->options([
                        'teks' => 'Teks',
                        'grup' => 'Grup',
                        'tes' => 'Tes',
                        'invoice' => 'Invoice',
                        'laporan' => 'Laporan',
                        'resi' => 'Resi',
                    ]),
                Tables\Filters\Filter::make('gagal')
                    ->label('Hanya gagal')
                    ->query(fn ($query) => $query->where('status', WaLog::STATUS_GAGAL)),
            ])
            ->actions([
                Tables\Actions\Action::make('lihat')
                    ->label('Lihat')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('Detail pesan')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (WaLog $record): HtmlString => new HtmlString(
                        '<div class="space-y-2 text-sm">'
                        .'<div><b>Tujuan:</b> '.e($record->target).'</div>'
                        .'<div class="whitespace-pre-wrap rounded-lg bg-gray-50 p-3 dark:bg-gray-800">'
                        .nl2br(e((string) ($record->pesan ?: '(tidak ada isi pesan tersimpan)'))).'</div>'
                        .($record->error_message ? '<div class="text-danger-600"><b>Error:</b> '.e($record->error_message).'</div>' : '')
                        .'</div>'
                    )),

                Tables\Actions\Action::make('unduhPdf')
                    ->label('Unduh PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (WaLog $record): bool => filled($record->dokumen_path))
                    ->url(fn (WaLog $record): string => app(WhatsappService::class)
                        ->urlPublik($record->dokumen_path, (string) ($record->dokumen_namafile ?: basename($record->dokumen_path))))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('kirimUlang')
                    ->label('Kirim Ulang')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (WaLog $record): bool => $record->bisaDikirimUlang())
                    ->form([
                        Forms\Components\TextInput::make('target')
                            ->label(fn (WaLog $record): string => $record->action === 'send-grup' ? 'ID Grup Tujuan' : 'Nomor Tujuan')
                            ->tel(fn (WaLog $record): bool => $record->action !== 'send-grup')
                            ->required()
                            ->default(fn (WaLog $record): string => (string) $record->target)
                            ->helperText('Boleh diganti — mis. kirim ke nomor/grup lain.'),
                    ])
                    ->action(function (WaLog $record, array $data): void {
                        try {
                            $baru = app(WhatsappService::class)->kirimUlang($record, $data['target'] ?? null);
                            Notification::make()
                                ->title($baru->sukses() ? 'Terkirim ulang' : 'Kirim ulang gagal')
                                ->body($baru->sukses() ? 'Status tercatat di riwayat.' : (string) $baru->error_message)
                                ->status($baru->sukses() ? 'success' : 'danger')
                                ->send();
                        } catch (BusinessRuleException $e) {
                            Notification::make()->title('Tidak bisa kirim ulang')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWaLogs::route('/'),
        ];
    }
}
