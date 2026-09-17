<?php

namespace App\Filament\Resources;

use App\Enums\AttendanceCodeStatus;
use App\Enums\RoleName;
use App\Filament\Resources\AttendanceCodeResource\Pages;
use App\Models\AttendanceCode;
use App\Services\AttendanceCodeService;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Menu Absensi & Insentif → "Kode Absensi" (dev-plan/15, B39-B41, B43):
 * riwayat kode/QR absensi kantor. Hanya 1 kode `aktif` berlaku sekaligus —
 * "Buat Kode Baru" otomatis menonaktifkan yang lama.
 */
class AttendanceCodeResource extends Resource
{
    protected static ?string $model = AttendanceCode::class;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $navigationGroup = 'Absensi & Insentif';

    protected static ?string $navigationLabel = 'Kode Absensi';

    protected static ?string $modelLabel = 'Kode Absensi';

    protected static ?string $pluralModelLabel = 'Kode Absensi';

    private const PENGELOLA_ROLES = [RoleName::Owner, RoleName::Admin, RoleName::Hr];

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole([
            RoleName::Owner->value,
            RoleName::Admin->value,
            RoleName::Hr->value,
            RoleName::Finance->value,
        ]) ?? false;
    }

    public static function canCreate(): bool
    {
        return false; // pembuatan lewat aksi "Buat Kode Baru", bukan form create standar.
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function bolehKelola(): bool
    {
        return auth()->user()?->hasAnyRole(array_map(fn (RoleName $r) => $r->value, self::PENGELOLA_ROLES)) ?? false;
    }

    /**
     * URL absen (dituju QR) — dibangun manual (bukan route()) supaya tidak
     * bergantung ke route scan teknisi yang baru ada di fase berikutnya.
     */
    public static function urlAbsen(AttendanceCode $record): string
    {
        return url('/teknisi/absensi/'.$record->kode);
    }

    public static function qrDataUri(AttendanceCode $record): string
    {
        $result = (new Builder(
            writer: new PngWriter,
            data: static::urlAbsen($record),
            size: 320,
            margin: 12,
        ))->build();

        return $result->getDataUri();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('kode')
                    ->label('Kode')
                    ->formatStateUsing(fn (string $state): string => Str::limit($state, 12, '…'))
                    ->copyable()
                    ->copyMessage('Kode disalin')
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (AttendanceCodeStatus $state): string => $state === AttendanceCodeStatus::Aktif ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('lokasi')
                    ->label('Lokasi')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('berlaku_sampai')
                    ->label('Kedaluwarsa')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Tidak ada batas'),
                Tables\Columns\TextColumn::make('dibuatOleh.name')
                    ->label('Dibuat oleh'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('buatBaru')
                    ->label('Buat Kode Baru')
                    ->icon('heroicon-o-plus')
                    ->visible(fn () => static::bolehKelola())
                    ->form([
                        Forms\Components\TextInput::make('lokasi')
                            ->label('Label lokasi (opsional)')
                            ->placeholder('mis. Kantor Pusat')
                            ->maxLength(255),
                        Forms\Components\DateTimePicker::make('berlaku_sampai')
                            ->label('Kedaluwarsa (opsional)')
                            ->helperText('Kosongkan bila tidak ada batas waktu — nonaktifkan manual kapan saja.'),
                    ])
                    ->modalDescription('Kode lama (kalau masih aktif) otomatis dinonaktifkan.')
                    ->action(function (array $data): void {
                        app(AttendanceCodeService::class)->buatBaru(
                            auth()->user(),
                            filled($data['lokasi'] ?? null) ? $data['lokasi'] : null,
                            filled($data['berlaku_sampai'] ?? null) ? Carbon::parse($data['berlaku_sampai']) : null,
                        );

                        Notification::make()
                            ->title('Kode baru dibuat')
                            ->body('Klik "Lihat QR" pada baris paling atas untuk cetak.')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('lihatQr')
                    ->label('Lihat QR')
                    ->icon('heroicon-o-qr-code')
                    ->modalHeading('QR Absensi')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (AttendanceCode $record): HtmlString => new HtmlString(
                        '<div class="flex flex-col items-center gap-3 text-center">'
                        .'<img src="'.e(static::qrDataUri($record)).'" alt="QR Absensi" class="rounded-lg border" />'
                        .'<p class="text-sm text-gray-600 break-all">'.e(static::urlAbsen($record)).'</p>'
                        .'</div>'
                    )),
                Tables\Actions\Action::make('aktifkan')
                    ->label('Aktifkan')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (AttendanceCode $record): bool => static::bolehKelola() && $record->status !== AttendanceCodeStatus::Aktif)
                    ->requiresConfirmation()
                    ->action(function (AttendanceCode $record): void {
                        app(AttendanceCodeService::class)->aktifkan($record, auth()->user());

                        Notification::make()->title('Kode diaktifkan')->success()->send();
                    }),
                Tables\Actions\Action::make('nonaktifkan')
                    ->label('Nonaktifkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (AttendanceCode $record): bool => static::bolehKelola() && $record->status === AttendanceCodeStatus::Aktif)
                    ->requiresConfirmation()
                    ->modalDescription('Teknisi tidak akan bisa absen pakai kode ini lagi sampai diaktifkan ulang.')
                    ->action(function (AttendanceCode $record): void {
                        app(AttendanceCodeService::class)->nonaktifkan($record, auth()->user());

                        Notification::make()->title('Kode dinonaktifkan')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendanceCodes::route('/'),
        ];
    }
}
