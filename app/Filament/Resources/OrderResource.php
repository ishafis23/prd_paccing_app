<?php

namespace App\Filament\Resources;

use App\Enums\CustomerArea;
use App\Enums\CustomerJenis;
use App\Enums\ExpenseCategory;
use App\Enums\IncomeCategory;
use App\Enums\LeadSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Enums\UnitType;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Customer;
use App\Models\CustomerAcUnit;
use App\Models\CustomerAddress;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ServiceCatalog;
use App\Models\Team;
use App\Models\Titik;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\FinanceService;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Support\EnumOptions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Components\Actions;
use Filament\Infolists\Components\Actions\Action as InfolistAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class OrderResource extends BaseResource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Customer & Order';

    public static function getEloquentQuery(): Builder
    {
        // Hindari N+1 utk kolom Tim, Laporan, Total & seksi infolist (B21/§3.11/dev-plan13).
        return parent::getEloquentQuery()->with(['timTeknisi', 'workReports.photos.orderItem.acUnit', 'orderItems.acUnit', 'pelaporPerbaikan', 'team']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // dev-plan/16: pilihan pelanggan baru vs terdaftar.
                Forms\Components\Radio::make('mode_pelanggan')
                    ->label('Pelanggan')
                    ->options([
                        'terdaftar' => 'Pelanggan Terdaftar',
                        'baru' => 'Pelanggan Baru',
                    ])
                    ->default('terdaftar')
                    ->inline()
                    ->live()
                    ->columnSpanFull(),

                Forms\Components\Select::make('customer_id')
                    ->label('Customer')
                    ->options(fn () => Customer::query()->pluck('nama', 'id'))
                    ->searchable()
                    ->required(fn (Get $get): bool => ($get('mode_pelanggan') ?? 'terdaftar') !== 'baru')
                    ->visible(fn (Get $get): bool => ($get('mode_pelanggan') ?? 'terdaftar') !== 'baru')
                    ->live()
                    ->afterStateUpdated(function (Forms\Set $set, $state) {
                        $customer = Customer::find($state);
                        $set('alamat_pengerjaan', $customer?->alamat);
                        $set('jenis_pelanggan', $customer?->jenis?->value);
                        $set('customer_address_id', $customer?->alamatUtama()?->id);
                        $set('customer_ac_unit_id', null);
                    }),
                Forms\Components\Select::make('customer_address_id')
                    ->label('Alamat (opsional)')
                    ->helperText('Alamat mana yg akan dikerjakan — default alamat utama customer (dev-plan/14).')
                    ->options(fn (Get $get) => filled($get('customer_id'))
                        ? CustomerAddress::query()->where('customer_id', $get('customer_id'))->orderBy('id')->get()
                            ->mapWithKeys(fn (CustomerAddress $a) => [$a->id => $a->labelTampil()])
                        : [])
                    ->searchable()
                    ->visible(fn (Get $get): bool => ($get('mode_pelanggan') ?? 'terdaftar') !== 'baru')
                    ->live()
                    ->afterStateUpdated(function (Forms\Set $set, $state) {
                        $alamat = CustomerAddress::find($state);
                        $set('alamat_pengerjaan', $alamat?->alamat);
                        $set('customer_ac_unit_id', null);
                    }),

                // dev-plan/16, B57/B58: blok "Pelanggan Baru" — nama/no HP/
                // jenis/area/sumber lead/email + unit AC pertama (opsional).
                Forms\Components\Section::make('Data Pelanggan Baru')
                    ->visible(fn (Get $get): bool => ($get('mode_pelanggan') ?? 'terdaftar') === 'baru')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('pelanggan_baru_nama')
                            ->label('Nama')
                            ->required(fn (Get $get): bool => $get('mode_pelanggan') === 'baru')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('pelanggan_baru_no_hp')
                            ->label('No. HP/WA')
                            ->required(fn (Get $get): bool => $get('mode_pelanggan') === 'baru')
                            ->maxLength(20)
                            ->live(onBlur: true)
                            ->helperText(function (Get $get): ?string {
                                $noHp = trim((string) $get('pelanggan_baru_no_hp'));
                                if ($noHp === '') {
                                    return null;
                                }

                                $ketemu = app(CustomerService::class)->cariByNoHp($noHp);

                                return $ketemu
                                    ? "⚠️ No. HP ini sudah terdaftar atas nama {$ketemu->nama} — pastikan ini memang pelanggan baru, atau pindah ke 'Pelanggan Terdaftar'."
                                    : null;
                            }),
                        Forms\Components\Select::make('pelanggan_baru_jenis')
                            ->label('Jenis Pelanggan')
                            ->options(EnumOptions::for(CustomerJenis::class))
                            ->default(CustomerJenis::Perorangan->value)
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set, $state) => $set('jenis_pelanggan', $state)),
                        Forms\Components\Select::make('pelanggan_baru_area')
                            ->label('Area')
                            ->options(EnumOptions::for(CustomerArea::class))
                            ->required(fn (Get $get): bool => $get('mode_pelanggan') === 'baru'),
                        Forms\Components\Select::make('pelanggan_baru_sumber_lead')
                            ->label('Sumber Lead')
                            ->options(EnumOptions::for(LeadSource::class))
                            ->default(LeadSource::Lainnya->value),
                        Forms\Components\TextInput::make('pelanggan_baru_email')
                            ->label('Email (opsional)')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('pelanggan_baru_kode_ruangan')
                            ->label('Ruangan Unit AC Pertama (opsional)')
                            ->helperText('Kosongkan kalau belum mau dicatat sekarang — bisa dilengkapi belakangan lewat menu Customer.')
                            ->maxLength(255),
                        Forms\Components\Select::make('pelanggan_baru_jenis_unit')
                            ->label('Jenis Unit AC (opsional)')
                            ->options(EnumOptions::for(UnitType::class)),
                        Forms\Components\TextInput::make('pelanggan_baru_pk')
                            ->label('PK (opsional)')
                            ->maxLength(20)
                            ->placeholder('mis. 1 PK'),
                    ]),

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
                Forms\Components\Select::make('customer_ac_unit_id')
                    ->label('Unit AC (opsional)')
                    ->helperText('Pilih unit spesifik di ALAMAT terpilih — supaya laporan teknisi menunjuk ke unit yang benar.')
                    ->options(function (Get $get) {
                        return CustomerAcUnit::query()
                            ->where('customer_id', $get('customer_id'))
                            ->when(filled($get('customer_address_id')), fn ($q) => $q->where('customer_address_id', $get('customer_address_id')))
                            ->orderBy('kode_unit')
                            ->get()
                            ->mapWithKeys(fn (CustomerAcUnit $u) => [$u->id => $u->labelTampil()]);
                    })
                    ->searchable()
                    ->visible(fn (Get $get): bool => ($get('mode_pelanggan') ?? 'terdaftar') !== 'baru'),
                Forms\Components\Select::make('teknisi_id')
                    ->label('Assign Teknisi (opsional)')
                    ->options(fn () => User::role(RoleName::Teknisi->value)->pluck('name', 'id'))
                    ->searchable(),
                Forms\Components\Textarea::make('alamat_pengerjaan')
                    ->columnSpanFull(),
                Forms\Components\Select::make('jenis_pelanggan')
                    ->label('Jenis Pelanggan')
                    ->options([
                        CustomerJenis::Perorangan->value => 'Cust Umum',
                        CustomerJenis::Company->value => 'Instansi',
                    ])
                    ->helperText('Menentukan wajib/tidaknya upload bukti pembayaran oleh teknisi. Default mengikuti data customer, bisa diubah di sini.')
                    ->required(),
                Forms\Components\DatePicker::make('tanggal_jadwal'),
                Forms\Components\TimePicker::make('jam_jadwal'),
                Forms\Components\Textarea::make('catatan_admin')
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_klaim')
                    ->label('Pekerjaan klaim/garansi (tidak ditagih)')
                    ->helperText('dev-plan/15 B50: order klaim dikecualikan dari hitungan titik/unit/omset skema insentif Games.')
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Tabs::make('order-tabs')
                    ->tabs([
                        Tabs\Tab::make('Order')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        \Filament\Infolists\Components\View::make('order.info-table')
                                            ->state(fn (Order $record): Order => $record),
                                    ]),
                            ]),

                        Tabs\Tab::make('Rincian Layanan')
                            ->schema([
                                TextEntry::make('warning_discrepancy')
                                    ->label('⚠️ Peringatan Discrepancy')
                                    ->columnSpanFull()
                                    ->visible(function (Order $record): bool {
                                        $calculated = $record->orderItems->reject(fn (OrderItem $i) => $i->dibatalkan())->sum(fn (OrderItem $i) => (float) $i->harga * $i->jumlah);
                                        return $calculated != $record->total();
                                    })
                                    ->state(function (Order $record): string {
                                        $calculated = $record->orderItems->reject(fn (OrderItem $i) => $i->dibatalkan())->sum(fn (OrderItem $i) => (float) $i->harga * $i->jumlah);
                                        $actual = $record->total();
                                        $diff = abs($actual - $calculated);
                                        return "Total yang dihitung dari item (Rp".number_format($calculated, 0, ',', '.')
                                            .") tidak sama dengan Total Tagihan (Rp".number_format($actual, 0, ',', '.')
                                            ."). Selisih: Rp".number_format($diff, 0, ',', '.')
                                            .". Periksa nilai Jumlah di item layanan di atas.";
                                    })
                                    ->color('danger'),

                                Section::make('Daftar Layanan')
                                    ->description('Baris pertama otomatis dari Jenis Layanan di atas. Tambah baris baru lewat aksi "Tambah Layanan".')
                                    ->schema([
                                        \Filament\Infolists\Components\View::make('components.order-items-table')
                                            ->state(fn (Order $record) => $record->orderItems),
                                    ]),
                                Section::make('Total Tagihan')
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('total')
                                            ->label('Total')
                                            ->state(fn (Order $record) => 'Rp'.number_format($record->total(), 0, ',', '.'))
                                            ->size('lg')
                                            ->extraAttributes(['class' => 'font-bold text-success']),
                                        Actions::make([
                                            InfolistAction::make('koreksiTotal')
                                            ->label('Koreksi Total')
                                            ->icon('heroicon-o-pencil-square')
                                            ->color('warning')
                                            ->iconButton()
                                            ->tooltip('Koreksi Total')
                                            ->form([
                                                Forms\Components\TextInput::make('total_terkoreksi')
                                                    ->label('Total Terkoreksi')
                                                    ->numeric()
                                                    ->prefix('Rp')
                                                    ->required()
                                                    ->minValue(0)
                                                    ->default(fn (Order $record) => $record->total()),
                                                Forms\Components\Textarea::make('alasan')
                                                    ->label('Alasan Koreksi')
                                                    ->required()
                                                    ->maxLength(500),
                                            ])
                                            ->action(function (array $data, Order $record): void {
                                                try {
                                                    $totalBaru = (float) $data['total_terkoreksi'];
                                                    $alasan = $data['alasan'];
                                                    $totalLama = $record->total();

                                                    app(OrderService::class)->koreksiTotal(
                                                        $record,
                                                        $totalBaru,
                                                        $alasan,
                                                        auth()->user()
                                                    );

                                                    Notification::make()
                                                        ->success()
                                                        ->title('Total berhasil dikoreksi')
                                                        ->body("Total lama: Rp".number_format($totalLama, 0, ',', '.')." → Total baru: Rp".number_format($totalBaru, 0, ',', '.'))
                                                        ->send();

                                                    $record->refresh();
                                                } catch (\Exception $e) {
                                                    Notification::make()
                                                        ->danger()
                                                        ->title('Gagal mengkoreksi total')
                                                        ->body($e->getMessage())
                                                        ->send();

                                                    throw new Halt();
                                                }
                                            }),
                                        ]),
                                    ]),
                            ]),

                        OrderResource\LaporanPengerjaanTab::tab(),

                        Tabs\Tab::make('Pembayaran')
                            ->schema([
                                Section::make()
                                    ->columns(2)
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
                                            ->columnSpanFull()
                                            ->disk('public')
                                            ->visible(fn (Order $record): bool => filled($record->bukti_pembayaran)),
                                    ]),
                            ]),

                        Tabs\Tab::make('Pengeluaran')
                            ->schema([
                                Section::make('Material & Perawatan (Admin)')
                                    ->description('Pengeluaran yang dicatat admin untuk order ini.')
                                    ->headerActions([
                                        InfolistAction::make('tambahPengeluaran')
                                            ->label('Tambah Pengeluaran')
                                            ->icon('heroicon-o-plus')
                                            ->color('primary')
                                            ->modalHeading('Tambah Pengeluaran')
                                            ->form([
                                                Forms\Components\Select::make('kategori')
                                                    ->options([
                                                        ExpenseCategory::Material->value => 'Material',
                                                        ExpenseCategory::Perawatan->value => 'Perawatan',
                                                    ])
                                                    ->required(),
                                                Forms\Components\TextInput::make('qty')
                                                    ->label('Qty')
                                                    ->numeric()
                                                    ->default(1)
                                                    ->minValue(1)
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => $set('nominal', round((float) $get('qty') * (float) $get('harga'), 2))),
                                                Forms\Components\TextInput::make('harga')
                                                    ->label('Harga Satuan')
                                                    ->numeric()
                                                    ->prefix('Rp')
                                                    ->minValue(0)
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => $set('nominal', round((float) $get('qty') * (float) $get('harga'), 2))),
                                                Forms\Components\TextInput::make('nominal')
                                                    ->label('Total')
                                                    ->numeric()
                                                    ->prefix('Rp')
                                                    ->required()
                                                    ->minValue(1)
                                                    ->helperText('Otomatis dari Qty × Harga, bisa dikoreksi manual.'),
                                                Forms\Components\DatePicker::make('tanggal')
                                                    ->default(now())
                                                    ->required(),
                                                Forms\Components\Textarea::make('keterangan')
                                                    ->columnSpanFull(),
                                                Forms\Components\FileUpload::make('bukti')
                                                    ->label('Bukti/Struk')
                                                    ->image()
                                                    ->directory('expenses'),
                                            ])
                                            ->action(function (array $data, Order $record): void {
                                                try {
                                                    self::tambahPengeluaran($record, $data);

                                                    Notification::make()->success()->title('Pengeluaran ditambahkan')->send();
                                                    $record->refresh();
                                                    $record->unsetRelation('expenses');
                                                } catch (\Exception $e) {
                                                    Notification::make()->danger()->title('Gagal menambah pengeluaran')->body($e->getMessage())->send();
                                                    throw new Halt();
                                                }
                                            }),
                                    ])
                                    ->schema([
                                        \Filament\Infolists\Components\View::make('components.expense-table')
                                            ->state(fn (Order $record) => $record->expenses)
                                            ->default([]),
                                    ]),
                                Section::make('Operasional (Teknisi)')
                                    ->description('Pengeluaran yang diinput teknisi dan tertaut ke order ini.')
                                    ->schema([
                                        RepeatableEntry::make('teknisiExpenses')
                                            ->label('')
                                            ->columns(6)
                                            ->schema([
                                                TextEntry::make('tanggal_input')->label('Tanggal')->date('d M Y'),
                                                TextEntry::make('kategori')->label('Kategori')->badge(),
                                                TextEntry::make('keterangan')->label('Keterangan')->placeholder('—')->columnSpan(2),
                                                TextEntry::make('qty')->label('Qty')->placeholder('—'),
                                                TextEntry::make('harga')->label('Harga')->money('IDR')->placeholder('—'),
                                                TextEntry::make('nominal')->label('Total')->money('IDR'),
                                                TextEntry::make('status')->label('Status')->badge()
                                                    ->color(fn ($state): string => match ($state) {
                                                        'approved' => 'success',
                                                        'rejected' => 'danger',
                                                        default => 'warning',
                                                    }),
                                                TextEntry::make('teknisi.name')->label('Teknisi')->columnSpanFull(),
                                            ])
                                            ->placeholder('Belum ada pengeluaran teknisi'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Laporan Pengerjaan')
                            ->schema([
                                Section::make()
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
                                                RepeatableEntry::make('photos')
                                                    ->label('Foto per Kategori')
                                                    ->columnSpanFull()
                                                    ->columns(4)
                                                    ->schema([
                                                        TextEntry::make('orderItem.nama_layanan')
                                                            ->label('Layanan')
                                                            ->formatStateUsing(fn (?string $state, $record): string => $record?->orderItem?->acUnit
                                                                ? $state.' — '.$record->orderItem->acUnit->labelTampil()
                                                                : (string) $state),
                                                        TextEntry::make('slot')->label('Slot')->formatStateUsing(fn (?string $state): string => str($state ?? '')->headline()->toString()),
                                                        ImageEntry::make('path')->label('')->disk('public')->columnSpan(2),
                                                    ])
                                                    ->visible(fn ($record): bool => $record?->photos->isNotEmpty()),
                                                TextEntry::make('waktu_selesai')->label('Selesai')->dateTime('d M Y H:i'),
                                                TextEntry::make('diverifikasi_pada')
                                                    ->label('Verifikasi')
                                                    ->badge()
                                                    ->color(fn ($record) => $record?->sudahDiverifikasi() ? 'success' : 'warning')
                                                    ->formatStateUsing(fn ($state, $record) => $record?->sudahDiverifikasi()
                                                        ? 'Terverifikasi oleh '.($record->verifikator?->name ?? '—')
                                                        : 'Menunggu Verifikasi'),
                                            ]),
                                    ])
                                    ->visible(fn (Order $record): bool => $record->workReports->isNotEmpty()),
                                Section::make()
                                    ->schema([
                                        TextEntry::make('placeholder')
                                            ->label('')
                                            ->state('Belum ada laporan pengerjaan')
                                            ->columnSpanFull(),
                                    ])
                                    ->visible(fn (Order $record): bool => $record->workReports->isEmpty()),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Catat pengeluaran admin (material/perawatan) yang tertaut ke order.
     *
     * @param  array<string, mixed>  $data
     */
    public static function tambahPengeluaran(Order $record, array $data): Expense
    {
        return app(FinanceService::class)->createExpense(
            ExpenseCategory::from($data['kategori']),
            (float) $data['nominal'],
            auth()->user(),
            $data['tanggal'] ?? null,
            $data['keterangan'] ?? null,
            $data['bukti'] ?? null,
            $record->id,
            isset($data['qty']) ? (int) $data['qty'] : null,
            isset($data['harga']) ? (float) $data['harga'] : null,
        );
    }

    /**
     * Isian aksi "Buat Invoice" (tabel & halaman view Order).
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function formInvoice(): array
    {
        return [
            Forms\Components\DatePicker::make('tanggal')->default(now())->required(),
            Forms\Components\DatePicker::make('jatuh_tempo')->label('Jatuh tempo')->default(now()->addDays(7))->required(),
            Forms\Components\Textarea::make('catatan')->label('Catatan (opsional)'),
        ];
    }

    /**
     * Buat invoice dari satu order lalu arahkan ke halaman invoice-nya.
     *
     * @param  array<string, mixed>  $data
     */
    public static function buatInvoice(Order $order, array $data): void
    {
        try {
            $invoice = app(InvoiceService::class)->buat([$order], auth()->user(), $data);
        } catch (BusinessRuleException|AuthorizationException $e) {
            Notification::make()->danger()->title('Gagal membuat invoice')->body($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title('Invoice '.$invoice->nomor.' dibuat (draft)')->send();

        redirect(InvoiceResource::getUrl('view', ['record' => $invoice]));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.nama')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('serviceCatalog.jenis_layanan')->label('Layanan')->badge(),
                Tables\Columns\TextColumn::make('unit')
                    ->label('Unit')
                    ->state(fn (Order $record): string => $record->jumlahUnit().' unit')
                    ->tooltip(fn (Order $record): string => $record->ringkasanLayanan()),
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
                Tables\Columns\TextColumn::make('titik.nama')->label('Titik')->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('total')->label('Total')->state(fn (Order $record) => 'Rp'.number_format($record->total(), 0, ',', '.')),
                Tables\Columns\TextColumn::make('laporan_status')
                    ->label('Laporan')
                    ->badge()
                    ->placeholder('—')
                    ->state(function (Order $record): ?string {
                        $laporan = $record->workReports->sortByDesc('id')->first();

                        if ($laporan === null) {
                            return null;
                        }

                        return $laporan->sudahDiverifikasi() ? 'Terverifikasi' : 'Menunggu Verifikasi';
                    })
                    ->color(fn (?string $state): string => $state === 'Terverifikasi' ? 'success' : 'warning'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(EnumOptions::for(OrderStatus::class)),
                Tables\Filters\SelectFilter::make('teknisi_id')->label('Teknisi')->options(fn () => User::role(RoleName::Teknisi->value)->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('team_id')->label('Tim')->options(fn () => Team::where('aktif', true)->pluck('nama', 'id')),
                Tables\Filters\SelectFilter::make('titik_id')->label('Titik')->options(fn () => Titik::orderBy('urutan')->pluck('nama', 'id')),
                Tables\Filters\Filter::make('tanggal_jadwal')
                    ->form([
                        Forms\Components\DatePicker::make('tanggal_jadwal')->label('Tanggal Jadwal'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['tanggal_jadwal'] ?? null),
                        fn (Builder $q) => $q->whereDate('tanggal_jadwal', $data['tanggal_jadwal']),
                    ))
                    ->indicateUsing(fn (array $data): ?string => filled($data['tanggal_jadwal'] ?? null)
                        ? 'Tanggal: '.Carbon::parse($data['tanggal_jadwal'])->format('d M Y')
                        : null),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),

                    Tables\Actions\Action::make('suratJalan')
                        ->label('Surat Jalan')
                        ->icon('heroicon-o-clipboard-document-list')
                        ->color('gray')
                        ->visible(fn (Order $record) => $record->jenis_pelanggan === CustomerJenis::Company
                            && $record->status !== OrderStatus::Batal)
                        ->url(fn (Order $record) => route('surat-jalan.show', [$record->id, $record->pastikanSuratJalanToken()]))
                        ->openUrlInNewTab(),

                    Tables\Actions\Action::make('previewLaporan')
                        ->label('Preview Laporan')
                        ->icon('heroicon-o-document-magnifying-glass')
                        ->color('gray')
                        ->visible(fn (Order $record) => \App\Filament\Resources\OrderResource\LaporanPengerjaanTab::boleh()
                            && $record->status !== OrderStatus::Batal)
                        ->url(fn (Order $record) => \App\Support\Url::absolute('laporan.preview', ['order' => $record->id]))
                        ->openUrlInNewTab(),

                    Tables\Actions\Action::make('buatInvoice')
                        ->label('Buat Invoice')
                        ->icon('heroicon-o-document-plus')
                        ->color('gray')
                        ->visible(fn (Order $record) => InvoiceService::boleh(auth()->user())
                            && app(InvoiceService::class)->bisaDibuatInvoice($record))
                        ->modalHeading('Buat Invoice')
                        ->modalDescription('Baris invoice diturunkan dari layanan order ini; masih bisa diedit selama draft.')
                        ->form(self::formInvoice())
                        ->action(fn (Order $record, array $data) => self::buatInvoice($record, $data)),

                    Tables\Actions\Action::make('lihatInvoice')
                        ->label('Lihat Invoice')
                        ->icon('heroicon-o-document-currency-dollar')
                        ->color('gray')
                        ->visible(fn (Order $record) => InvoiceService::boleh(auth()->user())
                            && app(InvoiceService::class)->punyaInvoiceAktif($record))
                        ->url(fn (Order $record) => InvoiceResource::getUrl('view', ['record' => app(InvoiceService::class)->invoiceAktifOrder($record)]))
                        ->openUrlInNewTab(),

                    Tables\Actions\Action::make('tambahLayanan')
                        ->label('Tambah Layanan')
                        ->icon('heroicon-o-plus-circle')
                        ->color('gray')
                        ->visible(fn (Order $record) => auth()->user()->hasAnyRole([RoleName::Admin->value, RoleName::Owner->value])
                            && $record->status !== OrderStatus::Batal)
                        ->modalHeading('Tambah Layanan ke Order')
                        ->modalDescription('Mis. sparepart pengganti yg sudah disepakati dgn customer (alur "Ada Perbaikan"). Harga diisi manual sesuai hasil nego.')
                        ->form([
                            // Klarifikasi 21 Sep 2026: order Selesai BOLEH
                            // ditambah layanan (mis. customer telepon lagi
                            // setelah ditutup) — tapi tampilkan peringatan
                            // dulu, order ini kemungkinan sudah ditagih.
                            Forms\Components\Placeholder::make('peringatanSelesai')
                                ->label('')
                                ->visible(fn (Order $record) => $record->status === OrderStatus::Selesai)
                                ->content(new HtmlString(
                                    '<div class="flex items-start gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 ring-1 ring-amber-200">'
                                    .'<strong>Order ini sudah Selesai.</strong> Tagihan mungkin sudah dikirim/dibayar customer — '
                                    .'pastikan sudah dikonfirmasi ulang sebelum menambah layanan, karena total tagihan akan berubah.'
                                    .'</div>'
                                )),
                            Forms\Components\TextInput::make('nama_layanan')
                                ->label('Nama Layanan/Sparepart')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set) => $set('komponen', IncomeCategory::defaultUntukBaris(
                                    filled($get('kategori')) ? ServiceType::from($get('kategori')) : null,
                                    $get('nama_layanan'),
                                )->value)),
                            Forms\Components\Select::make('kategori')
                                ->options(EnumOptions::for(ServiceType::class))
                                ->live()
                                ->afterStateUpdated(fn (Get $get, Set $set) => $set('komponen', IncomeCategory::defaultUntukBaris(
                                    filled($get('kategori')) ? ServiceType::from($get('kategori')) : null,
                                    $get('nama_layanan'),
                                )->value)),
                            Forms\Components\Radio::make('komponen')
                                ->label('Masuk omset')
                                ->options(['jasa' => 'Jasa', 'material' => 'Material'])
                                ->default('jasa')
                                ->inline()
                                ->required(),
                            Forms\Components\Select::make('customer_ac_unit_id')
                                ->label('Unit AC (opsional)')
                                ->options(fn (Order $record) => CustomerAcUnit::query()
                                    ->where('customer_id', $record->customer_id)
                                    ->get()
                                    ->mapWithKeys(fn (CustomerAcUnit $u) => [$u->id => $u->labelTampil()]))
                                ->searchable(),
                            Forms\Components\TextInput::make('harga')
                                ->numeric()
                                ->prefix('Rp')
                                ->required()
                                ->minValue(0),
                            Forms\Components\TextInput::make('jumlah')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->maxValue(1000)
                                ->required(),
                            Forms\Components\Textarea::make('catatan')
                                ->columnSpanFull(),
                        ])
                        ->action(function (Order $record, array $data) {
                            try {
                                app(OrderService::class)->tambahLayanan($record, $data, auth()->user());
                                Notification::make()->success()->title('Layanan ditambahkan')->send();
                            } catch (BusinessRuleException|AuthorizationException $e) {
                                Notification::make()->danger()->title('Gagal menambah layanan')->body($e->getMessage())->send();
                            }
                        }),

                    Tables\Actions\Action::make('setujuiPerbaikan')
                        ->label('Setujui Perbaikan')
                        ->icon('heroicon-o-wrench')
                        ->color('success')
                        ->visible(fn (Order $record) => auth()->user()->hasAnyRole([RoleName::Admin->value, RoleName::Owner->value])
                            && $record->perbaikan_menunggu_konfirmasi)
                        ->modalHeading('Setujui Perbaikan')
                        ->modalDescription(fn (Order $record) => 'Customer setuju atas: '.$record->perbaikan_catatan
                            .($record->perbaikan_estimasi_harga !== null ? ' (estimasi teknisi Rp'.number_format((float) $record->perbaikan_estimasi_harga, 0, ',', '.').')' : ''))
                        ->form([
                            Forms\Components\TextInput::make('nama_layanan')
                                ->label('Nama Layanan/Sparepart')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\Select::make('kategori')
                                ->options(EnumOptions::for(ServiceType::class)),
                            Forms\Components\Select::make('customer_ac_unit_id')
                                ->label('Unit AC (opsional)')
                                ->options(fn (Order $record) => CustomerAcUnit::query()
                                    ->where('customer_id', $record->customer_id)
                                    ->get()
                                    ->mapWithKeys(fn (CustomerAcUnit $u) => [$u->id => $u->labelTampil()]))
                                ->searchable(),
                            Forms\Components\TextInput::make('harga')
                                ->label('Harga hasil deal')
                                ->numeric()
                                ->prefix('Rp')
                                ->required()
                                ->minValue(0),
                            Forms\Components\TextInput::make('jumlah')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->maxValue(1000)
                                ->required(),
                            Forms\Components\Textarea::make('catatan')
                                ->columnSpanFull(),
                        ])
                        ->fillForm(fn (Order $record): array => [
                            'nama_layanan' => $record->perbaikan_catatan,
                            'harga' => $record->perbaikan_estimasi_harga,
                        ])
                        ->action(function (Order $record, array $data) {
                            try {
                                app(OrderService::class)->setujuiPerbaikan($record, $data, auth()->user());
                                Notification::make()->success()->title('Perbaikan disetujui, layanan ditambahkan')->send();
                            } catch (BusinessRuleException|AuthorizationException $e) {
                                Notification::make()->danger()->title('Gagal menyetujui perbaikan')->body($e->getMessage())->send();
                            }
                        }),

                    Tables\Actions\Action::make('tolakPerbaikan')
                        ->label('Tolak Perbaikan')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (Order $record) => auth()->user()->hasAnyRole([RoleName::Admin->value, RoleName::Owner->value])
                            && $record->perbaikan_menunggu_konfirmasi)
                        ->modalHeading('Tolak Perbaikan')
                        ->modalDescription(fn (Order $record) => 'Customer tidak setuju atas: '.$record->perbaikan_catatan)
                        ->form([
                            Forms\Components\Textarea::make('catatan')->label('Catatan (opsional)')->columnSpanFull(),
                        ])
                        ->action(function (Order $record, array $data) {
                            try {
                                app(OrderService::class)->tolakPerbaikan($record, auth()->user(), $data['catatan'] ?? null);
                                Notification::make()->success()->title('Perbaikan ditolak')->send();
                            } catch (BusinessRuleException|AuthorizationException $e) {
                                Notification::make()->danger()->title('Gagal menolak perbaikan')->body($e->getMessage())->send();
                            }
                        }),

                    Tables\Actions\Action::make('assignTeknisi')
                        ->label('Assign Teknisi')
                        ->icon('heroicon-o-user-plus')
                        ->visible(fn (Order $record) => auth()->user()->hasAnyRole([RoleName::Admin->value, RoleName::Owner->value])
                            && ! in_array($record->status, [OrderStatus::Selesai, OrderStatus::Batal])
                            && $record->teknisi_id === null)
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

                    Tables\Actions\Action::make('assignTim')
                        ->label('Assign Tim')
                        ->icon('heroicon-o-user-group')
                        ->color('gray')
                        ->visible(fn (Order $record) => auth()->user()->hasAnyRole([RoleName::Admin->value, RoleName::Owner->value])
                            && ! in_array($record->status, [OrderStatus::Selesai, OrderStatus::Batal])
                            && $record->teknisi_id === null)
                        ->modalHeading('Assign Tim Teknisi')
                        ->modalDescription('Pilih tim tetap (dev-plan/12 §3.13) — seluruh anggotanya (termasuk PIC) otomatis ditugaskan ke order ini.')
                        ->form([
                            Forms\Components\Select::make('team_id')
                                ->label('Tim')
                                ->options(fn () => Team::where('aktif', true)->pluck('nama', 'id'))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Order $record, array $data) {
                            try {
                                app(OrderService::class)->assignTeam($record, Team::findOrFail($data['team_id']), auth()->user());
                                Notification::make()->success()->title('Tim di-assign')->send();
                            } catch (BusinessRuleException|AuthorizationException $e) {
                                Notification::make()->danger()->title('Gagal assign tim')->body($e->getMessage())->send();
                            }
                        }),

                    Tables\Actions\Action::make('gantiPic')
                        ->label('Ganti PIC')
                        ->icon('heroicon-o-arrows-right-left')
                        ->color('warning')
                        ->visible(fn (Order $record) => auth()->user()->hasAnyRole([RoleName::Admin->value, RoleName::Owner->value])
                            && ! in_array($record->status, [OrderStatus::Selesai, OrderStatus::Batal])
                            && $record->teknisi_id !== null)
                        ->modalHeading('Ganti PIC')
                        ->modalDescription('Utk teknisi berhalangan di hari-H. Status order TIDAK berubah — attendance terbuka PIC lama (kalau sempat check-in) otomatis ditutup.')
                        ->form([
                            Forms\Components\Select::make('teknisi_id')
                                ->label('PIC Baru')
                                ->options(fn (Order $record) => User::role(RoleName::Teknisi->value)
                                    ->where('id', '!=', $record->teknisi_id)
                                    ->pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                            Forms\Components\Textarea::make('alasan')->label('Alasan (opsional)')->columnSpanFull(),
                        ])
                        ->action(function (Order $record, array $data) {
                            try {
                                app(OrderService::class)->gantiPic(
                                    $record,
                                    User::findOrFail($data['teknisi_id']),
                                    auth()->user(),
                                    $data['alasan'] ?? null,
                                );
                                Notification::make()->success()->title('PIC diganti')->send();
                            } catch (BusinessRuleException|AuthorizationException $e) {
                                Notification::make()->danger()->title('Gagal ganti PIC')->body($e->getMessage())->send();
                            }
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

                    Tables\Actions\Action::make('verifikasiLaporan')
                        ->label('Verifikasi Laporan')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(fn (Order $record) => auth()->user()->hasAnyRole([RoleName::Admin->value, RoleName::Owner->value])
                            && $record->workReports->sortByDesc('id')->first()?->sudahDiverifikasi() === false)
                        ->requiresConfirmation()
                        ->modalDescription(fn (Order $record) => $record->workReports->sortByDesc('id')->first()?->catatan_pengerjaan)
                        ->modalHeading('Verifikasi Laporan Pengerjaan')
                        ->modalSubmitActionLabel('Ya, Verifikasi')
                        ->action(function (Order $record) {
                            $laporan = $record->workReports->sortByDesc('id')->first();

                            try {
                                app(OrderService::class)->verifikasiLaporan($laporan, auth()->user());
                                Notification::make()->success()->title('Laporan diverifikasi')->send();
                            } catch (BusinessRuleException|AuthorizationException $e) {
                                Notification::make()->danger()->title('Gagal verifikasi')->body($e->getMessage())->send();
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
                            Forms\Components\TextInput::make('total_tagihan')
                                ->label('Total Tagihan')
                                ->helperText('Default harga katalog. Ubah kalau ada ongkir/material tambahan (naikkan) atau diskon (turunkan).')
                                ->numeric()
                                ->prefix('Rp')
                                ->required()
                                ->minValue(1)
                                ->live()
                                ->default(fn (Order $record) => $record->latestPayment?->total_tagihan ?? $record->total()),
                            Forms\Components\TextInput::make('jumlah_dibayar')
                                ->numeric()
                                ->prefix('Rp')
                                ->required()
                                ->minValue(1),
                            Forms\Components\Textarea::make('catatan')
                                ->label('Alasan Penyesuaian')
                                ->helperText('Wajib diisi karena Total Tagihan diubah dari nilai sebelumnya.')
                                ->visible(fn (Get $get, Order $record): bool => abs((float) ($get('total_tagihan') ?? 0) - (float) ($record->latestPayment?->total_tagihan ?? $record->total())) > 0.009)
                                ->required(fn (Get $get, Order $record): bool => abs((float) ($get('total_tagihan') ?? 0) - (float) ($record->latestPayment?->total_tagihan ?? $record->total())) > 0.009),
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
                                    isset($data['total_tagihan']) ? (float) $data['total_tagihan'] : null,
                                    filled($data['catatan'] ?? null) ? $data['catatan'] : null,
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
                    ->label('Aksi')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button(),
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
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
