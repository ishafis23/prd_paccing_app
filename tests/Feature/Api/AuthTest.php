<?php

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\DeviceToken;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->mk = function (string $role = 'teknisi', array $attr = []): User {
        $u = User::factory()->create($attr + ['password' => 'rahasia123']);
        $u->assignRole($role);

        return $u;
    };
});

it('login teknisi mengembalikan token & profil; token dipakai untuk /me', function () {
    $t = ($this->mk)();

    $res = $this->postJson('/api/v1/login', ['email' => $t->email, 'password' => 'rahasia123', 'device_name' => 'Redmi'])
        ->assertOk()->assertJsonPath('data.user.id', $t->id);

    $token = $res->json('data.token');
    expect($token)->not->toBeEmpty();

    $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.user.email', $t->email)
        ->assertJsonStructure(['data' => ['usaha' => ['nama'], 'min_app_version', 'waktu_server']]);
});

it('password salah 422, role non-teknisi 403, akun nonaktif 403', function () {
    $t = ($this->mk)();
    $this->postJson('/api/v1/login', ['email' => $t->email, 'password' => 'salah', 'device_name' => 'x'])
        ->assertStatus(422)->assertJsonPath('code', 'validasi');

    $admin = ($this->mk)(RoleName::Admin->value);
    $this->postJson('/api/v1/login', ['email' => $admin->email, 'password' => 'rahasia123', 'device_name' => 'x'])
        ->assertStatus(403)->assertJsonPath('code', 'bukan_teknisi');

    $off = ($this->mk)('teknisi', ['status' => UserStatus::Nonaktif]);
    $this->postJson('/api/v1/login', ['email' => $off->email, 'password' => 'rahasia123', 'device_name' => 'x'])
        ->assertStatus(403)->assertJsonPath('code', 'akun_nonaktif');
});

it('tanpa token 401 berbentuk JSON; versi app lama 426', function () {
    $this->getJson('/api/v1/me')->assertStatus(401)->assertJsonPath('code', 'tidak_terautentikasi');

    config(['api.min_app_version' => '1.2.0']);
    $this->withHeader('X-App-Version', '1.0.0')->getJson('/api/v1/me')
        ->assertStatus(426)->assertJsonPath('code', 'upgrade_required');
});

it('token user non-teknisi ditolak 403 dan akun nonaktif 401 di endpoint teknisi', function () {
    $admin = ($this->mk)(RoleName::Admin->value);
    Sanctum::actingAs($admin);
    $this->getJson('/api/v1/me')->assertStatus(403);

    $t = ($this->mk)();
    $t->update(['status' => UserStatus::Nonaktif]);
    Sanctum::actingAs($t);
    $this->getJson('/api/v1/me')->assertStatus(401);
});

it('logout mencabut token perangkat ini dan token push', function () {
    $t = ($this->mk)();
    $token = $t->createToken('hp')->plainTextToken;
    $h = ['Authorization' => 'Bearer '.$token];

    $this->withHeaders($h)->postJson('/api/v1/perangkat', ['token' => 'ExponentPushToken[abc]'])->assertCreated();
    expect(DeviceToken::where('user_id', $t->id)->count())->toBe(1);

    $this->withHeaders($h)->postJson('/api/v1/logout', ['device_token' => 'ExponentPushToken[abc]'])->assertOk();

    expect($t->tokens()->count())->toBe(0)->and(DeviceToken::count())->toBe(0);
});
