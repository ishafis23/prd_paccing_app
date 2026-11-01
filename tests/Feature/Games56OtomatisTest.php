<?php

use App\Enums\IncentiveKategori;
use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Models\Order;
use App\Models\OrderTechnician;
use App\Models\TechnicianIncentive;
use App\Models\User;
use App\Services\TechnicianIncentiveService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->mk = fn (): User => tap(User::factory()->create(), fn ($u) => $u->assignRole(RoleName::Teknisi->value));
    $this->order = function (User $pic, ?User $rekan, int $harga, int $unit, string $tgl = '2026-10-26') {
        $o = Order::factory()->create(['teknisi_id' => $pic->id, 'status' => OrderStatus::Selesai, 'tanggal_jadwal' => $tgl, 'is_klaim' => false]);
        $o->orderItems()->delete();
        $o->orderItems()->create(['nama_layanan' => 'Cuci AC', 'kategori' => \App\Enums\ServiceType::CuciAc, 'komponen' => \App\Enums\IncomeCategory::Jasa, 'harga' => $harga, 'jumlah' => $unit]);
        if ($rekan) {
            OrderTechnician::create(['order_id' => $o->id, 'teknisi_id' => $rekan->id]);
        }

        return $o->fresh();
    };
});

it('berdua: omset >= 850rb dapat Game 5 untuk keduanya, 7 unit < 12 tidak dapat Game 6 (kasus client 26 Okt)', function () {
    $a = ($this->mk)();
    $b = ($this->mk)();
    ($this->order)($a, $b, 70000, 1);
    ($this->order)($a, $b, 100000, 2);   // total unit 3
    ($this->order)($a, $b, 85000, 3);    // 6
    ($this->order)($a, $b, 550000, 1);   // 7 unit, omset jasa 70k+200k+255k+550k = 1.075.000

    $tgl = Carbon::parse('2026-10-26');
    $r = app(TechnicianIncentiveService::class)->ringkasanGames56($a, $tgl);
    expect($r['omset'])->toBe(1075000.0)->and($r['unit'])->toBe(7)->and($r['mode'])->toBe('berdua')
        ->and($r['dapat_games5'])->toBeTrue()->and($r['dapat_games6'])->toBeFalse();

    foreach ([$a, $b] as $u) {
        $g5 = TechnicianIncentive::where('user_id', $u->id)->where('kategori', IncentiveKategori::Games5OmsetTim->value)->first();
        expect($g5)->not->toBeNull()->and((float) $g5->nominal)->toBe(40000.0);
        expect(TechnicianIncentive::where('user_id', $u->id)->where('kategori', IncentiveKategori::Games6Unit->value)->exists())->toBeFalse();
    }
});

it('sendiri: ambang & nominal sendiri; entri otomatis dicabut saat tidak lagi tercapai', function () {
    $a = ($this->mk)();
    $o = ($this->order)($a, null, 100000, 6);   // 600rb, 6 unit -> game 6 sendiri
    $q = fn (IncentiveKategori $k) => TechnicianIncentive::where('user_id', $a->id)->where('kategori', $k->value);

    expect($q(IncentiveKategori::Games6Unit)->exists())->toBeTrue()
        ->and($q(IncentiveKategori::Games5OmsetTim)->exists())->toBeFalse();

    ($this->order)($a, null, 300000, 1);        // omset 900rb -> game 5 sendiri
    expect((float) $q(IncentiveKategori::Games5OmsetTim)->first()->nominal)->toBe(100000.0);

    $o->update(['status' => OrderStatus::Batal]);
    expect($q(IncentiveKategori::Games6Unit)->exists())->toBeFalse();
});
