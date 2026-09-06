<?php

namespace App\Filament\Resources;

use App\Enums\ReminderStatus;
use App\Enums\RoleName;
use App\Enums\OrderStatus;
use App\Filament\Resources\ServiceReminderResource\Pages;
use App\Models\Order;
use App\Models\ServiceReminder;
use App\Services\PaymentService;
use App\Support\EnumOptions;
use Carbon\CarbonImmutable;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceReminderResource extends Resource
{
    protected static ?string $model = ServiceReminder::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'Customer & Order';

    protected static ?string $navigationLabel = 'Notice Servis Berikutnya';

    public static function getNavigationBadge(): ?string
    {
        return (string) ServiceReminder::jatuhTempo()->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Buat Notice Servis Berikutnya')
                ->description('Notice dibuat otomatis saat order lunas; gunakan form ini untuk membuat manual (koreksi/susulan). Satu order hanya boleh punya satu notice.')
                ->schema([
                    Forms\Components\Select::make('customer_id')
                        ->label('Customer')
                        ->relationship('customer', 'nama')
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Set $set): mixed => $set('order_id', null)),
                    Forms\Components\Select::make('order_id')
                        ->label('Order Acuan (servis terakhir)')
                        ->options(function (Get $get): array {
                            $customerId = $get('customer_id');

                            if (blank($customerId)) {
                                return [];
                            }

                            return Order::query()
                                ->with('serviceCatalog')
                                ->where('customer_id', $customerId)
                                ->where('status', '!=', OrderStatus::Batal->value)
                                ->latest('id')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (Order $order): array => [
                                    $order->id => sprintf(
                                        '#%d · %s · %s',
                                        $order->id,
                                        $order->serviceCatalog?->jenis_layanan?->value ?? 'tanpa layanan',
                                        $order->tanggal_jadwal?->format('d/m/Y') ?? '-'
                                    ),
                                ])
                                ->all();
                        })
                        ->disabled(fn (Get $get): bool => blank($get('customer_id')))
                        ->helperText('Pilih dulu customer di atas.')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            if (! $state) {
                                return;
                            }

                            $interval = Order::with('serviceCatalog')->find($state)?->serviceCatalog?->interval_bulan;

                            $set('interval_bulan', $interval);

                            if ($interval) {
                                $set('tanggal_servis_berikutnya', CarbonImmutable::now()->addMonthsNoOverflow($interval)->toDateString());
                            }
                        }),
                    Forms\Components\TextInput::make('interval_bulan')
                        ->label('Interval (bulan)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(24)
                        ->required()
                        ->helperText('Otomatis dari layanan order; bisa diubah manual.'),
                    Forms\Components\DatePicker::make('tanggal_servis_berikutnya')
                        ->label('Tanggal Servis Berikutnya')
                        ->required()
                        ->minDate(now()->toDateString())
                        ->default(now()->toDateString()),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal_servis_berikutnya')
            ->columns([
                Tables\Columns\TextColumn::make('customer.nama')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('order.serviceCatalog.jenis_layanan')->label('Layanan terakhir'),
                Tables\Columns\TextColumn::make('tanggal_servis_berikutnya')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('status_notice')->badge()->color(fn (ReminderStatus $state) => match ($state) {
                    ReminderStatus::SudahDihubungi, ReminderStatus::Selesai => 'success',
                    ReminderStatus::SiapDihubungi => 'warning',
                    ReminderStatus::BelumJatuhTempo => 'gray',
                }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status_notice')->options(EnumOptions::for(ReminderStatus::class)),
                Tables\Filters\Filter::make('jatuh_tempo')
                    ->label('Jatuh Tempo (H-7)')
                    ->query(fn ($query) => $query->jatuhTempo()),
            ])
            ->actions([
                Tables\Actions\Action::make('tandaiDihubungi')
                    ->label('Tandai Dihubungi')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (ServiceReminder $record) => auth()->user()->hasAnyRole([RoleName::Admin->value, RoleName::Owner->value])
                        && $record->status_notice !== ReminderStatus::SudahDihubungi)
                    ->action(function (ServiceReminder $record) {
                        app(PaymentService::class)->tandaiSudahDihubungi($record, auth()->user());
                        Notification::make()->success()->title('Ditandai sudah dihubungi')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceReminders::route('/'),
            'create' => Pages\CreateServiceReminder::route('/create'),
        ];
    }
}
