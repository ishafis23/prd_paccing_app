<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\BusinessInfoService;
use App\Services\StorageQuotaService;
use App\Support\Api\Presenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Login token (Sanctum), profil, perangkat push, foto profil. */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi salah.']);
        }

        if ($user->status !== UserStatus::Aktif) {
            return response()->json(['message' => 'Akun Anda dinonaktifkan. Hubungi admin.', 'code' => 'akun_nonaktif'], 403);
        }

        if (! $user->hasRole(RoleName::Teknisi->value)) {
            return response()->json(['message' => 'Aplikasi ini hanya untuk akun teknisi.', 'code' => 'bukan_teknisi'], 403);
        }

        $token = $user->createToken($data['device_name'], ['teknisi'], now()->addDays((int) config('api.token_hari', 90)));

        return response()->json(['data' => [
            'token' => $token->plainTextToken,
            'user' => Presenter::user($user),
        ]]);
    }

    public function logout(Request $request): JsonResponse
    {
        // Cabut token perangkat ini beserta token push-nya bila dikirim.
        if (filled($request->input('device_token'))) {
            DeviceToken::query()->where('user_id', $request->user()->id)->where('token', $request->input('device_token'))->delete();
        }

        $request->user()->currentAccessToken()->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $info = app(BusinessInfoService::class);

        return response()->json(['data' => [
            'user' => Presenter::user($user),
            'usaha' => ['nama' => $info->namaUsaha(), 'logo_url' => $info->logoUrl()],
            'min_app_version' => (string) config('api.min_app_version'),
            'waktu_server' => now()->toIso8601String(),
            'fitur' => ['push' => true, 'games56' => true, 'pengeluaran_partner' => true],
        ]]);
    }

    /** Daftarkan/perbarui token push perangkat (setelah login & saat token berganti). */
    public function daftarPerangkat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'in:android,ios'],
            'app_version' => ['nullable', 'string', 'max:20'],
        ]);

        $perangkat = DeviceToken::query()->updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $data['platform'] ?? 'android',
                'app_version' => $data['app_version'] ?? $request->header('X-App-Version'),
                'last_seen_at' => now(),
            ],
        );

        return response()->json(['data' => ['id' => $perangkat->id]], 201);
    }

    public function hapusPerangkat(Request $request, string $token): JsonResponse
    {
        DeviceToken::query()->where('user_id', $request->user()->id)->where('token', $token)->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    /** Ganti foto profil (multipart `foto`). */
    public function fotoProfil(Request $request): JsonResponse
    {
        $request->validate(['foto' => ['required', 'image', 'max:4096']]);

        $user = $request->user();
        $file = $request->file('foto');

        app(StorageQuotaService::class)->pastikanCukup((int) $file->getSize());

        $lama = $user->foto_profil;
        $path = $file->store('foto-profil', 'public');
        $user->foto_profil = $path;
        $user->save();

        if ($lama && $lama !== $path) {
            Storage::disk('public')->delete($lama);
        }

        StorageQuotaService::lupakanCache();

        return response()->json(['data' => Presenter::user($user)]);
    }
}
