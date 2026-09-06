<?php

namespace App\Filament\Resources;

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\LayananBerandaResource\Pages;
use App\Models\ServiceCatalog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;

/**
 * Pilih & susun layanan yang tampil di landing (B38d) — grup Website.
 * Harga & interval tetap dari katalog; admin menambah gambar/deskripsi,
 * toggle tampil, dan urutan. Owner/Admin.
 */
class LayananBerandaResource extends Resource
{
    protected static ?string $model = ServiceCatalog::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Layanan Beranda';

    protected static ?string $modelLabel = 'Layanan';

    protected static ?string $pluralModelLabel = 'Layanan Beranda';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole([RoleName::Owner->value, RoleName::Admin->value]) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\FileUpload::make('gambar')
                ->label('Gambar Layanan')
                ->image()
                ->disk('public')
                ->directory('layanan')
                ->imageEditor()
                ->maxSize(3072)
                ->rules([
                    fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                        if ($value instanceof UploadedFile) {
                            try {
                                app(\App\Services\StorageQuotaService::class)->pastikanCukup($value->getSize());
                            } catch (BusinessRuleException $e) {
                                $fail($e->getMessage());
                            }
                        }
                    },
                ])
                ->helperText('Opsional — kalau kosong, kartu memakai ikon layanan. PNG/JPG maks. 3 MB.'),
            Forms\Components\Textarea::make('deskripsi')
                ->label('Deskripsi Singkat')
                ->rows(3)
                ->maxLength(500)
                ->helperText('Kalau kosong, kartu hanya menampilkan nama & harga.'),
            Forms\Components\Toggle::make('tampil_beranda')
                ->label('Tampilkan di Beranda')
                ->default(false),
            Forms\Components\TextInput::make('urutan_beranda')
                ->label('Urutan Tampil')
                ->numeric()
                ->default(0)
                ->helperText('Angka kecil tampil lebih dulu.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->orderBy('tampil_beranda', 'desc')
                ->orderBy('urutan_beranda')
                ->orderBy('id'))
            ->columns([
                Tables\Columns\ImageColumn::make('gambar')
                    ->label('Gambar')
                    ->disk('public')
                    ->size(40)
                    ->square()
                    ->visible(fn (?ServiceCatalog $record): bool => filled($record?->gambar)),
                Tables\Columns\TextColumn::make('label')
                    ->label('Layanan')
                    ->getStateUsing(fn (ServiceCatalog $record): string => $record->labelLayanan())
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where(fn (Builder $q) => $q
                            ->where('pk', 'like', "%{$search}%")
                            ->orWhere('jenis_layanan', 'like', "%{$search}%"))),
                Tables\Columns\TextColumn::make('harga')
                    ->label('Harga')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\IconColumn::make('aktif')
                    ->label('Aktif')
                    ->boolean(),
                Tables\Columns\IconColumn::make('tampil_beranda')
                    ->label('Tampil')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('urutan_beranda')
                    ->label('Urutan')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('tampil_beranda')->label('Tampil di beranda'),
            ])
            ->actions([
                Tables\Actions\Action::make('toggleTampil')
                    ->label(fn (ServiceCatalog $record): string => $record->tampil_beranda ? 'Sembunyikan' : 'Tampilkan')
                    ->icon(fn (ServiceCatalog $record): string => $record->tampil_beranda ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (ServiceCatalog $record): string => $record->tampil_beranda ? 'gray' : 'success')
                    ->action(function (ServiceCatalog $record): void {
                        $record->tampil_beranda = ! $record->tampil_beranda;
                        $record->save();
                        Notification::make()->success()->title($record->tampil_beranda
                            ? 'Layanan tampil di beranda'
                            : 'Layanan disembunyikan dari beranda')->send();
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLayananBerandas::route('/'),
            'edit' => Pages\EditLayananBeranda::route('/{record}/edit'),
        ];
    }
}
