<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Services\FilePenyimpananService;
use App\Services\StorageQuotaService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;

/**
 * Halaman Manajemen → "Penyimpanan" (B27–B31).
 *
 * Menggantikan PenyimpananFotoWidget: ringkasan kuota + daftar semua file di
 * folder terkelola (work-reports & payment-channels) dengan pratinjau,
 * pencarian/filter/urutan, hapus per file & massal, serta tombol menjalankan
 * pembersihan otomatis (foto:bersihkan). Owner/Admin mengelola; Finance lihat.
 */
class KelolaPenyimpanan extends Page
{
    protected static string $view = 'filament.pages.kelola-penyimpanan';

    protected static ?string $navigationGroup = 'Manajemen';

    protected static ?string $navigationLabel = 'Penyimpanan';

    protected static ?string $title = 'Penyimpanan';

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $slug = 'penyimpanan';

    public string $cari = '';

    public string $filterFolder = '';

    public string $filterStatus = '';

    public string $urut = 'terbaru';

    public int $halaman = 1;

    /** @var array<int, string> */
    public array $terpilih = [];

    public ?string $pratinjau = null;

    private ?Collection $semuaFile = null;

    public const PER_HALAMAN = 25;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->hasAnyRole([RoleName::Owner->value, RoleName::Admin->value, RoleName::Finance->value]);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function bisaHapus(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->hasAnyRole([RoleName::Owner->value, RoleName::Admin->value]);
    }

    public function updated(string $property): void
    {
        if ($property !== 'halaman' && $property !== 'terpilih' && $property !== 'pratinjau') {
            $this->halaman = 1;
        }
    }

    public function hapusFile(string $path): void
    {
        abort_unless($this->bisaHapus(), 403);

        app(FilePenyimpananService::class)->hapusFile($path);
        $this->semuaFile = null;
        $this->terpilih = array_values(array_diff($this->terpilih, [$path]));

        Notification::make()
            ->title('File dihapus')
            ->body(basename($path))
            ->success()
            ->send();
    }

    public function hapusMassal(): void
    {
        abort_unless($this->bisaHapus(), 403);
        abort_if($this->terpilih === [], 422, 'Tidak ada file yang dipilih.');

        $service = app(FilePenyimpananService::class);
        foreach ($this->terpilih as $path) {
            $service->hapusFile($path);
        }

        $jumlah = count($this->terpilih);
        $this->semuaFile = null;
        $this->terpilih = [];

        Notification::make()
            ->title($jumlah.' file dihapus')
            ->success()
            ->send();
    }

    public function bersihkanSekarang(): void
    {
        abort_unless($this->bisaHapus(), 403);

        Artisan::call('foto:bersihkan');
        $hasil = trim(Artisan::output());

        $this->semuaFile = null;

        Notification::make()
            ->title('Pembersihan foto lama selesai')
            ->body($hasil)
            ->success()
            ->send();
    }

    public function pilihHalamanIni(bool $pilih): void
    {
        $paths = collect($this->fileHalamanIni())->pluck('path')->all();
        $this->terpilih = $pilih
            ? array_values(array_unique(array_merge($this->terpilih, $paths)))
            : array_values(array_diff($this->terpilih, $paths));
    }

    public function setHalaman(int $halaman): void
    {
        $this->halaman = max(1, $halaman);
    }

    public function urlFile(string $path): string
    {
        return asset('storage/'.ltrim($path, '/'));
    }

    protected function getViewData(): array
    {
        $quota = app(StorageQuotaService::class);
        $daftar = $this->daftarFileTersaring();

        $total = $daftar->count();
        $jumlahHalaman = max(1, (int) ceil($total / self::PER_HALAMAN));
        $this->halaman = min(max(1, $this->halaman), $jumlahHalaman);

        $pakai = $quota->pakaiBytes();
        $kuota = $quota->kuotaBytes();
        $persen = min(100.0, $quota->persenTerpakai());

        return [
            'files' => $daftar
                ->forPage($this->halaman, self::PER_HALAMAN)
                ->values()
                ->all(),
            'total' => $total,
            'jumlahHalaman' => $jumlahHalaman,
            'halamanAktif' => $this->halaman,
            'perHalaman' => self::PER_HALAMAN,
            'bisaHapus' => $this->bisaHapus(),
            'pakaiLabel' => StorageQuotaService::formatBytes($pakai),
            'kuotaLabel' => StorageQuotaService::formatBytes($kuota),
            'persen' => $persen,
            'sisaLabel' => StorageQuotaService::formatBytes($quota->sisaBytes()),
            'jumlahFileFoto' => $quota->jumlahFileFoto(),
            'maxUmurHari' => (int) config('penyimpanan.max_umur_hari', 60),
            'penuh' => $pakai >= $kuota,
            'peringatan' => ! ($pakai >= $kuota) && $persen >= (int) config('penyimpanan.peringatan_persen', 90),
            'filterStatusOptions' => $this->opsiStatus(),
            'filterFolderOptions' => [
                'work-reports' => 'Foto laporan',
                'payment-channels' => 'Gambar QRIS',
            ],
            'urutOptions' => [
                'terbaru' => 'Terbaru',
                'terlama' => 'Terlama',
                'terbesar' => 'Ukuran terbesar',
                'terkecil' => 'Ukuran terkecil',
                'nama' => 'Nama A–Z',
            ],
            'statusBadge' => [
                'foto_sebelum' => ['info', 'Foto sebelum'],
                'foto_sesudah' => ['info', 'Foto sesudah'],
                'qris' => ['success', 'QRIS'],
                'yatim' => ['warning', 'Tanpa referensi'],
            ],
        ];
    }

    private function daftarFile(): Collection
    {
        return $this->semuaFile ??= app(FilePenyimpananService::class)->daftarFile();
    }

    private function daftarFileTersaring(): Collection
    {
        $kata = strtolower(trim($this->cari));

        $daftar = $this->daftarFile()
            ->when($kata !== '', fn (Collection $c) => $c->filter(function (array $f) use ($kata): bool {
                return str_contains(strtolower($f['nama'].' '.$f['label'].' '.$f['path']), $kata);
            }))
            ->when($this->filterFolder !== '', fn (Collection $c) => $c->where('folder', $this->filterFolder))
            ->when($this->filterStatus !== '', fn (Collection $c) => $c->where('status', $this->filterStatus));

        $terurut = match ($this->urut) {
            'terlama' => $daftar->sortBy(fn (array $f) => $f['mtime']?->timestamp ?? 0),
            'terbesar' => $daftar->sortByDesc('ukuran'),
            'terkecil' => $daftar->sortBy('ukuran'),
            'nama' => $daftar->sortBy('nama'),
            default => $daftar->sortByDesc(fn (array $f) => $f['mtime']?->timestamp ?? 0),
        };

        return $terurut->values();
    }

    private function fileHalamanIni(): array
    {
        $daftar = $this->daftarFileTersaring();

        return $daftar
            ->forPage($this->halaman, self::PER_HALAMAN)
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function opsiStatus(): array
    {
        return [
            'foto_sebelum' => 'Foto sebelum',
            'foto_sesudah' => 'Foto sesudah',
            'qris' => 'QRIS channel',
            'yatim' => 'Tanpa referensi',
        ];
    }
}
