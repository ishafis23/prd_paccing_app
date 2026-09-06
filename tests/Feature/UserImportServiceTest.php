<?php

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use App\Services\UserImportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->mkUser = function (string $role): User {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    };

    $this->buatFile = function (array $barisData, string $ekstensi = 'xlsx'): string {
        $path = Storage::disk('local')->path('test-import-'.uniqid().'.'.$ekstensi);

        if ($ekstensi === 'csv') {
            $handle = fopen($path, 'w');
            fputcsv($handle, UserImportService::HEADER);
            foreach ($barisData as $baris) {
                fputcsv($handle, $baris);
            }
            fclose($handle);

            return $path;
        }

        $ss = new Spreadsheet;
        $ss->getActiveSheet()->fromArray(array_merge([UserImportService::HEADER], $barisData), null, 'A1');
        (new Xlsx($ss))->save($path);

        return $path;
    };
});

it('template berisi header & contoh; mengimport template menghasilkan 2 akun (B36)', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $service = app(UserImportService::class);

    $path = Storage::disk('local')->path('test-template-'.uniqid().'.xlsx');
    (new Xlsx($service->template()))->save($path);

    $hasil = $service->import($path, $admin);

    expect($hasil['berhasil'])->toBe(2)
        ->and($hasil['gagal'])->toBe(0)
        ->and(User::where('email', 'budi@example.com')->first()->hasRole(RoleName::Teknisi->value))->toBeTrue()
        ->and(Hash::check(UserImportService::DEFAULT_PASSWORD, User::where('email', 'budi@example.com')->first()->password))->toBeTrue()
        ->and(User::where('email', 'sari@example.com')->first()->hasRole(RoleName::Finance->value))->toBeTrue();
});

it('import xlsx membuat akun dgn password default, status nonaktif, dan no_hp', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $path = ($this->buatFile)([
        ['Andi Pratama', 'andi@example.com', '0811000111', 'teknisi', '', ''],
        ['Citra Dewi', 'citra@example.com', '0811222333', 'admin', 'rahasia123', 'nonaktif'],
    ]);

    $hasil = app(UserImportService::class)->import($path, $admin);

    expect($hasil['berhasil'])->toBe(2);

    $andi = User::where('email', 'andi@example.com')->first();
    expect(Hash::check(UserImportService::DEFAULT_PASSWORD, $andi->password))->toBeTrue()
        ->and($andi->status)->toBe(UserStatus::Aktif)
        ->and($andi->phone)->toBe('0811000111');

    $citra = User::where('email', 'citra@example.com')->first();
    expect(Hash::check('rahasia123', $citra->password))->toBeTrue()
        ->and($citra->status)->toBe(UserStatus::Nonaktif)
        ->and($citra->hasRole(RoleName::Admin->value))->toBeTrue();
});

it('email duplikat di DB dilewati tanpa mengubah akun lama', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $lama = User::factory()->create(['email' => 'lama@example.com', 'name' => 'Nama Lama']);
    $lama->assignRole(RoleName::Finance->value);

    $path = ($this->buatFile)([
        ['Nama Baru', 'lama@example.com', '', 'teknisi', 'password123', ''],
        ['Orang Baru', 'baru@example.com', '', 'teknisi', 'password123', ''],
    ]);

    $hasil = app(UserImportService::class)->import($path, $admin);

    expect($hasil['berhasil'])->toBe(1)
        ->and($hasil['dilewati'])->toBe(1)
        ->and($lama->fresh()->name)->toBe('Nama Lama')
        ->and($lama->fresh()->hasRole(RoleName::Finance->value))->toBeTrue();
});

it('email duplikat dalam satu file: baris kedua dilewati', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $path = ($this->buatFile)([
        ['Dua Kali A', 'dua@example.com', '', 'teknisi', 'password123', ''],
        ['Dua Kali B', 'dua@example.com', '', 'admin', 'password123', ''],
    ]);

    $hasil = app(UserImportService::class)->import($path, $admin);

    expect($hasil['berhasil'])->toBe(1)
        ->and($hasil['dilewati'])->toBe(1)
        ->and(User::where('email', 'dua@example.com')->count())->toBe(1);
});

