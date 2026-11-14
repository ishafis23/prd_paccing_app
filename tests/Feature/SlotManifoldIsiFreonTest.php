<?php

use App\Enums\ServiceType;
use App\Models\PhotoReportTemplate;
use App\Services\PhotoReportTemplateService;
use App\Support\FotoLaporanSlot;

it('Isi Freon punya slot foto manifold sebelum & sesudah, tidak wajib tapi dianjurkan', function () {
    $slot = FotoLaporanSlot::untuk(ServiceType::TambahFreon);

    expect($slot)->toHaveKeys(['tekanan_sebelum', 'tekanan_sesudah'])
        ->and($slot['tekanan_sebelum'])->toBe('Ukuran Manifold Sebelum')
        ->and(FotoLaporanSlot::wajibUntuk(ServiceType::TambahFreon))->not->toHaveKeys(['tekanan_sebelum', 'tekanan_sesudah'])
        ->and(array_keys(FotoLaporanSlot::dianjurkanUntuk(ServiceType::TambahFreon)))
        ->toEqualCanonicalizing(['tekanan_sebelum', 'tekanan_sesudah']);
});

it('slot dianjurkan yang diwajibkan admin pindah ke daftar wajib, bukan dianjurkan', function () {
    PhotoReportTemplate::where('kode_slot', 'tekanan_sebelum')->update(['wajib' => true]);
    app(PhotoReportTemplateService::class)->lupakanCache('tambah_freon');

    expect(FotoLaporanSlot::wajibUntuk(ServiceType::TambahFreon))->toHaveKey('tekanan_sebelum')
        ->and(FotoLaporanSlot::dianjurkanUntuk(ServiceType::TambahFreon))->not->toHaveKey('tekanan_sebelum');
});
