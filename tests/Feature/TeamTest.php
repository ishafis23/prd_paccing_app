<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\TeamResource\Pages\CreateTeam;
use App\Filament\Resources\TeamResource\Pages\EditTeam;
use App\Models\Order;
use App\Models\Team;
use App\Models\User;
use App\Services\OrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->mkAdmin = function (): User {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    };

    $this->mkTeknisi = function (): User {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Teknisi->value);

        return $user;
    };

    $this->mkTeam = function (array $teknisiIds, array $ekstra = []): Team {
        $team = Team::factory()->create(array_merge(['pic_teknisi_id' => $teknisiIds[0]], $ekstra));
        $team->members()->sync($teknisiIds);

        return $team;
    };
});

it('assignTeam menugaskan PIC + seluruh anggota tim ke order & mencatat team_id', function () {
    $admin = ($this->mkAdmin)();
    $pic = ($this->mkTeknisi)();
    $anggota = ($this->mkTeknisi)();
    $team = ($this->mkTeam)([$pic->id, $anggota->id]);
    $order = Order::factory()->create(['status' => OrderStatus::Baru, 'teknisi_id' => null]);

    $hasil = app(OrderService::class)->assignTeam($order, $team, $admin);

    expect($hasil->teknisi_id)->toBe($pic->id);
    expect($hasil->status)->toBe(OrderStatus::Terjadwal);
    expect($hasil->team_id)->toBe($team->id);
    expect($hasil->orderTechnicians()->pluck('teknisi_id')->all())->toContain($pic->id, $anggota->id);
});

it('assignTeam menolak tim nonaktif', function () {
    $admin = ($this->mkAdmin)();
    $pic = ($this->mkTeknisi)();
    $team = ($this->mkTeam)([$pic->id], ['aktif' => false]);
    $order = Order::factory()->create(['status' => OrderStatus::Baru, 'teknisi_id' => null]);

    app(OrderService::class)->assignTeam($order, $team, $admin);
})->throws(BusinessRuleException::class, 'nonaktif');

it('assignTeam menolak tim tanpa anggota', function () {
    $admin = ($this->mkAdmin)();
    $team = Team::factory()->create();
    $order = Order::factory()->create(['status' => OrderStatus::Baru, 'teknisi_id' => null]);

    app(OrderService::class)->assignTeam($order, $team, $admin);
})->throws(BusinessRuleException::class, 'belum punya anggota');

it('assignTeam menolak bukan admin/owner', function () {
    $pic = ($this->mkTeknisi)();
    $team = ($this->mkTeam)([$pic->id]);
    $order = Order::factory()->create(['status' => OrderStatus::Baru, 'teknisi_id' => null]);

    app(OrderService::class)->assignTeam($order, $team, $pic);
})->throws(AuthorizationException::class);

it('Filament: create Team menyimpan pic_teknisi_id dari anggota pertama & sync team_members', function () {
    $admin = ($this->mkAdmin)();
    $t1 = ($this->mkTeknisi)();
    $t2 = ($this->mkTeknisi)();

    Livewire::actingAs($admin)
        ->test(CreateTeam::class)
        ->fillForm([
            'nama' => 'Tim Alpha',
            'teknisi_ids' => [$t1->id, $t2->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $team = Team::where('nama', 'Tim Alpha')->first();
    expect($team)->not->toBeNull();
    expect($team->pic_teknisi_id)->toBe($t1->id);
    expect($team->members->pluck('id')->all())->toContain($t1->id, $t2->id);
});

it('Filament: edit Team mengganti anggota & PIC baru', function () {
    $admin = ($this->mkAdmin)();
    $t1 = ($this->mkTeknisi)();
    $t2 = ($this->mkTeknisi)();
    $t3 = ($this->mkTeknisi)();
    $team = ($this->mkTeam)([$t1->id, $t2->id]);

    Livewire::actingAs($admin)
        ->test(EditTeam::class, ['record' => $team->id])
        ->fillForm([
            'teknisi_ids' => [$t3->id, $t2->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $fresh = $team->fresh();
    expect($fresh->pic_teknisi_id)->toBe($t3->id);
    expect($fresh->members->pluck('id')->all())->toContain($t3->id, $t2->id);
    expect($fresh->members->pluck('id')->all())->not->toContain($t1->id);
});

it('aksi Assign Tim hanya muncul saat order belum punya PIC', function () {
    $admin = ($this->mkAdmin)();
    $pic = ($this->mkTeknisi)();
    $belumAdaPic = Order::factory()->create(['teknisi_id' => null, 'status' => OrderStatus::Baru]);
    $sudahAdaPic = Order::factory()->create(['teknisi_id' => $pic->id, 'status' => OrderStatus::Dikerjakan]);

    Livewire::actingAs($admin)
        ->test(ListOrders::class)
        ->assertTableActionVisible('assignTim', $belumAdaPic)
        ->assertTableActionHidden('assignTim', $sudahAdaPic);
});

it('aksi Assign Tim via admin table menugaskan tim ke order', function () {
    $admin = ($this->mkAdmin)();
    $pic = ($this->mkTeknisi)();
    $anggota = ($this->mkTeknisi)();
    $team = ($this->mkTeam)([$pic->id, $anggota->id], ['nama' => 'Tim Beta']);
    $order = Order::factory()->create(['teknisi_id' => null, 'status' => OrderStatus::Baru]);

    Livewire::actingAs($admin)
        ->test(ListOrders::class)
        ->callTableAction('assignTim', $order, data: ['team_id' => $team->id])
        ->assertNotified();

    $fresh = $order->fresh();
    expect($fresh->teknisi_id)->toBe($pic->id);
    expect($fresh->team_id)->toBe($team->id);
});