it('baris ber-role owner oleh Admin dilewati; oleh Owner dibuat', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $path = ($this->buatFile)([
        ['Bos Besar', 'bos@example.com', '', 'owner', 'password123', ''],
    ]);

    $hasilAdmin = app(UserImportService::class)->import($path, $admin);

    expect($hasilAdmin['berhasil'])->toBe(0)
        ->and($hasilAdmin['gagal'])->toBe(1)
        ->and(collect($hasilAdmin['rincian'])->first())->toContain('Owner');

    $owner = ($this->mkUser)(RoleName::Owner->value);
    $hasilOwner = app(UserImportService::class)->import($path, $owner);

    expect($hasilOwner['berhasil'])->toBe(1)
        ->and(User::where('email', 'bos@example.com')->first()->hasRole(RoleName::Owner->value))->toBeTrue();
});

it('baris tidak valid dilewati dengan rincian alasan (B36c)', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $path = ($this->buatFile)([
        ['', 'kosong@example.com', '', 'teknisi', 'password123', ''],
        ['Email Salah', 'bukan-email', '', 'teknisi', 'password123', ''],
        ['Role Salah', 'role@example.com', '', 'ceo', 'password123', ''],
        ['Password Pendek', 'pendek@example.com', '', 'teknisi', 'abc', ''],
        ['Status Salah', 'status@example.com', '', 'teknisi', 'password123', 'aneh'],
    ]);

    $hasil = app(UserImportService::class)->import($path, $admin);

    expect($hasil['berhasil'])->toBe(0)
        ->and($hasil['gagal'])->toBe(5)
        ->and(collect($hasil['rincian'])->join(' '))->toContain('Nama wajib')
        ->and(collect($hasil['rincian'])->join(' '))->toContain('Email tidak valid')
        ->and(collect($hasil['rincian'])->join(' '))->toContain('Role tidak valid')
        ->and(collect($hasil['rincian'])->join(' '))->toContain('minimal 8')
        ->and(collect($hasil['rincian'])->join(' '))->toContain('Status tidak valid');
});

it('menolak file > 200 baris, header salah, dan ekstensi tidak didukung', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $service = app(UserImportService::class);

    $banyak = collect(range(1, 201))->map(fn ($i) => ["User {$i}", "user{$i}@example.com", '', 'teknisi', 'password123', ''])->all();
    $pathBanyak = ($this->buatFile)($banyak);

    expect(fn () => $service->import($pathBanyak, $admin))
        ->toThrow(BusinessRuleException::class, 'Maksimal 200');

    $pathHeader = Storage::disk('local')->path('test-salah-'.uniqid().'.xlsx');
    $ss = new Spreadsheet;
    $ss->getActiveSheet()->fromArray([['kolom-a', 'kolom-b']], null, 'A1');
    (new Xlsx($ss))->save($pathHeader);

    expect(fn () => $service->import($pathHeader, $admin))
        ->toThrow(BusinessRuleException::class, 'Format file tidak dikenali');

    $pathTxt = Storage::disk('local')->path('test-'.uniqid().'.txt');
    file_put_contents($pathTxt, 'apa saja');

    expect(fn () => $service->import($pathTxt, $admin))
        ->toThrow(BusinessRuleException::class, '.xlsx atau .csv');
});

it('hanya Admin/Owner yang boleh import', function (string $role) {
    $user = ($this->mkUser)($role);
    $path = ($this->buatFile)([['User X', 'userx@example.com', '', 'teknisi', 'password123', '']]);

    app(UserImportService::class)->import($path, $user);
})->with(['finance' => RoleName::Finance->value, 'teknisi' => RoleName::Teknisi->value])
    ->throws(AuthorizationException::class);

it('import file csv UTF-8 berhasil', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $path = ($this->buatFile)([
        ['Csv Person', 'csv@example.com', '0813000111', 'hr', 'password123', ''],
    ], 'csv');

    $hasil = app(UserImportService::class)->import($path, $admin);

    expect($hasil['berhasil'])->toBe(1)
        ->and(User::where('email', 'csv@example.com')->first()->hasRole(RoleName::Hr->value))->toBeTrue();
});

it('halaman daftar pengguna menampilkan tombol Import Excel & Unduh Template utk admin', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->assertOk()
        ->assertSee('Import Excel')
        ->assertSee('Unduh Template');
});
