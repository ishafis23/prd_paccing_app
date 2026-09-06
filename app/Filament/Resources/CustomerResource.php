<?php

namespace App\Filament\Resources;

use App\Enums\CustomerArea;
use App\Enums\CustomerStatus;
use App\Enums\LeadSource;
use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use App\Support\EnumOptions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Customer & Order';

    protected static ?string $navigationLabel = 'Data Customer';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nama')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('no_hp')
                    ->label('No. HP/WA')
                    ->required()
                    ->maxLength(20),
                Forms\Components\Textarea::make('alamat')
                    ->columnSpanFull(),
                Forms\Components\Select::make('area')
                    ->options(EnumOptions::for(CustomerArea::class))
                    ->required(),
                Forms\Components\Select::make('sumber_lead')
                    ->label('Sumber Lead')
                    ->options(EnumOptions::for(LeadSource::class))
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options(EnumOptions::for(CustomerStatus::class))
                    ->default(CustomerStatus::Lead->value)
                    ->required(),
                Forms\Components\Textarea::make('catatan')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('no_hp')->label('No. HP/WA')->searchable(),
                Tables\Columns\TextColumn::make('area')->badge()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable()->color(fn (CustomerStatus $state): string => match ($state) {
                    CustomerStatus::Aktif => 'success',
                    CustomerStatus::Lead => 'warning',
                    CustomerStatus::Nonaktif => 'gray',
                }),
                Tables\Columns\TextColumn::make('sumber_lead')->label('Sumber Lead')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d M Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('area')->options(EnumOptions::for(CustomerArea::class)),
                Tables\Filters\SelectFilter::make('status')->options(EnumOptions::for(CustomerStatus::class)),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
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
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
