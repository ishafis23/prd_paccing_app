<?php

use App\Enums\RoleName;
use App\Models\DailyAttendance;
use App\Models\TeknisiExpense;
use App\Models\User;
use App\Services\SyaratAdministrasiService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->mk = fn (): User => tap(User::factory()->create(), fn ($u) => $u->assignRole(RoleName::Teknisi->value));
    $this->body = fn (array $ubah = []): array => $ubah + [
        'tanggal_input' => today()->toDateString(), 'kategori' => 'bensin', 'qty' => 1, 'harga' => 20000, 'nominal' => 20000,
    ];
});

it('catat, ubah, hapus pengeluaran sendiri', function () {
    $a = ($this->mk)();
    Sanctum::actingAs($a);

    $id = $this->postJson('/api/v1/pengeluaran', ($this->body)(['keterangan' => 'Bensin']))
        ->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.nominal', 20000)->json('data.id');

    $this->putJson("/api/v1/pengeluaran/{$id}", ($this->body)(['harga' => 25000, 'nominal' => 25000]))
        ->assertOk()->assertJsonPath('data.nominal', 25000)->assertJsonPath('data.perlu_acc_ulang', false);

    $this->deleteJson("/api/v1/pengeluaran/{$id}")->assertOk();
    expect(TeknisiExpense::count())->toBe(0);
});

it('partner: dicatat satu orang, terlihat partner, tidak bisa diubah/dihapus partner; diri sendiri ditolak', function () {
    $a = ($this->mk)();
    $b = ($this->mk)();

    Sanctum::actingAs($a);
    $id = $this->postJson('/api/v1/pengeluaran', ($this->body)(['partner_id' => $b->id]))->assertCreated()->json('data.id');
    $this->postJson('/api/v1/pengeluaran', ($this->body)(['partner_id' => $a->id]))->assertStatus(422)->assertJsonPath('code', 'validasi');

    Sanctum::actingAs($b);
    $this->getJson('/api/v1/pengeluaran')->assertOk()
        ->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.sebagai_partner', true)
        ->assertJsonPath('data.0.bisa_ubah', false)->assertJsonPath('data.0.dicatat_oleh.id', $a->id);
    $this->putJson("/api/v1/pengeluaran/{$id}", ($this->body)())->assertStatus(404);
    $this->deleteJson("/api/v1/pengeluaran/{$id}")->assertStatus(404);
});

it('ubah nominal pada yang disetujui mengembalikan ke menunggu; ubah partner saja tidak', function () {
    $a = ($this->mk)();
    $b = ($this->mk)();
    Sanctum::actingAs($a);
    $e = TeknisiExpense::create(($this->body)() + ['teknisi_id' => $a->id, 'status' => 'approved']);

    $this->putJson("/api/v1/pengeluaran/{$e->id}", ($this->body)(['partner_id' => $b->id]))
        ->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.perlu_acc_ulang', false);

    $this->putJson("/api/v1/pengeluaran/{$e->id}", ($this->body)(['partner_id' => $b->id, 'harga' => 30000, 'nominal' => 30000]))
        ->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.perlu_acc_ulang', true);
});

it('pengeluaran disetujui tidak bisa dihapus; validasi nominal & tanggal', function () {
    $a = ($this->mk)();
    Sanctum::actingAs($a);
    $e = TeknisiExpense::create(($this->body)() + ['teknisi_id' => $a->id, 'status' => 'approved']);

    $this->deleteJson("/api/v1/pengeluaran/{$e->id}")->assertStatus(422)->assertJsonPath('code', 'aturan_bisnis');
    $this->postJson('/api/v1/pengeluaran', ($this->body)(['nominal' => 500]))->assertStatus(422);
    $this->postJson('/api/v1/pengeluaran', ($this->body)(['tanggal_input' => today()->addDay()->toDateString()]))->assertStatus(422);
});

it('filter harian vs bulanan, ringkasan & breakdown', function () {
    $a = ($this->mk)();
    Sanctum::actingAs($a);
    $kemarin = today()->subDay();
    TeknisiExpense::create(($this->body)(['tanggal_input' => today()->toDateString()]) + ['teknisi_id' => $a->id, 'status' => 'pending']);
    TeknisiExpense::create(($this->body)(['tanggal_input' => $kemarin->toDateString(), 'kategori' => 'makan']) + ['teknisi_id' => $a->id, 'status' => 'approved']);

    $this->getJson('/api/v1/pengeluaran?mode=harian&tanggal='.today()->toDateString())
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('ringkasan.hari_ini.total_pending', 20000)
        ->assertJsonPath('breakdown.bensin.total', 20000)->assertJsonPath('breakdown.makan.total', 0);

    $res = $this->getJson('/api/v1/pengeluaran?mode=bulanan&bulan='.$kemarin->format('Y-m'))->assertOk();
    expect($res->json('data'))->not->toBeEmpty();
});

it('pengeluaran lewat API menuntaskan syarat administrasi harian', function () {
    $a = ($this->mk)();
    Sanctum::actingAs($a);
    $kemarin = today()->subDay()->toDateString();
    DailyAttendance::create(['user_id' => $a->id, 'tanggal' => $kemarin, 'jam_datang' => $kemarin.' 08:00:00']);

    $this->getJson('/api/v1/administrasi')->assertOk()
        ->assertJsonPath('data.tertahan', true)->assertJsonPath('data.penghalang.0.aksi.layar', 'pengeluaran');

    $this->postJson('/api/v1/pengeluaran', ($this->body)(['tanggal_input' => $kemarin]))->assertCreated();

    expect(collect(app(SyaratAdministrasiService::class)->penghalang($a))->where('jenis', 'pengeluaran'))->toBeEmpty();
    $this->getJson('/api/v1/administrasi')->assertJsonPath('data.tertahan', false);
});

it('opsi form memuat kategori, order & calon partner (bukan diri sendiri)', function () {
    $a = ($this->mk)();
    $b = ($this->mk)();
    Sanctum::actingAs($a);

    $res = $this->getJson('/api/v1/pengeluaran/opsi')->assertOk();
    expect(collect($res->json('data.partner'))->pluck('id')->all())->toBe([$b->id])
        ->and(collect($res->json('data.kategori'))->pluck('kode')->all())->toContain('bensin');
});
