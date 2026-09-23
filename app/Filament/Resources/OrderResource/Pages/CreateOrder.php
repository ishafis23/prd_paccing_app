<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Enums\CustomerArea;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\UnitType;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\OrderResource;
use App\Models\Customer;
use App\Models\CustomerAcUnit;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\ServiceCatalog;
use App\Models\Titik;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\OrderService;
use App\Support\EnumOptions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * Wizard 3-langkah (dev-plan/18, direvisi dev-plan/admin/03): Data
 * Pelanggan -> Layanan (bisa >1 titik alamat, tiap titik >1 unit AC +
 * layanan, alamat titik pertama otomatis default alamat utama customer)
 * -> Ringkasan & Total. SATU submission bisa menghasilkan LEBIH DARI SATU
 * Order (1 per alamat, ikut aturan "1 order = 1 kunjungan = 1 alamat" yg
 * sudah baku di OrderService) — lihat OrderService::createOrders(). Form
 * ini di-override di level PAGE (bukan OrderResource::form()) supaya
 * tidak menular ke halaman lain kalau nanti ada Edit Order yg butuh form
 * single-alamat biasa.
 */
class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    /** @var array<int, Order> */
    protected array $createdOrders = [];

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Wizard::make([
                $this->stepDataPelanggan(),
                $this->stepAlamatLayanan(),
                $this->stepRingkasan(),
            ])
                ->submitAction(new HtmlString(Blade::render(<<<'BLADE'
                    <x-filament::button type="submit" size="sm">
                        Proses Order
                    </x-filament::button>
                    BLADE)))
                ->columnSpanFull(),
        ]);
    }

    protected function stepDataPelanggan(): Forms\Components\Wizard\Step
    {
        return Forms\Components\Wizard\Step::make('Data Pelanggan')
            ->schema([
                Forms\Components\Radio::make('mode_pelanggan')
                    ->label('Pelanggan')
                    ->options([
                        'terdaftar' => 'Pelanggan Terdaftar',
                        'baru' => 'Pelanggan Baru',
                    ])
                    ->default('terdaftar')
                    ->inline()
                    ->live()
                    ->required(),

                Forms\Components\Select::make('customer_id')
                    ->label('Customer')
                    ->options(fn () => Customer::query()->pluck('nama', 'id'))
                    ->searchable()
                    ->required(fn (Get $get): bool => $get('mode_pelanggan') !== 'baru')
                    ->visible(fn (Get $get): bool => $get('mode_pelanggan') !== 'baru')
                    ->live(),

                Forms\Components\Select::make('jenis_pelanggan')
                    ->label('Jenis Pelanggan')
                    ->options([
                        'perorangan' => 'Cust Umum',
                        'company' => 'Instansi',
                    ])
                    ->helperText('Berlaku utk semua alamat di submission ini. Menentukan wajib/tidaknya bukti pembayaran diupload teknisi.')
                    ->required()
                    ->default(fn (Get $get): string => Customer::find($get('customer_id'))?->jenis?->value ?? 'perorangan'),

                Forms\Components\Section::make('Data Pelanggan Baru')
                    ->visible(fn (Get $get): bool => $get('mode_pelanggan') === 'baru')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('pelanggan_baru.nama')
                            ->label('Nama')
                            ->required(fn (Get $get): bool => $get('mode_pelanggan') === 'baru')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('pelanggan_baru.no_hp')
                            ->label('No. HP/WA')
                            ->required(fn (Get $get): bool => $get('mode_pelanggan') === 'baru')
                            ->maxLength(20)
                            ->live(onBlur: true)
                            ->helperText(function (Get $get): ?string {
                                $noHp = trim((string) $get('pelanggan_baru.no_hp'));
                                if ($noHp === '') {
                                    return null;
                                }

                                $ketemu = app(CustomerService::class)->cariByNoHp($noHp);

                                return $ketemu
                                    ? "⚠️ No. HP ini sudah terdaftar atas nama {$ketemu->nama} — pastikan ini memang pelanggan baru."
                                    : null;
                            }),
                        Forms\Components\TextInput::make('pelanggan_baru.alamat')
                            ->label('Alamat Awal')
                            ->helperText('Jadi alamat titik pertama di langkah berikutnya (bisa diganti di sana kalau ternyata beda).')
                            ->columnSpanFull()
                            ->maxLength(65535),
                        Forms\Components\TextInput::make('pelanggan_baru.maps_link')
                            ->label('Titik Map (opsional)')
                            ->url()
                            ->columnSpanFull(),
                        Forms\Components\Select::make('pelanggan_baru.area')
                            ->label('Area (opsional)')
                            ->options(EnumOptions::for(CustomerArea::class)),
                        Forms\Components\Select::make('pelanggan_baru.sumber_lead')
                            ->label('Sumber Lead (opsional)')
                            ->options(EnumOptions::for(LeadSource::class)),
                        Forms\Components\TextInput::make('pelanggan_baru.email')
                            ->label('Email (opsional)')
                            ->email()
                            ->maxLength(255),
                    ]),
            ]);
    }

    protected function stepAlamatLayanan(): Forms\Components\Wizard\Step
    {
        return Forms\Components\Wizard\Step::make('Layanan')
            ->schema([
                Forms\Components\Repeater::make('alamat')
                    ->label('')
                    ->addActionLabel('+ Tambah Titik Alamat')
                    ->minItems(1)
                    ->required()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => filled($state['alamat_baru']['alamat'] ?? null)
                        ? $state['alamat_baru']['alamat']
                        : (filled($state['customer_address_id'] ?? null)
                            ? CustomerAddress::find($state['customer_address_id'])?->labelTampil()
                            : 'Titik alamat baru'))
                    ->schema([
                        Forms\Components\Radio::make('mode')
                            ->label('Alamat')
                            ->options(['existing' => 'Alamat Tersimpan', 'baru' => 'Alamat Baru'])
                            // ../mode_pelanggan cuma naik 1 hop (lewat index item
                            // repeater), belum sampai root — sama seperti bug
                            // customer_address_id di bawah, butuh 2 hop.
                            ->default(fn (Get $get): string => $get('../../mode_pelanggan') === 'baru' ? 'baru' : 'existing')
                            ->visible(fn (Get $get): bool => $get('../../mode_pelanggan') !== 'baru')
                            ->inline()
                            ->live(),

                        Forms\Components\Select::make('customer_address_id')
                            ->label('Pilih Alamat')
                            ->helperText('Otomatis terisi alamat utama customer. Ganti kalau order ini di alamat lain.')
                            ->options(fn (Get $get) => filled($get('../../customer_id'))
                                ? Customer::find($get('../../customer_id'))?->addresses()->orderBy('id')->get()
                                    ->mapWithKeys(fn (CustomerAddress $a) => [$a->id => $a->labelTampil()])
                                : [])
                            // Path relatif BUKAN ../customer_id (itu cuma naik 1
                            // level, lewat index item repeater "alamat" doang,
                            // masih nyangkut di dalam repeater itu sendiri) —
                            // tiap "../" cuma buang 1 segmen path (lihat
                            // HasState::generateRelativeStatePath, beforeLast('.')),
                            // dan index item repeater ITU SENDIRI adalah 1
                            // segmen terpisah dari nama repeater-nya. Perlu
                            // ../../customer_id (2 hop: lewat index item, lalu
                            // lewat nama repeater "alamat") baru sampai ke root.
                            // Sebelumnya SELALU null → dropdown ini SELALU kosong
                            // regardless drop searchable — bug user: alamat
                            // "kadang tidak otomatis mengisi" (dibuktikan empiris
                            // via helperText debug, bukan tebakan). Sekalian
                            // ->searchable() DIHAPUS: opsinya cuma sedikit per
                            // customer, dan versi remote-search closure begini
                            // riskan gagal resolve path yg sama saat request AJAX
                            // pencarian terpisah dari render biasa.
                            //
                            // ->default() (dev-plan/admin/03, B79): pra-isi
                            // alamat utama customer supaya dropdown TIDAK
                            // pernah tampak kosong utk customer yang sudah
                            // punya alamat tersimpan (akar kebingungan admin
                            // yg berujung isi Assign Teknisi+Tim sekaligus,
                            // lihat OrderService::createOrders() guard
                            // konflik) — admin tetap bebas ganti ke alamat
                            // lain kalau order ini memang di lokasi berbeda.
                            ->default(fn (Get $get) => filled($get('../../customer_id'))
                                ? Customer::find($get('../../customer_id'))?->alamatUtama()?->id
                                : null)
                            ->visible(fn (Get $get): bool => $get('../../mode_pelanggan') !== 'baru' && $get('mode') !== 'baru')
                            ->live(),

                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('alamat_baru.nama_lokasi')
                                ->label('Nama Lokasi (opsional)')
                                ->maxLength(255),
                            Forms\Components\Textarea::make('alamat_baru.alamat')
                                ->label('Alamat')
                                ->helperText('Titik pertama: kosongkan utk pakai Alamat Awal dari Data Pelanggan.')
                                ->columnSpanFull(),
                            Forms\Components\TextInput::make('alamat_baru.maps_link')
                                ->label('Titik Map (opsional)')
                                ->url()
                                ->columnSpanFull(),
                        ])
                            ->columns(2)
                            ->visible(fn (Get $get): bool => $get('../../mode_pelanggan') === 'baru' || $get('mode') === 'baru'),

                        Forms\Components\Select::make('teknisi_id')
                            ->label('Teknisi/PIC (opsional)')
                            ->options(fn () => User::role(RoleName::Teknisi->value)->pluck('name', 'id'))
                            ->searchable()
                            ->live(),
                        Forms\Components\Select::make('pendamping_teknisi_id')
                            ->label('Pendamping (opsional)')
                            ->helperText('Kalau teknisi ini jalan berdua (rekan sedang sakit/tdk masuk dianggap jalan sendiri, kosongkan).')
                            ->options(fn (Get $get) => User::role(RoleName::Teknisi->value)
                                ->when(filled($get('teknisi_id')), fn ($q) => $q->whereKeyNot($get('teknisi_id')))
                                ->pluck('name', 'id'))
                            ->searchable(),
                        Forms\Components\DatePicker::make('tanggal_jadwal'),
                        Forms\Components\Select::make('titik_id')
                            ->label('Titik/Jam Kunjungan')
                            ->options(fn () => Titik::where('aktif', true)->orderBy('urutan')->get()
                                ->mapWithKeys(fn (Titik $t) => [$t->id => $t->nama.' ('.Carbon::parse($t->jam)->format('H:i').')']))
                            ->searchable(),
                        Forms\Components\Toggle::make('is_klaim')
                            ->label('Pekerjaan klaim/garansi (tidak ditagih)'),
                        Forms\Components\Textarea::make('catatan_admin')
                            ->label('Catatan (opsional)')
                            ->columnSpanFull(),

                        Forms\Components\Repeater::make('items')
                            ->label('Unit AC & Layanan')
                            ->addActionLabel('+ Tambah Unit/Layanan')
                            ->minItems(1)
                            ->required()
                            ->columns(2)
                            ->schema([
                                Forms\Components\Radio::make('unit_mode')
                                    ->label('Unit AC')
                                    ->options([
                                        'existing' => 'Unit Tersimpan',
                                        'baru' => 'Unit Baru',
                                        'tidak_ada' => 'Tanpa Unit Spesifik',
                                    ])
                                    ->default('baru')
                                    ->inline()
                                    ->live()
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('customer_ac_unit_id')
                                    ->label('Pilih Unit AC')
                                    ->options(function (Get $get) {
                                        // 4 hop ke root customer_id: lewat index
                                        // item "items", nama repeater "items",
                                        // index item "alamat", nama repeater
                                        // "alamat" — dibuktikan empiris lewat
                                        // helperText debug (bukan tebakan), sama
                                        // kasus dgn customer_address_id di atas.
                                        $customerId = $get('../../../../customer_id');
                                        if (blank($customerId)) {
                                            return [];
                                        }

                                        // customer_address_id boleh kosong (admin
                                        // memilih "pakai alamat utama customer") —
                                        // fallback ke alamat utama di sini juga,
                                        // supaya daftar Unit AC tidak ikut kosong
                                        // gara-gara alamat tidak dipilih eksplisit.
                                        // 2 hop: lewat index item "items", nama
                                        // repeater "items" -> sampai level alamat.{i}
                                        // tempat customer_address_id berada.
                                        $alamatId = $get('../../customer_address_id');
                                        if (blank($alamatId)) {
                                            $alamatId = Customer::find($customerId)?->alamatUtama()?->id;
                                        }

                                        if (blank($alamatId)) {
                                            return [];
                                        }

                                        return CustomerAcUnit::query()
                                            ->where('customer_id', $customerId)
                                            ->where('customer_address_id', $alamatId)
                                            ->get()
                                            ->mapWithKeys(fn (CustomerAcUnit $u) => [$u->id => $u->labelTampil()]);
                                    })
                                    // TANPA ->searchable() — sama seperti
                                    // customer_address_id di atas: path relatif
                                    // (../../../../customer_id, ../../customer_address_id)
                                    // tidak konsisten ke-resolve saat request AJAX
                                    // pencarian terpisah, daftar unit per alamat
                                    // selalu pendek.
                                    ->live()
                                    ->visible(fn (Get $get): bool => $get('unit_mode') === 'existing')
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('unit_baru.kode_ruangan')
                                    ->label('Ruangan/Lokasi Unit')
                                    ->required(fn (Get $get): bool => $get('unit_mode') === 'baru')
                                    ->visible(fn (Get $get): bool => $get('unit_mode') === 'baru'),
                                Forms\Components\Select::make('unit_baru.jenis_unit')
                                    ->label('Jenis Unit')
                                    ->options(EnumOptions::for(UnitType::class))
                                    ->live()
                                    ->visible(fn (Get $get): bool => $get('unit_mode') === 'baru'),
                                Forms\Components\TextInput::make('unit_baru.pk')
                                    ->label('PK')
                                    ->placeholder('mis. 1 PK')
                                    ->live()
                                    ->visible(fn (Get $get): bool => $get('unit_mode') === 'baru'),

                                Forms\Components\Select::make('service_catalog_id')
                                    ->label('Layanan')
                                    ->helperText('Daftar difilter otomatis kalau jenis unit sudah diketahui.')
                                    ->options(function (Get $get) {
                                        $jenisUnit = null;
                                        $pk = null;

                                        if ($get('unit_mode') === 'existing' && filled($get('customer_ac_unit_id'))) {
                                            $unit = CustomerAcUnit::find($get('customer_ac_unit_id'));
                                            $jenisUnit = $unit?->jenis_unit?->value;
                                            $pk = $unit?->pk;
                                        } elseif ($get('unit_mode') === 'baru') {
                                            $jenisUnit = $get('unit_baru.jenis_unit');
                                            $pk = $get('unit_baru.pk');
                                        }

                                        $query = ServiceCatalog::query()->where('aktif', true);

                                        if (filled($jenisUnit)) {
                                            $cocok = (clone $query)->where('jenis_unit', $jenisUnit)
                                                ->when(filled($pk), fn ($q) => $q->where('pk', $pk));

                                            if ($cocok->exists()) {
                                                $query = $cocok;
                                            }
                                        }

                                        return $query->get()
                                            ->mapWithKeys(fn (ServiceCatalog $c) => [$c->id => $c->labelLayanan().' (Rp'.number_format((float) $c->harga, 0, ',', '.').')']);
                                    })
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                        $catalog = filled($state) ? ServiceCatalog::find($state) : null;
                                        $set('harga', $catalog?->harga);
                                    }),
                                Forms\Components\TextInput::make('harga')
                                    ->label('Harga (opsional, override katalog)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->minValue(0),
                                Forms\Components\TextInput::make('jumlah')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->required(),
                                Forms\Components\Textarea::make('catatan')
                                    ->label('Catatan (opsional)')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    protected function stepRingkasan(): Forms\Components\Wizard\Step
    {
        return Forms\Components\Wizard\Step::make('Ringkasan')
            ->schema([
                Forms\Components\Placeholder::make('ringkasan')
                    ->label('')
                    ->content(function (Get $get): HtmlString {
                        $alamatList = $get('alamat') ?? [];
                        $grandTotal = 0.0;
                        $html = '';

                        foreach (array_values($alamatList) as $i => $blok) {
                            $alamatLabel = filled($blok['alamat_baru']['alamat'] ?? null)
                                ? $blok['alamat_baru']['alamat']
                                : (filled($blok['customer_address_id'] ?? null)
                                    ? (CustomerAddress::find($blok['customer_address_id'])?->labelTampil() ?? '—')
                                    : '— alamat utama customer —');

                            $penanggungJawab = filled($blok['teknisi_id'] ?? null)
                                ? (User::find($blok['teknisi_id'])?->name ?? '—')
                                    .(filled($blok['pendamping_teknisi_id'] ?? null)
                                        ? ' + '.(User::find($blok['pendamping_teknisi_id'])?->name ?? '—')
                                        : '')
                                : '— belum di-assign —';

                            $titik = filled($blok['titik_id'] ?? null) ? Titik::find($blok['titik_id']) : null;
                            $titikLabel = $titik !== null
                                ? $titik->nama.' ('.Carbon::parse($titik->jam)->format('H:i').')'
                                : '— belum dipilih —';

                            $subtotal = 0.0;
                            $itemsHtml = '';

                            foreach (array_values($blok['items'] ?? []) as $item) {
                                $catalog = filled($item['service_catalog_id'] ?? null) ? ServiceCatalog::find($item['service_catalog_id']) : null;
                                $harga = filled($item['harga'] ?? null) ? (float) $item['harga'] : (float) ($catalog?->harga ?? 0);
                                $jumlah = max(1, (int) ($item['jumlah'] ?? 1));
                                $lineTotal = $harga * $jumlah;
                                $subtotal += $lineTotal;

                                $itemsHtml .= '<li>'.e($catalog?->labelLayanan() ?? '—')
                                    .' &times;'.$jumlah
                                    .' — Rp'.number_format($lineTotal, 0, ',', '.').'</li>';
                            }

                            $grandTotal += $subtotal;

                            $html .= '<div class="rounded-lg border p-4 mb-3">'
                                .'<p class="font-semibold">Alamat '.($i + 1).': '.e($alamatLabel).'</p>'
                                .'<p class="text-sm opacity-70">Titik: '.e($titikLabel).'</p>'
                                .'<p class="text-sm opacity-70">Ditugaskan ke: '.e($penanggungJawab ?? '—').'</p>'
                                .'<ul class="list-disc ml-5 text-sm">'.$itemsHtml.'</ul>'
                                .'<p class="text-sm font-medium">Subtotal: Rp'.number_format($subtotal, 0, ',', '.').'</p>'
                                .'</div>';
                        }

                        $html .= '<p class="text-lg font-bold">Total Keseluruhan: Rp'.number_format($grandTotal, 0, ',', '.').'</p>';

                        return new HtmlString($html);
                    }),
            ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $this->createdOrders = app(OrderService::class)->createOrders($data, auth()->user());
        } catch (BusinessRuleException|AuthorizationException $e) {
            Notification::make()->danger()->title('Order gagal dibuat')->body($e->getMessage())->send();
            $this->halt();
        }

        return $this->createdOrders[0];
    }

    protected function getCreatedNotification(): ?Notification
    {
        $count = count($this->createdOrders);
        $ids = collect($this->createdOrders)->pluck('id')->implode(', #');

        return Notification::make()
            ->success()
            ->title($count > 1 ? "{$count} order dibuat, satu per alamat" : 'Order dibuat')
            ->body("#{$ids}");
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
