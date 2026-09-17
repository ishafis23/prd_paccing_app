<?php

use App\Enums\IncentiveKategori;
use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\MotorCleaningResource;
use App\Filament\Resources\MotorCleaningResource\Pages\CreateMotorCleaning;
use App\Livewire\Teknisi\AbsensiScan;
use App\Livewire\Teknisi\OrderDetail;
use App\Models\Attendance;
use App\Models\MotorCleaning;
use App\Models\Order;
use App\Models\TechnicianIncentive;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\AttendanceSettingService;
use App\Services\MotorCleaningService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
    AttendanceSettingService::lupakanCache();

    $this->mkUser = function (string $role): User {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    };

    $this->admin = ($this->mkUser)(RoleName::Admin->value);
    $this->teknisi = ($this->mkUser)(RoleName::Teknisi->value);
});

afterEach(function () {
    Carbon::setTestNow();
});

// --- AttendanceService::catatTitikPertama (Games 2) ------------------------

it('catatTitikPertama menulis ledger games2 bila masih dalam ambang jam', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-17 08:00:00'));

    app(AttendanceService::class)->catatTitikPertama($this->teknisi, UploadedFile::fake()->image('titik.jpg'));

    $entry = TechnicianIncentive::query()->where('kategori', IncentiveKategori::Games2TitikPertama->value)->first();
    expect($entry)->not->toBeNull()
        ->and((float) $entry->nominal)->toBe(7500.0);
});

it('catatTitikPertama TIDAK menulis ledger bila lewat ambang jam, tapi foto tetap tersimpan', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-17 09:00:00'));

    app(AttendanceService::class)->catatTitikPertama($this->teknisi, UploadedFile::fake()->image('titik.jpg'));

    expect(TechnicianIncentive::query()->where('kategori', IncentiveKategori::Games2TitikPertama->value)->exists())->toBeFalse();
    expect(Storage::disk('public')->allFiles('absensi'))->not->toBeEmpty();
});

// --- OrderDetail (Livewire): perluasan slider Check-in utk Games 2 --------

it('checkIn pertama hari itu wajib foto titik pertama — ditolak tanpa foto', function () {
    $order = Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::MenujuLokasi]);

    Livewire::actingAs($this->teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->call('checkIn')
        ->assertHasErrors('fotoTitikPertama');

    expect($order->fresh()->status)->toBe(OrderStatus::MenujuLokasi);
});

it('checkIn pertama hari itu dengan foto -> berhasil & Games 2 tercatat bila tepat waktu', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-17 08:00:00'));
    $order = Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::MenujuLokasi]);

    Livewire::actingAs($this->teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->set('fotoTitikPertama', UploadedFile::fake()->image('titik.jpg'))
        ->call('checkIn')
        ->assertHasNoErrors();

    expect($order->fresh()->status)->toBe(OrderStatus::Dikerjakan)
        ->and(TechnicianIncentive::query()->where('kategori', IncentiveKategori::Games2TitikPertama->value)->exists())->toBeTrue();
});

it('checkIn KEDUA hari itu tidak lagi butuh foto titik pertama', function () {
    Attendance::factory()->create([
        'user_id' => $this->teknisi->id,
        'tanggal' => Carbon::today()->toDateString(),
        'jam_masuk' => now(),
    ]);
    $order = Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::MenujuLokasi]);

    Livewire::actingAs($this->teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->call('checkIn')
        ->assertHasNoErrors();

    expect($order->fresh()->status)->toBe(OrderStatus::Dikerjakan);
});

// --- MotorCleaningService (Games 3) -----------------------------------------

it('catat sendiri (tanpa rekan) membuat MotorCleaning & ledger utk 1 orang', function () {
    $cleaning = app(MotorCleaningService::class)->catat($this->teknisi, UploadedFile::fake()->image('motor.jpg'));

    expect($cleaning->teknisis)->toHaveCount(1)
        ->and(Storage::disk('public')->exists($cleaning->foto))->toBeTrue();

    $entry = TechnicianIncentive::query()->where('user_id', $this->teknisi->id)->where('kategori', IncentiveKategori::Games3CuciMotor->value)->first();
    expect((float) $entry->nominal)->toBe(3000.0);
});

