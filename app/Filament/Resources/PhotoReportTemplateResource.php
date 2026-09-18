<?php

namespace App\Filament\Resources;

use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Filament\Resources\PhotoReportTemplateResource\Pages;
use App\Models\PhotoReportTemplate;
use App\Services\PhotoReportTemplateService;
use App\Support\EnumOptions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Menu Customer & Order → "Template Foto Laporan" (dev-plan/17, B61):
 * daftar slot foto laporan per kategori — admin bisa aktif/nonaktifkan &
 * tandai wajib per item langsung di tabel, serta tambah item baru tanpa
 * deploy. Menggantikan hardcode `App\Support\FotoLaporanSlot`.
 */
class PhotoReportTemplateResource extends Resource
{
    protected static ?string $model = PhotoReportTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-camera';

    protected static ?string $navigationGroup = 'Customer & Order';

    protected static ?string $navigationLabel = 'Template Foto Laporan';

    protected static ?string $modelLabel = 'Template Foto';

    protected static ?string $pluralModelLabel = 'Template Foto Laporan';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole([
            RoleName::Owner->value,
            RoleName::Admin->value,
            RoleName::Finance->value,
        ]) ?? false;
    }

    public static function canCreate(): bool
    {
        return false; // lewat aksi "Tambah Item", bukan form create standar.
    }

    public static function canEdit($record): bool
    {
        return static::bolehKelola();
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function bolehKelola(): bool
    {
        return auth()->user()?->hasAnyRole([RoleName::Owner->value, RoleName::Admin->value]) ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('label')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('urutan')
                ->numeric()
                ->default(0)
                ->required(),
            Forms\Components\Toggle::make('wajib')
                ->helperText('dev-plan/17 B63: submit laporan ditolak kalau ini wajib & aktif, tapi belum ada fotonya.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('kategori')
                    ->badge(),
                Tables\Columns\TextColumn::make('urutan')
                    ->sortable(),
                Tables\Columns\TextColumn::make('label'),
                Tables\Columns\TextColumn::make('kode_slot')
                    ->label('Kode Slot')
                    ->fontFamily('mono')
                    ->color('gray'),
                Tables\Columns\ToggleColumn::make('aktif')
                    ->disabled(fn (): bool => ! static::bolehKelola())
                    ->afterStateUpdated(fn (PhotoReportTemplate $record) => app(PhotoReportTemplateService::class)->lupakanCache($record->kategori->value)),
                Tables\Columns\ToggleColumn::make('wajib')
                    ->disabled(fn (): bool => ! static::bolehKelola())
                    ->afterStateUpdated(fn (PhotoReportTemplate $record) => app(PhotoReportTemplateService::class)->lupakanCache($record->kategori->value)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kategori')
                    ->options(EnumOptions::for(ServiceType::class)),
            ])
            ->headerActions([
                Tables\Actions\Action::make('tambahItem')
                    ->label('Tambah Item')
                    ->icon('heroicon-o-plus')
                    ->visible(fn (): bool => static::bolehKelola())
                    ->form([
                        Forms\Components\Select::make('kategori')
                            ->options(EnumOptions::for(ServiceType::class))
                            ->required(),
                        Forms\Components\TextInput::make('label')
                            ->label('Label (nama yang dilihat teknisi)')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('urutan')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Forms\Components\Toggle::make('wajib')
                            ->helperText('dev-plan/17 B63: submit laporan ditolak kalau belum ada fotonya.'),
                    ])
                    ->action(function (array $data): void {
                        app(PhotoReportTemplateService::class)->tambah($data, auth()->user());

                        Notification::make()->title('Item foto ditambahkan')->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => static::bolehKelola())
                    ->using(function (PhotoReportTemplate $record, array $data): PhotoReportTemplate {
                        return app(PhotoReportTemplateService::class)->perbarui($record, $data, auth()->user());
                    }),
            ])
            ->modifyQueryUsing(fn ($query) => $query->orderBy('kategori')->orderBy('urutan'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPhotoReportTemplates::route('/'),
        ];
    }
}
