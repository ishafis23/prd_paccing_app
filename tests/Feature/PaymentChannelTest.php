<?php

use App\Enums\PaymentChannelType;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\PaymentChannel;
use App\Models\User;
use App\Services\PaymentChannelService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->service = new PaymentChannelService;
});

function pcUserBerperan(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function pcChannelQris(User $by, array $overrides = []): PaymentChannel
{
    return app(PaymentChannelService::class)->create(array_merge([
        'nama' => 'QRIS Paccing',
        'jenis' => PaymentChannelType::Qris->value,
        'atas_nama' => 'Paccing Official',
    ], $overrides), $by);
}

it('admin dapat membuat channel qris dengan default aktif', function () {
    $admin = pcUserBerperan(RoleName::Admin->value);

    $channel = pcChannelQris($admin);

    expect($channel->nama)->toBe('QRIS Paccing')
        ->and($channel->jenis)->toBe(PaymentChannelType::Qris)
        ->and($channel->aktif)->toBeTrue()
        ->and($channel->dicatat_oleh)->toBe($admin->id)
        ->and(PaymentChannel::query()->count())->toBe(1);
});

it('channel bank wajib nomor rekening dan nama bank', function () {
    $admin = pcUserBerperan(RoleName::Admin->value);

    $this->service->create([
        'nama' => 'BCA 8123',
        'jenis' => PaymentChannelType::Bank->value,
    ], $admin);
})->throws(BusinessRuleException::class, 'Nomor rekening');

it('channel tanpa nama ditolak', function () {
    $admin = pcUserBerperan(RoleName::Admin->value);

    $this->service->create([
        'nama' => '',
        'jenis' => PaymentChannelType::Qris->value,
    ], $admin);
})->throws(BusinessRuleException::class, 'Nama channel');

it('finance dan teknisi tidak boleh membuat channel', function () {
    $finance = pcUserBerperan(RoleName::Finance->value);
    $teknisi = pcUserBerperan(RoleName::Teknisi->value);

    expect(fn () => pcChannelQris($finance))->toThrow(AuthorizationException::class);
    expect(fn () => pcChannelQris($teknisi))->toThrow(AuthorizationException::class);
    expect(PaymentChannel::query()->count())->toBe(0);
});

it('owner dapat mengubah dan menonaktifkan channel', function () {
    $owner = pcUserBerperan(RoleName::Owner->value);
    $channel = pcChannelQris($owner);

    $diubah = $this->service->update($channel, [
        'nama' => 'QRIS Baru',
        'jenis' => PaymentChannelType::Qris->value,
        'atas_nama' => 'Paccing',
    ], $owner);

    expect($diubah->nama)->toBe('QRIS Baru');

    $nonaktif = $this->service->setAktif($diubah, false, $owner);

    expect($nonaktif->aktif)->toBeFalse()
        ->and($this->service->daftarAktif())->toHaveCount(0);
});

it('daftarAktif hanya mengembalikan channel aktif', function () {
    $admin = pcUserBerperan(RoleName::Admin->value);
    pcChannelQris($admin);
    pcChannelQris($admin, ['nama' => 'QRIS Nonaktif', 'aktif' => false]);

    $aktif = $this->service->daftarAktif();

    expect($aktif)->toHaveCount(1)
        ->and($aktif->first()->nama)->toBe('QRIS Paccing');
});

it('admin dapat menghapus channel', function () {
    $admin = pcUserBerperan(RoleName::Admin->value);
    $channel = pcChannelQris($admin);

    $this->service->hapus($channel, $admin);

    expect(PaymentChannel::query()->count())->toBe(0);
});

it('teknisi tidak boleh menghapus channel', function () {
    $admin = pcUserBerperan(RoleName::Admin->value);
    $teknisi = pcUserBerperan(RoleName::Teknisi->value);
    $channel = pcChannelQris($admin);

    expect(fn () => $this->service->hapus($channel, $teknisi))
        ->toThrow(AuthorizationException::class);
});
