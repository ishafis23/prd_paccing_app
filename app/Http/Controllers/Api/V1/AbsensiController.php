<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AttendanceMode;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\AttendanceLocation;
use App\Models\DailyAttendance;
use App\Models\TechnicianEarlyLeave;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\AttendanceSettingService;
use App\Services\EarlyLeaveService;
use App\Services\MotorCleaningService;
use App\Support\Api\Presenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Absensi datang/pulang, cuci motor (Games 3), riwayat, pulang cepat. */
class AbsensiController extends Controller
{
    /** Status absensi hari ini + info mode & batas jam. */
    public function hariIni(Request $request): JsonResponse
    {
        $settings = app(AttendanceSettingService::class)->data();
        $absen = DailyAttendance::query()
            ->where('user_id', $request->user()->id)
            ->where('tanggal', Carbon::today()->toDateString())
            ->first();

        $state = match (true) {
            $absen?->jam_pulang !== null => 'selesai',
            $absen?->jam_datang !== null => 'siap_pulang',
            default => 'belum_absen',
        };

        return response()->json(['data' => [
            'tanggal' => today()->toDateString(),
            'waktu_server' => now()->toIso8601String(),
            'mode_absensi' => $settings->mode_absensi->value,
            'state' => $state,
            'absen' => Presenter::absensi($absen),
            'batas' => [
                'games1' => substr((string) $settings->jam_games1_batas, 0, 5),
                'normal_selesai' => substr((string) $settings->jam_normal_selesai, 0, 5),
                'games2' => substr((string) $settings->jam_games2_batas, 0, 5),
                'games4' => substr((string) $settings->jam_games4_batas, 0, 5),
            ],
            'lokasi_kantor' => $settings->mode_absensi === AttendanceMode::Lokasi
                ? AttendanceLocation::query()->where('aktif', true)->get()->map(fn ($l): array => [
                    'id' => $l->id,
                    'nama' => $l->nama,
                    'latitude' => (float) $l->latitude,
                    'longitude' => (float) $l->longitude,
                    'radius_meter' => (int) $l->radius_meter,
                ])->all()
                : [],
        ]]);
    }

    public function datang(Request $request): JsonResponse
    {
        $request->validate([
            'foto' => ['required', 'image', 'max:5120'],
            'kode' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $absen = app(AttendanceService::class)->catatDatang(
            $request->user(),
            $request->input('kode'),
            $request->file('foto'),
            $request->filled('lat') ? (float) $request->input('lat') : null,
            $request->filled('lng') ? (float) $request->input('lng') : null,
        );

        return response()->json(['data' => Presenter::absensi($absen)], 201);
    }

    public function pulang(Request $request): JsonResponse
    {
        $request->validate(['foto' => ['required', 'image', 'max:5120']]);

        $absen = app(AttendanceService::class)->catatPulang($request->user(), $request->file('foto'));

        return response()->json(['data' => Presenter::absensi($absen)]);
    }

    /** Riwayat absensi sendiri; `bulan=YYYY-MM` opsional, paginasi. */
    public function riwayat(Request $request): JsonResponse
    {
        $request->validate(['bulan' => ['nullable', 'date_format:Y-m']]);

        $hasil = DailyAttendance::query()
            ->where('user_id', $request->user()->id)
            ->when($request->filled('bulan'), fn ($q) => $q->whereYear('tanggal', substr((string) $request->input('bulan'), 0, 4))
                ->whereMonth('tanggal', substr((string) $request->input('bulan'), 5, 2)))
            ->orderByDesc('tanggal')
            ->paginate(15);

        return response()->json([
            'data' => $hasil->getCollection()->map(fn (DailyAttendance $a): array => Presenter::absensi($a))->values(),
            'meta' => ['page' => $hasil->currentPage(), 'per_page' => $hasil->perPage(), 'total' => $hasil->total()],
        ]);
    }

    /** Teknisi lain yang bisa jadi rekan cuci motor. */
    public function rekanCuciMotor(Request $request): JsonResponse
    {
        $rekan = User::role(RoleName::Teknisi->value)
            ->where('id', '!=', $request->user()->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u): array => ['id' => $u->id, 'name' => $u->name]);

        return response()->json(['data' => $rekan]);
    }

    public function cuciMotor(Request $request): JsonResponse
    {
        $request->validate([
            'foto' => ['required', 'image', 'max:5120'],
            'rekan_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $rekan = $request->filled('rekan_id') ? User::query()->find($request->integer('rekan_id')) : null;

        $cleaning = app(MotorCleaningService::class)->catat($request->user(), $request->file('foto'), $rekan ? [$rekan] : []);

        return response()->json(['data' => [
            'id' => $cleaning->id,
            'tanggal' => $cleaning->tanggal instanceof \DateTimeInterface ? $cleaning->tanggal->format('Y-m-d') : (string) $cleaning->tanggal,
            'peserta' => $cleaning->teknisis->map(fn (User $u): array => ['id' => $u->id, 'name' => $u->name])->values(),
        ]], 201);
    }

    public function pulangCepat(Request $request): JsonResponse
    {
        $riwayat = TechnicianEarlyLeave::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('tanggal')
            ->get()
            ->map(fn (TechnicianEarlyLeave $p): array => Presenter::pulangCepat($p));

        return response()->json(['data' => $riwayat]);
    }

    public function ajukanPulangCepat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'jam_pulang' => ['required', 'string', 'max:8'],
            'alasan' => ['nullable', 'string', 'max:255'],
        ]);

        $p = app(EarlyLeaveService::class)->ajukan($request->user(), $data['tanggal'], $data['jam_pulang'], $data['alasan'] ?? null);

        return response()->json(['data' => Presenter::pulangCepat($p)], 201);
    }
}
