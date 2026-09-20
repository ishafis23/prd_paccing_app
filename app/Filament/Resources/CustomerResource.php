<?php

namespace App\Filament\Resources;

use App\Enums\CustomerArea;
use App\Enums\CustomerJenis;
use App\Enums\CustomerStatus;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use App\Services\CustomerPortalService;
use App\Support\EnumOptions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerResource extends BaseResource
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
                Forms\Components\Select::make('jenis')
                    ->options(EnumOptions::for(CustomerJenis::class))
                    ->default(CustomerJenis::Perorangan->value)
                    ->required(),
                Forms\Components\TextInput::make('no_hp')
                    ->label('No. HP/WA')
                    ->required()
                    ->maxLength(20),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                Forms\Components\Textarea::make('alamat')
                    ->label('Alamat Utama')
                    ->helperText('Opsional — utk satu alamat utama saja. Multi-alamat (rumah, usaha, dst, masing-masing dgn peta sendiri) dikelola lewat tab "Alamat" di halaman edit customer.')
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
                Tables\Columns\TextColumn::make('jenis')->badge()->sortable(),
                Tables\Columns\TextColumn::make('no_hp')->label('No. HP/WA')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('area')->badge()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable()->color(fn (CustomerStatus $state): string => match ($state) {
                    CustomerStatus::Aktif => 'success',
                    CustomerStatus::Lead => 'warning',
                    CustomerStatus::Nonaktif => 'gray',
                }),
                Tables\Columns\TextColumn::make('sumber_lead')->label('Sumber Lead')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('akses_portal')
                    ->label('Portal')
                    ->boolean()
                    ->state(fn (Customer $record): bool => $record->bisaLoginPortal())
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d M Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('jenis')->options(EnumOptions::for(CustomerJenis::class)),
                Tables\Filters\SelectFilter::make('area')->options(EnumOptions::for(CustomerArea::class)),
                Tables\Filters\SelectFilter::make('status')->options(EnumOptions::for(CustomerStatus::class)),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('aturPasswordPortal')
                    ->label('Atur Password Portal')
                    ->icon('heroicon-o-key')
                    ->color('gray')
                    ->visible(fn () => auth()->user()->hasAnyRole([RoleName::Admin->value, RoleName::Owner->value]))
                    ->modalHeading('Atur Password Portal Customer')
                    ->modalDescription('Aktifkan/reset akses login Portal Customer (dev-plan/12 §3.6) — customer login pakai email ini + password yang diatur di sini.')
                    ->form([
                        Forms\Components\TextInput::make('password')
                            ->label('Password Baru')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8),
                    ])
                    ->action(function (Customer $record, array $data) {
                        try {
                            app(CustomerPortalService::class)->aturPassword($record, $data['password'], auth()->user());
                            Notification::make()->success()->title('Password Portal diatur')->send();
                        } catch (BusinessRuleException $e) {
                            Notification::make()->danger()->title('Gagal mengatur password')->body($e->getMessage())->send();
                        }
                    }),

                Tables\Actions\Action::make('cabutAksesPortal')
                    ->label('Cabut Akses Portal')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->visible(fn (Customer $record) => $record->bisaLoginPortal()
                        && auth()->user()->hasAnyRole([RoleName::Admin->value, RoleName::Owner->value]))
                    ->requiresConfirmation()
                    ->modalDescription('Customer tidak akan bisa login ke Portal lagi sampai diaktifkan ulang. Data customer tidak dihapus.')
                    ->action(function (Customer $record) {
                        app(CustomerPortalService::class)->cabutAkses($record, auth()->user());
                        Notification::make()->success()->title('Akses Portal dicabut')->send();
                    }),
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
            CustomerResource\RelationManagers\AddressesRelationManager::class,
            CustomerResource\RelationManagers\AcUnitsRelationManager::class,
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
