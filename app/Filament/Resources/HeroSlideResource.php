<?php

namespace App\Filament\Resources;

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\HeroSlideResource\Pages;
use App\Models\HeroSlide;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;

/**
 * Kelola slide hero landing (B38c) — grup Website. Owner/Admin.
 */
class HeroSlideResource extends BaseResource
{
    protected static ?string $model = HeroSlide::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Hero Slider';

    protected static ?string $modelLabel = 'Slide Hero';

    protected static ?string $pluralModelLabel = 'Hero Slider';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole([RoleName::Owner->value, RoleName::Admin->value]) ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return static::canViewAny();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\FileUpload::make('gambar')
                ->label('Gambar Landscape (disarankan 1600×600+)')
                ->image()
                ->disk('public')
                ->directory('hero')
                ->imageEditor()
                ->maxSize(5120)
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
                ->required()
                ->helperText('PNG/JPG/WebP maks. 5 MB. Tampil penuh selebar layar — pakai gambar landscape.'),
            Forms\Components\TextInput::make('judul')
                ->label('Judul')
                ->maxLength(255),
            Forms\Components\Textarea::make('subjudul')
                ->label('Subjudul')
                ->rows(2),
            Forms\Components\TextInput::make('tombol_teks')
                ->label('Teks Tombol (opsional)')
                ->placeholder('mis. Chat WhatsApp')
                ->maxLength(100),
            Forms\Components\TextInput::make('tombol_url')
                ->label('Tautan Tombol (opsional)')
                ->placeholder('mis. https://wa.me/62811...')
                ->url()
                ->maxLength(500),
            Forms\Components\TextInput::make('urutan')
                ->label('Urutan')
                ->numeric()
                ->default(0),
            Forms\Components\Toggle::make('aktif')
                ->label('Aktif')
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('urutan')
            ->reorderable('urutan')
            ->columns([
                Tables\Columns\ImageColumn::make('gambar')
                    ->label('Gambar')
                    ->disk('public')
                    ->size(80)
                    ->square()
                    ->visible(fn (?HeroSlide $record): bool => filled($record?->gambar)),
                Tables\Columns\TextColumn::make('judul')
                    ->label('Judul')
                    ->placeholder('—')
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\TextColumn::make('subjudul')
                    ->label('Subjudul')
                    ->placeholder('—')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('urutan')
                    ->label('Urutan')
                    ->sortable(),
                Tables\Columns\IconColumn::make('aktif')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('aktif')->label('Status'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->action(function (HeroSlide $record): void {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($record->gambar);
                        $record->delete();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function ($records): void {
                            foreach ($records as $record) {
                                \Illuminate\Support\Facades\Storage::disk('public')->delete($record->gambar);
                                $record->delete();
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHeroSlides::route('/'),
            'create' => Pages\CreateHeroSlide::route('/create'),
            'edit' => Pages\EditHeroSlide::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('urutan');
    }
}
