<?php

namespace Database\Seeders;

use App\Models\WaTemplate;
use App\Services\WaTemplateService;
use Illuminate\Database\Seeder;

/**
 * dev-plan/27 fase 27-A: isi awal template WA dari default bawaan
 * (WaTemplateService::DEFAULTS) agar perilaku awal sama dengan teks yang
 * selama ini hard-code; admin tinggal mengedit lewat menu WhatsApp.
 */
class WaTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (WaTemplateService::DEFAULTS as $kode => $default) {
            WaTemplate::updateOrCreate(
                ['kode' => $kode],
                ['nama' => $default['nama'], 'isi' => $default['isi']],
            );
        }
    }
}