it('catat dengan 1 rekan membuat ledger utk 2 orang', function () {
    $rekan = ($this->mkUser)(RoleName::Teknisi->value);

    $cleaning = app(MotorCleaningService::class)->catat($this->teknisi, UploadedFile::fake()->image('motor.jpg'), [$rekan]);

    expect($cleaning->teknisis)->toHaveCount(2)
        ->and(TechnicianIncentive::query()->where('kategori', IncentiveKategori::Games3CuciMotor->value)->count())->toBe(2)
        ->and((float) TechnicianIncentive::query()->where('user_id', $rekan->id)->first()->nominal)->toBe(3000.0);
});

it('catat menolak lebih dari 1 rekan (maks 2 orang per motor)', function () {
    $rekan1 = ($this->mkUser)(RoleName::Teknisi->value);
    $rekan2 = ($this->mkUser)(RoleName::Teknisi->value);

    app(MotorCleaningService::class)->catat($this->teknisi, UploadedFile::fake()->image('motor.jpg'), [$rekan1, $rekan2]);
})->throws(BusinessRuleException::class, 'Maksimal 2 orang');

it('catat 2x di hari yang sama (pagi & sore) mengakumulasi nominal, bukan menimpa', function () {
    $service = app(MotorCleaningService::class);

    $service->catat($this->teknisi, UploadedFile::fake()->image('pagi.jpg'));
    $service->catat($this->teknisi, UploadedFile::fake()->image('sore.jpg'));

    expect(TechnicianIncentive::query()->where('kategori', IncentiveKategori::Games3CuciMotor->value)->count())->toBe(1);
    expect((float) TechnicianIncentive::first()->nominal)->toBe(6000.0);
    expect(MotorCleaning::query()->count())->toBe(2);
});

it('catat hanya boleh dilakukan role Teknisi', function () {
    app(MotorCleaningService::class)->catat($this->admin, UploadedFile::fake()->image('motor.jpg'));
})->throws(AuthorizationException::class);

// --- Livewire: AbsensiScan -> catatCuciMotor --------------------------------

it('AbsensiScan catatCuciMotor mencatat cuci motor dgn rekan opsional', function () {
    $rekan = ($this->mkUser)(RoleName::Teknisi->value);

    Livewire::actingAs($this->teknisi)
        ->test(AbsensiScan::class)
        ->set('fotoMotor', UploadedFile::fake()->image('motor.jpg'))
        ->set('rekanMotorId', $rekan->id)
        ->call('catatCuciMotor')
        ->assertHasNoErrors();

    $cleaning = MotorCleaning::first();
    expect($cleaning->teknisis)->toHaveCount(2);
});

// --- Filament: MotorCleaningResource -----------------------------------------

it('Cuci Motor resource hanya utk Owner/Admin/HR/Finance, bukan Teknisi', function (string $role, bool $boleh) {
    $user = ($this->mkUser)($role);

    $this->actingAs($user)->get(MotorCleaningResource::getUrl())
        ->{$boleh ? 'assertOk' : 'assertForbidden'}();
})->with([
    'owner' => [RoleName::Owner->value, true],
    'hr' => [RoleName::Hr->value, true],
    'finance' => [RoleName::Finance->value, true],
    'teknisi' => [RoleName::Teknisi->value, false],
]);

it('Admin bisa tambah entri Cuci Motor manual lewat form Filament & ledger tertulis', function () {
    $rekan = ($this->mkUser)(RoleName::Teknisi->value);
    $foto = UploadedFile::fake()->image('motor.jpg');

    Livewire::actingAs($this->admin)
        ->test(CreateMotorCleaning::class)
        ->fillForm([
            'teknisi_ids' => [$this->teknisi->id, $rekan->id],
            'tanggal' => Carbon::today()->toDateString(),
            'foto' => $foto,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(MotorCleaning::query()->count())->toBe(1)
        ->and(TechnicianIncentive::query()->where('kategori', IncentiveKategori::Games3CuciMotor->value)->count())->toBe(2);
});
