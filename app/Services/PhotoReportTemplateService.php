<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Exceptions\BusinessRuleException;
use App\Models\PhotoReportTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * dev-plan/17: kelola template slot foto laporan per kategori — pengganti
 * hardcode `App\Support\FotoLaporanSlot`. Admin bisa aktif/nonaktifkan &
 * tandai wajib per item, serta tambah item baru tanpa deploy (B61).
 */
class PhotoReportTemplateService
{
    use RestrictsByRole;

    private const PENGELOLA_ROLES = [RoleName::Admin, RoleName::Owner];

    /**
     * Slot aktif utk kategori tsb, terurut — [kode_slot => label].
     */
    public function untukKategori(?ServiceType $kategori): array
    {
        if ($kategori === null) {
            return [];
        }

        return Cache::remember(
            "photo_report_templates.{$kategori->value}",
            300,
            fn () => PhotoReportTemplate::query()
                ->where('kategori', $kategori->value)
                ->where('aktif', true)
                ->orderBy('urutan')
                ->pluck('label', 'kode_slot')
                ->all(),
        );
    }

    /**
     * Slot aktif & wajib utk kategori tsb — [kode_slot => label] (subset
     * dari untukKategori()). Dipakai validasi submit & penanda "Wajib".
     */
    public function daftarWajib(?ServiceType $kategori): array
    {
        if ($kategori === null) {
            return [];
        }

        return Cache::remember(
            "photo_report_templates.{$kategori->value}.wajib",
            300,
            fn () => PhotoReportTemplate::query()
                ->where('kategori', $kategori->value)
                ->where('aktif', true)
                ->where('wajib', true)
                ->orderBy('urutan')
                ->pluck('label', 'kode_slot')
                ->all(),
        );
    }

    /**
     * @param  array{kategori?: string, label?: string, urutan?: int, wajib?: bool}  $data
     */
    public function tambah(array $data, User $by): PhotoReportTemplate
    {
        $this->assertRole($by, self::PENGELOLA_ROLES);

        $kategori = $data['kategori'] ?? null;
        if (! $kategori) {
            throw new BusinessRuleException('Kategori wajib dipilih.');
        }

        $label = trim((string) ($data['label'] ?? ''));
        if ($label === '') {
            throw new BusinessRuleException('Label foto wajib diisi.');
        }

        $kodeSlot = Str::slug($label, '_');

        if (PhotoReportTemplate::query()->where('kategori', $kategori)->where('kode_slot', $kodeSlot)->exists()) {
            throw new BusinessRuleException('Sudah ada slot dengan nama serupa di kategori ini.');
        }

        $template = PhotoReportTemplate::create([
            'kategori' => $kategori,
            'kode_slot' => $kodeSlot,
            'label' => $label,
            'urutan' => $data['urutan'] ?? 0,
            'wajib' => (bool) ($data['wajib'] ?? false),
            'aktif' => true,
        ]);

        $this->lupakanCache($kategori);

        return $template;
    }

    public function toggleAktif(PhotoReportTemplate $template, User $by): PhotoReportTemplate
    {
        $this->assertRole($by, self::PENGELOLA_ROLES);

        $template->aktif = ! $template->aktif;
        $template->save();

        $this->lupakanCache($template->kategori->value);

        return $template->fresh();
    }

    /**
     * @param  array{label?: string, urutan?: int, wajib?: bool}  $data
     */
    public function perbarui(PhotoReportTemplate $template, array $data, User $by): PhotoReportTemplate
    {
        $this->assertRole($by, self::PENGELOLA_ROLES);

        // kode_slot sengaja diabaikan meski dikirim (B62) — terkunci di
        // level service, bukan cuma disembunyikan di form.
        if (array_key_exists('label', $data)) {
            $label = trim((string) $data['label']);
            if ($label === '') {
                throw new BusinessRuleException('Label foto wajib diisi.');
            }
            $template->label = $label;
        }

        if (array_key_exists('urutan', $data)) {
            $template->urutan = (int) $data['urutan'];
        }

        if (array_key_exists('wajib', $data)) {
            $template->wajib = (bool) $data['wajib'];
        }

        $template->save();

        $this->lupakanCache($template->kategori->value);

        return $template->fresh();
    }

    public function lupakanCache(string $kategori): void
    {
        Cache::forget("photo_report_templates.{$kategori}");
        Cache::forget("photo_report_templates.{$kategori}.wajib");
    }
}
