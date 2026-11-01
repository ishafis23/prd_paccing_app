<?php

use App\Enums\RoleName;
use App\Livewire\Teknisi\LaporanPengeluaran;
use App\Models\DailyAttendance;
use App\Models\TeknisiExpense;
use App\Models\User;
use App\Services\SyaratAdministrasiService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->mk = function (): User {
        $u = User::factory()->create();
        $u->assignRole(RoleName::Teknisi->value);

        return $u;
    };
    $this->absen = fn (User $u, string $tgl) => DailyAttendance::create([
        'user_id' => $u->id, 'tanggal' => $tgl, 'jam_datang' => $tgl.' 08:00:00',
    ]);
});

it('hari absen tanpa catatan pengeluaran memblokir; hari ini tidak dihitung', function () {
    $t = ($this->mk)();
    ($this->absen)($t, today()->subDay()->toDateString());
    ($this->absen)($t, today()->toDateString());

    $p = collect(app(SyaratAdministrasiService::class)->penghalang($t))->where('jenis', 'pengeluaran');

    expect($p)->toHaveCount(1)
        ->and($p->first()['pesan'])->toContain(today()->subDay()->format('d M Y'));
});

it('pengeluaran sendiri maupun sebagai partner menuntaskan syarat harian keduanya', function () {
    $a = ($this->mk)();
    $b = ($this->mk)();
    $kemarin = today()->subDay()->toDateString();
    ($this->absen)($a, $kemarin);
    ($this->absen)($b, $kemarin);

    TeknisiExpense::create([
        'teknisi_id' => $a->id, 'partner_id' => $b->id, 'tanggal_input' => $kemarin,
        'kategori' => 'bensin', 'qty' => 1, 'harga' => 20000, 'nominal' => 20000, 'status' => 'pending',
    ]);

    $svc = app(SyaratAdministrasiService::class);
    expect(collect($svc->penghalang($a))->where('judul', 'Catatan pengeluaran harian belum diisi'))->toBeEmpty()
        ->and(collect($svc->penghalang($b))->where('judul', 'Catatan pengeluaran harian belum diisi'))->toBeEmpty();
});

it('form menyimpan partner, partner melihatnya tapi tidak bisa menghapus; diri sendiri/non-teknisi ditolak', function () {
    $a = ($this->mk)();
    $b = ($this->mk)();
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);

    Livewire::actingAs($a)->test(LaporanPengeluaran::class)
        ->set('kategori', 'bensin')->set('qty', 1)->set('harga', 20000)->set('nominal', 20000)
        ->set('partner_id', $a->id)->call('submitPengeluaran')->assertHasErrors('partner_id');

    Livewire::actingAs($a)->test(LaporanPengeluaran::class)
        ->set('kategori', 'bensin')->set('qty', 1)->set('harga', 20000)->set('nominal', 20000)
        ->set('partner_id', $admin->id)->call('submitPengeluaran')->assertHasErrors('partner_id');

    Livewire::actingAs($a)->test(LaporanPengeluaran::class)
        ->set('kategori', 'bensin')->set('qty', 1)->set('harga', 20000)->set('nominal', 20000)
        ->set('partner_id', $b->id)->call('submitPengeluaran')->assertHasNoErrors();

    $e = TeknisiExpense::first();
    expect($e->partner_id)->toBe($b->id);

    Livewire::actingAs($b)->test(LaporanPengeluaran::class)
        ->assertSee('Dicatat oleh '.$a->name)
        ->call('deletePengeluaran', $e);
    expect(TeknisiExpense::count())->toBe(1);
});

it('filter riwayat pengeluaran bisa per hari/tanggal selain per bulan', function () {
    $a = ($this->mk)();
    $mk = fn (string $tgl, string $ket) => TeknisiExpense::create([
        'teknisi_id' => $a->id, 'tanggal_input' => $tgl, 'kategori' => 'bensin',
        'qty' => 1, 'harga' => 10000, 'nominal' => 10000, 'keterangan' => $ket, 'status' => 'pending',
    ]);
    $mk('2026-10-10', 'catatan-sepuluh');
    $mk('2026-10-20', 'catatan-duapuluh');

    $c = Livewire::actingAs($a)->test(LaporanPengeluaran::class)
        ->set('filterBulan', '2026-10')
        ->assertSee('catatan-sepuluh')->assertSee('catatan-duapuluh')
        ->set('modeFilter', 'harian')->set('filterTanggal', '2026-10-10')
        ->assertSee('catatan-sepuluh')->assertDontSee('catatan-duapuluh')
        ->assertSee('(2026-10-10)');
});
