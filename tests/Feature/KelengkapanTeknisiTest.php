<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Filament\Pages\KelengkapanTeknisi;
use App\Models\DailyAttendance;
use App\Models\Order;
use App\Models\TechnicianOffDay;
use App\Models\TeknisiExpense;
use App\Models\User;
use App\Services\KelengkapanHarianService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->kemarin = CarbonImmutable::yesterday();
    $this->tgl = $this->kemarin->toDateString();
    $this->teknisi = tap(User::factory()->create(['name' => 'Budi']), fn ($u) => $u->assignRole(RoleName::Teknisi->value));

    $this->absen = fn (User $u, bool $pulang = true) => DailyAttendance::create([
        'user_id' => $u->id, 'tanggal' => $this->tgl,
        'jam_datang' => $this->kemarin->setTime(8, 0), 'jam_pulang' => $pulang ? $this->kemarin->setTime(17, 0) : null,
    ]);
    $this->pengeluaran = fn (User $u, array $x = []) => TeknisiExpense::create($x + [
        'teknisi_id' => $u->id, 'tanggal_input' => $this->tgl, 'kategori' => 'bensin',
        'nominal' => 20000, 'qty' => 1, 'harga' => 20000, 'status' => 'approved', 'foto_path' => 'bukti/a.jpg',
    ]);
    $this->baris = fn () => app(KelengkapanHarianService::class)->untukTanggal($this->kemarin)
        ->firstWhere(fn ($r) => $r['teknisi']->id === $this->teknisi->id);
});

it('teknisi tanpa absen, dana, dll ditandai kurang', function () {
    $r = ($this->baris)();

    expect($r['lengkap'])->toBeFalse()
        ->and($r['absensi']['status'])->toBe('kurang')
        ->and($r['dana']['status'])->toBe('kurang')
        ->and($r['dokumentasi']['status'])->toBe('na')
        ->and($r['setoran']['status'])->toBe('na');
});

it('absen datang & pulang + pengeluaran berfoto tanpa order = lengkap', function () {
    ($this->absen)($this->teknisi);
    ($this->pengeluaran)($this->teknisi);

    $r = ($this->baris)();

    expect($r['absensi']['status'])->toBe('ok')->and($r['dana']['status'])->toBe('ok')->and($r['lengkap'])->toBeTrue();
});

it('belum absen pulang, pengeluaran tanpa foto / ditolak = kurang', function () {
    ($this->absen)($this->teknisi, pulang: false);
    ($this->pengeluaran)($this->teknisi, ['foto_path' => null]);

    $r = ($this->baris)();
    expect($r['absensi']['status'])->toBe('kurang')->and($r['absensi']['catatan'])->toBe('Belum absen pulang')
        ->and($r['dana']['status'])->toBe('kurang')->and($r['dana']['tanpa_foto'])->toBe(1);
});

it('hari OFF tidak menuntut absensi & dana', function () {
    TechnicianOffDay::create(['user_id' => $this->teknisi->id, 'tanggal' => $this->tgl]);

    $r = ($this->baris)();
    expect($r['libur'])->toBeTrue()->and($r['absensi']['status'])->toBe('na')->and($r['dana']['status'])->toBe('na')
        ->and($r['lengkap'])->toBeTrue();
});

it('order tunai tanpa laporan setoran = kurang', function () {
    ($this->absen)($this->teknisi);
    ($this->pengeluaran)($this->teknisi);
    Order::factory()->create([
        'teknisi_id' => $this->teknisi->id, 'tanggal_jadwal' => $this->tgl, 'status' => OrderStatus::Selesai,
        'metode_dipilih' => PaymentMethod::Cash, 'metode_dipilih_pada' => $this->kemarin,
    ]);

    $r = ($this->baris)();
    expect($r['setoran']['status'])->toBe('kurang')->and($r['setoran']['wajib'])->toBe(1)->and($r['setoran']['tersetor'])->toBe(0)
        ->and($r['lengkap'])->toBeFalse();
});

it('order yang belum diselesaikan di hari lampau membuat dokumentasi kurang', function () {
    Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'tanggal_jadwal' => $this->tgl, 'status' => OrderStatus::Terjadwal]);

    expect(($this->baris)()['dokumentasi']['status'])->toBe('kurang');
});

it('halaman bisa dibuka Owner/Admin/Finance, ditolak untuk teknisi', function () {
    $admin = tap(User::factory()->create(), fn ($u) => $u->assignRole(RoleName::Admin->value));

    Livewire::actingAs($admin)->test(KelengkapanTeknisi::class)
        ->set('tanggal', $this->tgl)->assertSee('Budi')->assertSee('Tahan dulu');

    Livewire::actingAs($admin)->test(KelengkapanTeknisi::class)
        ->set('tanggal', now()->addDay()->toDateString())->assertSet('tanggal', now()->toDateString());

    Livewire::actingAs($this->teknisi)->test(KelengkapanTeknisi::class)->assertForbidden();
});
