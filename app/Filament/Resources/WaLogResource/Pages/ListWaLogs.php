<?php

namespace App\Filament\Resources\WaLogResource\Pages;

use App\Filament\Resources\WaLogResource;
use Filament\Resources\Pages\ListRecords;

class ListWaLogs extends ListRecords
{
    protected static string $resource = WaLogResource::class;

    protected function getHeaderActions(): array
    {
        return []; // riwayat read-only; hanya aksi per-baris (Kirim Ulang, dll)
    }
}
