<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use Filament\Pages\Page;

/**
 * Halaman admin utk meninjau & menyetujui pengeluaran yang dilaporkan
 * teknisi. Membungkus komponen Livewire ManajemenPengeluaranTeknisi agar
 * muncul di sidebar Filament (sebelumnya hanya bisa diakses via URL mentah).
 * Owner/Admin/Finance boleh mengakses.
 */
class PengeluaranTeknisi extends Page
{
    protected static string $view = 'filament.pages.pengeluaran-teknisi';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Pengeluaran Teknisi';

    protected static ?string $title = 'Pengeluaran Teknisi';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $slug = 'pengeluaran-teknisi';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->hasAnyRole([RoleName::Owner->value, RoleName::Admin->value, RoleName::Finance->value]);
    }
}
