<?php

namespace App\Services;

use App\Enums\IncentiveKategori;
use App\Enums\IncentiveTipe;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\MotorCleaning;
use App\Models\TechnicianIncentive;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * Cuci/perawatan motor (dev-plan/15, Games 3 — B49): teknisi input sendiri
 * + foto, maks 2 orang per motor, bonus per orang lewat ledger insentif.
 */
class MotorCleaningService
{
    use RestrictsByRole;

    public const FOLDER = 'absensi';

    public function __construct(
        private readonly AttendanceSettingService $settingService,
        private readonly TechnicianIncentiveService $incentiveService,
        private readonly StorageQuotaService $quotaService,
    ) {}

    /**
     * @param  array<int, User>  $pesertaLain  teknisi lain (maks 1, di luar $pencatat)
     */
    public function catat(User $pencatat, UploadedFile $foto, array $pesertaLain = []): MotorCleaning
    {
        $this->assertRole($pencatat, [RoleName::Teknisi]);

        if (count($pesertaLain) > 1) {
            throw new BusinessRuleException('Maksimal 2 orang per motor.');
        }

        $this->quotaService->pastikanCukup($foto->getSize());
        $path = $foto->store(self::FOLDER, 'public');
        StorageQuotaService::lupakanCache();

        $tanggal = Carbon::today();

        $cleaning = MotorCleaning::create([
            'tanggal' => $tanggal->toDateString(),
            'foto' => $path,
            'dicatat_oleh' => $pencatat->id,
        ]);

        $peserta = collect([$pencatat])->merge($pesertaLain)->unique('id');
        $cleaning->teknisis()->sync($peserta->pluck('id'));

        foreach ($peserta as $teknisi) {
            $this->catatLedgerUntuk($teknisi, $tanggal, $path, $cleaning->id);
        }

        return $cleaning->fresh('teknisis');
    }

    /**
     * Games 3 bisa terjadi 2x sehari (pagi & sore) — akumulasi nominal ke
     * entri ledger yang sama hari itu, bukan menimpa (beda dari Games
     * lain yang selalu 1x/hari). Dipakai baik dari alur self-service
     * teknisi (`catat()`) maupun entri manual Admin/HR di Filament.
     */
    public function catatLedgerUntuk(User $teknisi, Carbon $tanggal, string $foto, int $cleaningId): void
    {
        $nominal = (float) $this->settingService->data()->nominal_games3;

        $existing = TechnicianIncentive::query()
            ->where('user_id', $teknisi->id)
            ->where('tanggal', $tanggal->toDateString())
            ->where('kategori', IncentiveKategori::Games3CuciMotor->value)
            ->first();

        $nominalBaru = $nominal + ($existing ? (float) $existing->nominal : 0.0);
        $referensi = trim(($existing?->referensi ?? '').";motor_cleaning_id={$cleaningId}", ';');

        $this->incentiveService->catat(
            $teknisi,
            $tanggal,
            IncentiveKategori::Games3CuciMotor,
            IncentiveTipe::Bonus,
            $nominalBaru,
            $foto,
            referensi: $referensi,
        );
    }
}
