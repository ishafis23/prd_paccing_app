<?php

namespace App\Filament\Resources;

use App\Enums\AdvanceStatus;
use App\Enums\RoleName;
use App\Filament\Resources\TechnicianAdvanceResource\Pages;
use App\Models\TechnicianAdvance;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * SDM/HRD → "Kasbon" (dev-plan/28): admin mencatat kasbon teknisi; nominalnya
 * dipotong otomatis saat gaji dihitung (PayrollService), sisa terbawa ke
 * periode berikutnya sampai lunas.
 */
class TechnicianAdvanceResource extends BaseResource
{
    protected static ?string $model = TechnicianAdvance::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'SDM/HRD';

    protected static ?string $navigationLabel = 'Kasbon';

    protected static ?string $modelLabel = 'Kasbon';

    protected static ?string $pluralModelLabel = 'Kasbon';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole([
            RoleName::Owner->value,
            RoleName::Hr->value,
            RoleName::Admin->value,
        ]) ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->label('Teknisi')
                ->options(fn () => User::role(RoleName::Teknisi->value)->pluck('name', 'id'))
                ->searchable()
                ->required(),
            Forms\Components\DatePicker::make('tanggal')
                ->label('Tanggal Kasbon')
                ->default(now())
                ->required(),
            Forms\Components\TextInput::make('nominal')
                ->label('Nominal Kasbon')
                ->numeric()
                ->prefix('Rp')
                ->minValue(1)
                ->required(),
            Forms\Components\TextInput::make('cicilan_per_periode')
                ->label('Cicilan per Periode Gaji')
                ->numeric()
                ->prefix('Rp')
                ->minValue(1)
                ->helperText('Kosongkan bila dipotong lunas sekaligus di gaji berikutnya.'),
            Forms\Components\Textarea::make('keterangan')
                ->rows(2)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Teknisi')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('tanggal')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('nominal')->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('cicilan_per_periode')->label('Cicilan/Periode')->money('IDR')->placeholder('Lunas sekaligus'),
                Tables\Columns\TextColumn::make('terpotong')
                    ->label('Terpotong')
                    ->state(fn (TechnicianAdvance $r): float => $r->terbayar())
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('sisa')
                    ->label('Sisa')
                    ->state(fn (TechnicianAdvance $r): float => $r->sisa())
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (AdvanceStatus $state): string => $state->label())
                    ->color(fn (AdvanceStatus $state): string => $state === AdvanceStatus::Lunas ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('keterangan')->limit(40)->placeholder('—')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Teknisi')
                    ->relationship('user', 'name')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(AdvanceStatus::cases())->mapWithKeys(fn (AdvanceStatus $s) => [$s->value => $s->label()])->all()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (TechnicianAdvance $r): bool => $r->pelunasan()->doesntExist()),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (TechnicianAdvance $r): bool => $r->pelunasan()->doesntExist()),
            ])
            ->defaultSort('tanggal', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTechnicianAdvances::route('/'),
            'create' => Pages\CreateTechnicianAdvance::route('/create'),
            'edit' => Pages\EditTechnicianAdvance::route('/{record}/edit'),
        ];
    }
}
