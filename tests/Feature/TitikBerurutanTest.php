<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\Titik;
use App\Models\User;
use App\Services\TeknisiService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->tek = tap(User::factory()->create(), fn ($u) => $u->assignRole(RoleName::Teknisi->value));
    $this->t1 = Titik::create(['nama' => 'Titik 1', 'jam' => '08:15', 'urutan' => 1, 'aktif' => true]);
    $this->t2 = Titik::create(['nama' => 'Titik 2', 'jam' => '10:00', 'urutan' => 2, 'aktif' => true]);
    $this->t3 = Titik::create(['nama' => 'Titik 3', 'jam' => '13:00', 'urutan' => 3, 'aktif' => true]);
    $this->mk = fn (Titik $t, OrderStatus $s = OrderStatus::Terjadwal): Order => Order::factory()->create([
        'teknisi_id' => $this->tek->id, 'titik_id' => $t->id, 'status' => $s, 'tanggal_jadwal' => today(), 'is_klaim' => false,
    ]);
    $this->svc = app(TeknisiService::class);
});

it('titik berikutnya terkunci selama titik sebelumnya belum dikerjakan', function () {
    $o1 = ($this->mk)($this->t1);
    $o2 = ($this->mk)($this->t2);
    $o3 = ($this->mk)($this->t3);

    expect($this->svc->titikPenghalang($o1, $this->tek))->toBeNull()
        ->and($this->svc->titikPenghalang($o2, $this->tek)?->id)->toBe($o1->id)
        ->and($this->svc->titikPenghalang($o3, $this->tek)?->id)->toBe($o1->id);

    expect(fn () => $this->svc->berangkat($o2, $this->tek))->toThrow(BusinessRuleException::class, 'Titik 1');
});

it('titik terbuka setelah titik sebelumnya selesai atau terkendala (besok)', function () {
    $o1 = ($this->mk)($this->t1, OrderStatus::Selesai);
    $o2 = ($this->mk)($this->t2, OrderStatus::Terkendala);
    $o3 = ($this->mk)($this->t3);

    expect($this->svc->titikPenghalang($o3, $this->tek))->toBeNull();
});

it('kendala pending sore: status tetap aktif, titik berikutnya terbuka, order tetap bisa dikerjakan', function () {
    $o1 = ($this->mk)($this->t1);
    $o2 = ($this->mk)($this->t2);

    $this->svc->tandaiKendala($o1, $this->tek, 'AC belum bisa diakses', 'sore');

    $o1 = $o1->fresh();
    expect($o1->status)->toBe(OrderStatus::Terjadwal)->and($o1->jenis_kendala)->toBe('sore');
    expect($this->svc->titikPenghalang($o2, $this->tek))->toBeNull();      // titik 2 terbuka
    expect($this->svc->titikPenghalang($o1, $this->tek))->toBeNull();      // titik 1 tetap bisa dikerjakan
});

it('kendala pending besok: status terkendala dan titik berikutnya terbuka', function () {
    $o1 = ($this->mk)($this->t1);
    $o2 = ($this->mk)($this->t2);

    $this->svc->tandaiKendala($o1, $this->tek, 'Customer tidak ada', 'besok');

    expect($o1->fresh()->status)->toBe(OrderStatus::Terkendala)->and($o1->fresh()->jenis_kendala)->toBe('besok');
    expect($this->svc->titikPenghalang($o2, $this->tek))->toBeNull();
});

it('jenis kendala wajib valid', function () {
    $o1 = ($this->mk)($this->t1);

    expect(fn () => $this->svc->tandaiKendala($o1, $this->tek, 'x', ''))->toThrow(BusinessRuleException::class);
    expect(fn () => $this->svc->tandaiKendala($o1, $this->tek, 'x', 'lusa'))->toThrow(BusinessRuleException::class);
});

it('halaman order yang terkunci diarahkan kembali ke jadwal', function () {
    ($this->mk)($this->t1);
    $o2 = ($this->mk)($this->t2);

    \Livewire\Livewire::actingAs($this->tek)->test(\App\Livewire\Teknisi\OrderDetail::class, ['order' => $o2])
        ->assertRedirect();
});

it('jadwal menandai order yang terkunci', function () {
    ($this->mk)($this->t1);
    $o2 = ($this->mk)($this->t2);

    $c = \Livewire\Livewire::actingAs($this->tek)->test(\App\Livewire\Teknisi\JadwalHariIni::class);
    $c->assertSee('Terkunci');
});
