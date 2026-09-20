<?php

namespace App\Filament\Resources;

use App\Enums\RoleName;
use App\Filament\Resources\TeamResource\Pages;
use App\Models\Team;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Tim Teknisi permanen (dev-plan/12 §3.13) — mis. 1 tim = 2 teknisi,
 * dipilih sekali lalu dipakai berulang lewat aksi "Assign Tim" di Order
 * (beda dari assign satu-satu per order yg sudah ada).
 */
class TeamResource extends BaseResource
{
    protected static ?string $model = Team::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Manajemen';

    protected static ?string $navigationLabel = 'Tim Teknisi';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nama')
                    ->required()
                    ->maxLength(100),
                Forms\Components\Toggle::make('aktif')
                    ->default(true)
                    ->helperText('Tim nonaktif tidak muncul di pilihan "Assign Tim" pada Order.'),
                Forms\Components\Select::make('teknisi_ids')
                    ->label('Anggota Tim')
                    ->options(fn () => User::role(RoleName::Teknisi->value)->pluck('name', 'id'))
                    ->multiple()
                    ->required()
                    ->searchable()
                    ->helperText('Pilih sesuai urutan prioritas: teknisi PERTAMA menjadi PIC tim.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('picTeknisi.name')->label('PIC')->placeholder('—'),
                Tables\Columns\TextColumn::make('anggota')
                    ->label('Anggota')
                    ->state(fn (Team $record): string => $record->members
                        ->filter(fn (User $u): bool => (int) $u->id !== (int) $record->pic_teknisi_id)
                        ->pluck('name')
                        ->join(', ') ?: '—')
                    ->wrap(),
                Tables\Columns\IconColumn::make('aktif')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('aktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeams::route('/'),
            'create' => Pages\CreateTeam::route('/create'),
            'edit' => Pages\EditTeam::route('/{record}/edit'),
        ];
    }
}
