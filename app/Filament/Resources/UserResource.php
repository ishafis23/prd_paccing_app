<?php

namespace App\Filament\Resources;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Support\EnumOptions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends BaseResource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Manajemen';

    protected static ?string $navigationLabel = 'Pengguna';

    protected static ?string $modelLabel = 'Pengguna';

    protected static ?string $pluralModelLabel = 'Pengguna';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->label('No. HP/WA')
                    ->tel()
                    ->maxLength(20),
                Forms\Components\Select::make('role')
                    ->label('Role')
                    ->options(EnumOptions::for(RoleName::class))
                    ->required()
                    ->afterStateHydrated(function (Forms\Components\Select $component, ?User $record): void {
                        // Default hanya berlaku saat create; saat edit isi dari role aktif user.
                        if ($record && blank($component->getState())) {
                            $component->state($record->getRoleNames()->first());
                        }
                    })
                    ->helperText('Hanya Owner yang dapat memilih role Owner.'),
                Forms\Components\TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->helperText(fn (string $operation): string => $operation === 'edit'
                        ? 'Kosongkan jika tidak ingin mengubah password.'
                        : 'Minimal 8 karakter.'),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options(EnumOptions::for(UserStatus::class))
                    ->default(UserStatus::Aktif->value)
                    ->required(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (UserStatus $state): string => $state === UserStatus::Aktif ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Role')
                    ->relationship('roles', 'name')
                    ->options(EnumOptions::for(RoleName::class)),
                Tables\Filters\SelectFilter::make('status')
                    ->options(EnumOptions::for(UserStatus::class)),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
