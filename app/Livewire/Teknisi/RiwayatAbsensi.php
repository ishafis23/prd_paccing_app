<?php

namespace App\Livewire\Teknisi;

use App\Models\DailyAttendance;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Riwayat absensi kantor teknisi sendiri, per tanggal — jam datang/pulang
 * & status (mana yang telat), diminta user 19 Sep 2026.
 */
class RiwayatAbsensi extends Component
{
    use WithPagination;

    public function render()
    {
        $riwayat = DailyAttendance::query()
            ->where('user_id', auth()->id())
            ->orderByDesc('tanggal')
            ->paginate(15);

        return view('livewire.teknisi.riwayat-absensi', ['riwayat' => $riwayat])
            ->layout('layouts.teknisi', ['title' => 'Riwayat Absensi']);
    }
}
