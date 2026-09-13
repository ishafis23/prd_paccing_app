<?php

namespace App\Filament\Resources;

use App\Enums\CustomerJenis;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Support\EnumOptions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Customer & Order';

    public static function getEloquentQuery(): Builder
    {
        // Hindari N+1 untuk kolom Tim & seksi infolist (B21).
        return parent::getEloquentQuery()->with(['timTeknisi']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('customer_id')
                    ->label('Customer')
                    ->options(fn () => Customer::query()->pluck('nama', 'id'))
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Forms\Set $set, $state) {
                        $customer = Customer::find($state);
                        $set('alamat_pengerjaan', $customer?->alamat);
                        $set('jenis_pelanggan', $customer?->jenis?->value);
                    }),
                Forms\Components\Select::make('service_catalog_id')
                    ->label('Jenis Layanan')
                    ->options(fn () => ServiceCatalog::query()->where('aktif', true)->get()
                        ->mapWithKeys(fn (ServiceCatalog $c) => [$c->id => "{$c->jenis_layanan->value} - {$c->jenis_unit?->value} {$c->pk} (Rp".number_format($c->harga, 0, ',', '.').')']))
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('jumlah_unit')
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->required(),
                Forms\Components\Select::make('teknisi_id')
                    ->label('Assign Teknisi (opsional)')
                    ->options(fn () => User::role(RoleName::Teknisi->value)->pluck('name', 'id'))
                    ->searchable(),
                Forms\Components\Textarea::make('alamat_pengerjaan')
                    ->columnSpanFull(),
                Forms\Components\Select::make('jenis_pelanggan')
                    ->label('Jenis Pelanggan')
                    ->options([
                        CustomerJenis::Perorangan->value => 'Rumahan',
                        CustomerJenis::Company->value => 'Instansi',
                    ])
                    ->helperText('Menentukan wajib/tidaknya upload bukti pembayaran oleh teknisi. Default mengikuti data customer, bisa diubah di sini.')
                    ->required(),
                Forms\Components\DatePicker::make('tanggal_jadwal'),
                Forms\Components\TimePicker::make('jam_jadwal'),
                Forms\Components\Textarea::make('catatan_admin')
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Order')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('customer.nama')->label('Customer'),
                        TextEntry::make('serviceCatalog.jenis_layanan')->label('Layanan')->badge(),
                        TextEntry::make('teknisi.name')->label('Teknisi')->placeholder('— belum di-assign —'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('jenis_pelanggan')
                            ->label('Jenis Pelanggan')
                            ->badge()
                            ->formatStateUsing(fn (?CustomerJenis $state): ?string => match ($state) {
                                CustomerJenis::Company => 'Instansi',
                                CustomerJenis::Perorangan => 'Rumahan',
                                default => null,
                            })
                            ->placeholder('—'),
                        TextEntry::make('tanggal_jadwal')->date('d M Y'),
                        TextEntry::make('total')->label('Total')->state(fn (Order $record) => 'Rp'.number_format($record->total(), 0, ',', '.')),
                        TextEntry::make('alamat_pengerjaan')->columnSpanFull(),
                        TextEntry::make('alasan_kendala')
                            ->label('Alasan Kendala')
                            ->columnSpanFull()
                            ->color('danger')
                            ->visible(fn (Order $record): bool => filled($record->alasan_kendala)),
                        TextEntry::make('catatan_admin')->columnSpanFull()->placeholder('—'),
                    ]),
                Section::make('Tim Teknisi (B21)')
                    ->schema([
                        TextEntry::make('anggota_tim')
                            ->label('Anggota tim')
                            ->placeholder('— belum di-assign —')
                            ->state(function (Order $record): ?string {
                                $tim = $record->timTeknisi
                                    ->map(fn (User $u): string => (int) $u->id === (int) $record->teknisi_id
                                        ? $u->name.' (PIC)'
                                        : $u->name);

                                if ($tim->isEmpty() && $record->teknisi !== null) {
                                    $tim = collect([$record->teknisi->name.' (PIC)']);
                                }

                                return $tim->isEmpty() ? null : $tim->implode(', ');
                            }),
                    ])
                    ->collapsible(),
                Section::make('Pembayaran')
                    ->schema([
                        TextEntry::make('latestPayment.status')->label('Status')->badge()->placeholder('Belum ada pembayaran'),
                        TextEntry::make('latestPayment.jumlah_dibayar')->label('Dibayar')->money('IDR')->placeholder('—'),
                        TextEntry::make('latestPayment.tanggal_bayar')->label('Tanggal')->date('d M Y')->placeholder('—'),
                        TextEntry::make('metode_dipilih')
                            ->label('Metode dipilih customer')
                            ->badge()
                            ->placeholder('—')
                            ->formatStateUsing(fn (?PaymentMethod $state): ?string => $state ? ucfirst($state->value) : null),
                        ImageEntry::make('bukti_pembayaran')
                            ->label('Bukti Pembayaran')
                            ->disk('public')
                            ->visible(fn (Order $record): bool => filled($record->bukti_pembayaran)),
                    ])
                    ->columns(2),
                Section::make('Laporan Pengerjaan')
                    ->schema([
                        RepeatableEntry::make('workReports')
                            ->label('')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('catatan_pengerjaan')->label('Catatan')->columnSpanFull(),
                                ImageEntry::make('foto_sebelum')
                                    ->label('Foto Sebelum')
                                    ->disk('public')
                                    ->visible(fn ($record): bool => filled($record?->foto_sebelum)),
                                ImageEntry::make('foto_sesudah')
                                    ->label('Foto Sesudah')
                                    ->disk('public')
                                    ->visible(fn ($record): bool => filled($record?->foto_sesudah)),
                                TextEntry::make('waktu_selesai')->label('Selesai')->dateTime('d M Y H:i')->columnSpanFull(),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.nama')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('serviceCatalog.jenis_layanan')->label('Layanan')->badge(),
                Tables\Columns\TextColumn::make('teknisi.name')->label('Teknisi (PIC)')->placeholder('— belum di-assign —'),
                Tables\Columns\TextColumn::make('anggota_tim')
                    ->label('Anggota Tim')
                    ->placeholder('—')
                    ->wrap()
                    ->state(fn (Order $record): ?string => $record->timTeknisi
                        ->filter(fn (User $u): bool => (int) $u->id !== (int) $record->teknisi_id)
                        ->pluck('name')
                        ->join(', ') ?: null),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (OrderStatus $state): string => match ($state) {
                    OrderStatus::Selesai => 'success',
                    OrderStatus::Batal, OrderStatus::Terkendala => 'danger',
                    OrderStatus::ButuhFollowup => 'warning',
                    default => 'info',
                }),
                Tables\Columns\TextColumn::make('tanggal_jadwal')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('total')->label('Total')->state(fn (Order $record) => 'Rp'.number_format($record->total(), 0, ',', '.')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(EnumOptions::for(OrderStatus::class)),
                Tables\Filters\SelectFilter::make('teknisi_id')->label('Teknisi')->options(fn () => User::role(RoleName::Teknisi->value)->pluck('name', 'id')),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('assignTeknisi')
                    ->label('Assign Teknisi')
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn (Order $record) => auth()->user()->hasAnyRole([RoleName::Admin->value, RoleName::Owner->value])
                        && ! in_array($record->status, [OrderStatus::Selesai, OrderStatus::Batal]))
                    ->modalHeading('Assign Teknisi')
                    ->modalDescription('Boleh pilih beberapa teknisi. Urutan pilihan menentukan PIC: teknisi PERTAMA menjadi penanggung jawab (PIC), sisanya anggota tim.')
                    ->form([
                        Forms\Components\Select::make('teknisi_ids')
                            ->label('Teknisi')
                            ->options(fn () => User::role(RoleName::Teknisi->value)->pluck('name', 'id'))
                            ->multiple()
                            ->searchable()
                            ->required()
                            ->helperText('Pilih sesuai urutan prioritas: pertama = PIC.'),
                    ])
                    ->action(function (Order $record, array $data) {
                        $ids = array_values($data['teknisi_ids']);
                        $pic = User::findOrFail($ids[0]);
                        $berhasil = [$pic->name];
                        $dilewati = [];

                        try {
                            app(OrderService::class)->assignTechnician($record, $pic, auth()->user());
                        } catch (BusinessRuleException|AuthorizationException $e) {
                            Notification::make()->danger()->title('Gagal assign PIC')->body($e->getMessage())->send();

                            return;
                        }

                        foreach (array_slice($ids, 1) as $teknisiId) {
                            try {
                                app(OrderService::class)->tambahTeknisi($record, User::findOrFail($teknisiId), auth()->user());
                                $berhasil[] = User::find($teknisiId)?->name ?? "#{$teknisiId}";
                            } catch (BusinessRuleException|AuthorizationException) {
                                $dilewati[] = User::find($teknisiId)?->name ?? "#{$teknisiId}";
                            }
                        }

                        Notification::make()->success()
                            ->title('Teknisi di-assign')
                            ->body(implode(', ', $berhasil).($dilewati !== [] ? ' — sudah anggota: '.implode(', ', $dilewati) : ''))
                            ->send();
                    }),

                Tables\Actions\Action::make('tambahTeknisiTim')
                    ->label('Tambah Anggota Tim')
                    ->icon('heroicon-o-user-group')
                    ->visible(fn (Order $record) => auth()->user()->hasAnyRole([RoleName::Admin->value, RoleName::Owner->value])
                        && ! in_array($record->status, [OrderStatus::Selesai, OrderStatus::Batal])
                        && $record->teknisi_id !== null)
                    ->modalHeading('Tambah Anggota Tim')
                    ->modalDescription('Order harus punya PIC dulu (Assign Teknisi). Boleh pilih beberapa teknisi sekaligus.')
                    ->form([
                        Forms\Components\Select::make('teknisi_ids')
                            ->label('Teknisi')
                            ->options(fn () => User::role(RoleName::Teknisi->value)->pluck('name', 'id'))
                            ->multiple()
                            ->searchable()
                            ->required()
                            ->helperText('Teknisi yang sudah menjadi anggota tim otomatis dilewati.'),
                    ])
                    ->action(function (Order $record, array $data) {
                        $ditambah = [];
                        $dilewati = [];

                        foreach ($data['teknisi_ids'] as $teknisiId) {
                            try {
                                app(OrderService::class)->tambahTeknisi($record, User::findOrFail($teknisiId), auth()->user());
                                $ditambah[] = User::find($teknisiId)?->name ?? "#{$teknisiId}";
                            } catch (BusinessRuleException $e) {
                                $dilewati[] = User::find($teknisiId)?->name ?? "#{$teknisiId}";
                            } catch (AuthorizationException $e) {
                                $dilewati[] = User::find($teknisiId)?->name ?? "#{$teknisiId}";
                            }
                        }

                        if ($ditambah !== []) {
                            Notification::make()->success()
                                ->title('Anggota tim ditambahkan')
                                ->body('Anggota baru: '.implode(', ', $ditambah))
                                ->send();
                        }

                        if ($dilewati !== []) {
                            Notification::make()->warning()
                                ->title('Sebagian dilewati')
                                ->body('Sudah menjadi anggota: '.implode(', ', $dilewati))
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('catatPembayaran')
                    ->label('Catat Pembayaran')
                    ->icon('heroicon-o-banknotes')
                    ->visible(fn (Order $record) => auth()->user()->hasAnyRole([RoleName::Admin->value, RoleName::Finance->value, RoleName::Owner->value])
                        && ! in_array($record->status, [OrderStatus::Batal])
                        && $record->latestPayment?->status?->value !== 'lunas')
                    ->form([
                        Forms\Components\Select::make('metode')
                            ->options(EnumOptions::for(PaymentMethod::class))
                            ->default(PaymentMethod::Cash->value)
                            ->required(),
                        Forms\Components\TextInput::make('jumlah_dibayar')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->minValue(1),
                        Forms\Components\DatePicker::make('tanggal_bayar')
                            ->default(now()),
                    ])
                    ->action(function (Order $record, array $data) {
                        try {
                            $payment = app(PaymentService::class)->recordPayment(
                                $record,
                                PaymentMethod::from($data['metode']),
                                (float) $data['jumlah_dibayar'],
                                auth()->user(),
                                $data['tanggal_bayar'] ?? null,
                            );
                            Notification::make()->success()->title('Pembayaran tercatat')->body("Status: {$payment->status->value}")->send();
                        } catch (BusinessRuleException|AuthorizationException $e) {
                            Notification::make()->danger()->title('Gagal mencatat pembayaran')->body($e->getMessage())->send();
                        }
                    }),

                Tables\Actions\Action::make('jadwalkanUlang')
                    ->label('Jadwalkan Ulang')
                    ->icon('heroicon-o-calendar-days')
                    ->color('warning')
                    ->visible(fn (Order $record) => auth()->user()->hasAnyRole([RoleName::Admin->value, RoleName::Owner->value])
                        && $record->status === OrderStatus::Terkendala)
                    ->modalHeading('Jadwalkan Ulang Order')
                    ->modalDescription(fn (Order $record) => 'Alasan kendala sebelumnya: '.$record->alasan_kendala)
                    ->form([
                        Forms\Components\DatePicker::make('tanggal_jadwal')
                            ->required()
                            ->default(now()->addDay()),
                        Forms\Components\TimePicker::make('jam_jadwal'),
                    ])
                    ->action(function (Order $record, array $data) {
                        try {
                            app(OrderService::class)->reschedule(
                                $record,
                                auth()->user(),
                                $data['tanggal_jadwal'],
                                $data['jam_jadwal'] ?? null,
                            );
                            Notification::make()->success()->title('Order dijadwalkan ulang')->send();
                        } catch (BusinessRuleException|AuthorizationException $e) {
                            Notification::make()->danger()->title('Gagal menjadwalkan ulang')->body($e->getMessage())->send();
                        }
                    }),

                Tables\Actions\Action::make('batalkan')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Order $record) => auth()->user()->hasAnyRole([RoleName::Admin->value, RoleName::Owner->value])
                        && in_array($record->status, [OrderStatus::Baru, OrderStatus::Terjadwal, OrderStatus::Terkendala]))
                    ->form([
                        Forms\Components\Textarea::make('alasan')->label('Alasan pembatalan'),
                    ])
                    ->action(function (Order $record, array $data) {
                        try {
                            app(OrderService::class)->cancel($record, auth()->user(), $data['alasan'] ?? null);
                            Notification::make()->success()->title('Order dibatalkan')->send();
                        } catch (BusinessRuleException|AuthorizationException $e) {
                            Notification::make()->danger()->title('Gagal membatalkan order')->body($e->getMessage())->send();
                        }
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
