<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\LaporanBulanan;
use App\Services\BusinessInfoService;
use App\Services\LaporanBulananService;
use App\Services\LaporanRekapExcelService;
use App\Services\LaporanRekapPdfService;
use App\Services\LaporanRekapService;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Halaman "Laporan Bulanan" (dev-plan/21 §6): admin memilih customer + bulan
 * (+ cabang) → satu PDF berisi semua kunjungan pada periode itu. Bisa
 * diantrekan (job) atau dibuat sekarang secara sinkron untuk hosting tanpa
 * queue worker. Status tersimpan di tabel laporan_bulanan.
 */
class LaporanBulananCustomer extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.laporan-bulanan';

    protected static ?string $navigationGroup = 'Laporan';

    protected static ?string $navigationLabel = 'Laporan Bulanan';

    protected static ?string $title = 'Laporan Bulanan per Customer';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $slug = 'laporan-bulanan';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** Bulan (Y-m) utk unduhan Rekap Dana keuangan. */
    public string $bulanRekap = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([
            RoleName::Owner->value,
            RoleName::Admin->value,
            RoleName::Finance->value,
        ]) ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->bulanRekap = now()->format('Y-m');
        $this->form->fill(['customer_id' => null, 'bulan' => now()->format('Y-m'), 'customer_address_id' => null]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('customer_id')
                    ->label('Customer')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Customer::query()
                        ->where('nama', 'like', '%'.$search.'%')
                        ->orderBy('nama')->limit(30)->pluck('nama', 'id')->all())
                    ->getOptionLabelUsing(fn ($value): ?string => Customer::query()->find($value)?->nama)
                    ->live()
                    ->afterStateUpdated(fn (callable $set) => $set('customer_address_id', null))
                    ->required(),
                TextInput::make('bulan')
                    ->label('Bulan')
                    ->type('month')
                    ->required(),
                Select::make('customer_address_id')
                    ->label('Cabang (opsional)')
                    ->placeholder('Semua cabang')
                    ->options(function (callable $get): array {
                        if (! $get('customer_id')) {
                            return [];
                        }

                        // `nama_lokasi` (dan `alamat`) boleh kosong di data nyata —
                        // label opsi TIDAK BOLEH null, kalau null Filament melempar
                        // TypeError (Select::isOptionDisabled(): $label must be string).
                        return CustomerAddress::query()
                            ->where('customer_id', $get('customer_id'))
                            ->orderBy('nama_lokasi')
                            ->get()
                            ->mapWithKeys(function (CustomerAddress $alamat): array {
                                $label = trim((string) $alamat->labelTampil());

                                return [$alamat->id => $label !== '' ? $label : 'Cabang #'.$alamat->id];
                            })
                            ->all();
                    }),
            ])
            ->columns(3)
            ->statePath('data');
    }

    /** Antrekan lewat queue worker. */
    public function buatAntrean(): void
    {
        $this->buat(false);
    }

    /** Kerjakan sekarang juga — untuk lingkungan tanpa queue worker. */
    public function buatSinkron(): void
    {
        $this->buat(true);
    }

    public function hapus(int $id): void
    {
        abort_unless(static::canAccess(), 403);

        $baris = LaporanBulanan::query()->findOrFail($id);
        app(LaporanBulananService::class)->hapus($baris, auth()->user());
    }

    /**
     * @return Collection<int, LaporanBulanan>
     */
    public function riwayat(): Collection
    {
        return LaporanBulanan::query()->with(['customer', 'alamat', 'pembuat'])->latest('id')->limit(30)->get();
    }

    public function unduhRekapExcel(): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);

        [$data, $namaUsaha] = $this->dataRekap();
        $isi = app(LaporanRekapExcelService::class)->render($data, $namaUsaha);

        return response()->streamDownload(
            function () use ($isi): void {
                echo $isi;
            },
            $this->namaBerkasRekap('xlsx'),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function unduhRekapPdf(): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);

        [$data, $namaUsaha] = $this->dataRekap();
        $isi = app(LaporanRekapPdfService::class)->render($data, $namaUsaha);

        return response()->streamDownload(
            function () use ($isi): void {
                echo $isi;
            },
            $this->namaBerkasRekap('pdf'),
            ['Content-Type' => 'application/pdf'],
        );
    }

    /** @return array{0: array<string, mixed>, 1: string} */
    private function dataRekap(): array
    {
        $bulan = CarbonImmutable::createFromFormat('!Y-m', $this->bulanRekap);
        $namaUsaha = app(BusinessInfoService::class)->data()->nama_usaha ?: 'PT. Paccing Jaya Sejahtera';

        return [app(LaporanRekapService::class)->susun($bulan), $namaUsaha];
    }

    private function namaBerkasRekap(string $ekstensi): string
    {
        return 'Rekap Bulanan Dana '.$this->bulanRekap.'.'.$ekstensi;
    }

    private function buat(bool $sinkron): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();
        $customer = Customer::query()->findOrFail($data['customer_id']);

        try {
            $baris = app(LaporanBulananService::class)->ajukan(
                $customer,
                (string) $data['bulan'],
                filled($data['customer_address_id'] ?? null) ? (int) $data['customer_address_id'] : null,
                auth()->user(),
                $sinkron,
            );
        } catch (BusinessRuleException $e) {
            Notification::make()->danger()->title('Tidak bisa membuat laporan')->body($e->getMessage())->send();

            return;
        }

        if ($baris->status === LaporanBulanan::GAGAL) {
            Notification::make()->danger()->title('Laporan gagal dibuat')->body((string) $baris->pesan_error)->persistent()->send();
        } elseif ($baris->status === LaporanBulanan::SELESAI) {
            Notification::make()->success()->title('Laporan bulanan selesai')->send();
        } else {
            Notification::make()->success()->title('Laporan diantrekan')
                ->body('Jika status tidak berubah, antrean belum berjalan — pakai tombol "Buat sekarang (sinkron)".')->send();
        }
    }
}
