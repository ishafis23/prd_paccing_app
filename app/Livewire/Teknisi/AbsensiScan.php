<?php

namespace App\Livewire\Teknisi;

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\DailyAttendance;
use App\Models\User;
use App\Services\AttendanceCodeService;
use App\Services\AttendanceService;
use App\Services\MotorCleaningService;
use App\Support\Url;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Halaman absen kantor teknisi (dev-plan/15, §5): dibuka via scan QR
 * (`/teknisi/absensi/{kode}`) untuk absen datang, atau langsung dari menu
 * (`/teknisi/absensi`, tanpa kode) untuk absen pulang / lihat status hari
 * ini (kode tidak diperlukan lagi setelah absen datang tercatat).
 */
class AbsensiScan extends Component
{
    use WithFileUploads;

    public ?string $kode = null;

    public $foto;

    public $fotoMotor;

    public ?int $rekanMotorId = null;

    public function mount(?string $kode = null): void
    {
        $this->kode = $kode;
    }

    /**
     * Base URL utk kamera scan QR (resources/js/app.js) — dibangun dari
     * APP_URL eksplisit (App\Support\Url), BUKAN request ambien, supaya
     * navigasi hasil scan tetap benar di hosting subfolder. Lihat juga
     * AttendanceCodeResource::urlAbsen() (sumber isi QR-nya).
     */
    public function getUrlAbsensiBaseProperty(): string
    {
        return Url::absolute('teknisi.absensi');
    }

    public function getAbsenHariIniProperty(): ?DailyAttendance
    {
        return DailyAttendance::query()
            ->where('user_id', auth()->id())
            ->where('tanggal', Carbon::today()->toDateString())
            ->first();
    }

    public function getKodeValidProperty(): bool
    {
        if ($this->kode === null) {
            return false;
        }

        return app(AttendanceCodeService::class)->kodeAktifValid($this->kode) !== null;
    }

    /**
     * @return string 'perlu_kode'|'kode_invalid'|'siap_datang'|'siap_pulang'|'selesai'
     */
    public function getStateProperty(): string
    {
        $absen = $this->absenHariIni;

        if ($absen && $absen->jam_pulang !== null) {
            return 'selesai';
        }

        if ($absen && $absen->jam_datang !== null) {
            return 'siap_pulang';
        }

        if ($this->kode === null) {
            return 'perlu_kode';
        }

        return $this->kodeValid ? 'siap_datang' : 'kode_invalid';
    }

    public function catatDatang(): void
    {
        $this->validate(['foto' => ['required', 'image', 'max:5120']]);

        try {
            app(AttendanceService::class)->catatDatang(auth()->user(), (string) $this->kode, $this->foto);

            $this->reset('foto');
            session()->flash('status', 'Absen datang berhasil dicatat.');
        } catch (BusinessRuleException $e) {
            $this->addError('foto', $e->getMessage());
        }
    }

    public function catatPulang(): void
    {
        $this->validate(['foto' => ['required', 'image', 'max:5120']]);

        try {
            app(AttendanceService::class)->catatPulang(auth()->user(), $this->foto);

            $this->reset('foto');
            session()->flash('status', 'Absen pulang berhasil dicatat.');
        } catch (BusinessRuleException $e) {
            $this->addError('foto', $e->getMessage());
        }
    }

    /**
     * Games 3 (dev-plan/15, B49): daftar teknisi lain utk dipilih sbg
     * rekan cuci motor (maks 1 rekan, di luar diri sendiri).
     */
    public function getDaftarTeknisiLainProperty()
    {
        return User::role(RoleName::Teknisi->value)
            ->where('id', '!=', auth()->id())
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    public function catatCuciMotor(): void
    {
        $this->validate([
            'fotoMotor' => ['required', 'image', 'max:5120'],
            'rekanMotorId' => ['nullable', 'exists:users,id'],
        ]);

        try {
            $rekan = $this->rekanMotorId ? User::find($this->rekanMotorId) : null;

            app(MotorCleaningService::class)->catat(auth()->user(), $this->fotoMotor, $rekan ? [$rekan] : []);

            $this->reset('fotoMotor', 'rekanMotorId');
            session()->flash('status', 'Cuci motor berhasil dicatat.');
        } catch (BusinessRuleException $e) {
            $this->addError('fotoMotor', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.teknisi.absensi-scan')
            ->layout('layouts.teknisi', ['title' => 'Absensi']);
    }
}
