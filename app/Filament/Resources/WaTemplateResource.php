<?php

namespace App\Filament\Resources;

use App\Enums\RoleName;
use App\Filament\Resources\WaTemplateResource\Pages;
use App\Models\WaTemplate;
use App\Services\WaTemplateService;
use Filament\Forms;
use Filament\Forms\Form;
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
                Tables\Actions\EditAction::make(),
            ]);
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
