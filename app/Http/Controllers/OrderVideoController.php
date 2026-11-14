<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Services\OrderVideoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Dokumentasi video di portal web teknisi (revisi 14 Nov 2026 poin 13). */
class OrderVideoController extends Controller
{
    public function store(Request $request, int $order, OrderVideoService $service): RedirectResponse
    {
        $request->validate(['video' => ['required', 'file'], 'keterangan' => ['nullable', 'string', 'max:255']], [
            'video.required' => 'Pilih atau rekam video terlebih dahulu.',
        ]);

        $o = Order::query()->untukTeknisi($request->user()->id)->findOrFail($order);

        try {
            $service->tambah($o, $request->user(), $request->file('video'), $request->input('keterangan'));
        } catch (BusinessRuleException $e) {
            return back()->with('video_error', $e->getMessage());
        }

        return back()->with('video_status', 'Video dokumentasi tersimpan.');
    }

    public function destroy(Request $request, int $order, int $video, OrderVideoService $service): RedirectResponse
    {
        $o = Order::query()->untukTeknisi($request->user()->id)->findOrFail($order);

        try {
            $service->hapus($o->videos()->findOrFail($video), $request->user());
        } catch (BusinessRuleException $e) {
            return back()->with('video_error', $e->getMessage());
        }

        return back()->with('video_status', 'Video dihapus.');
    }
}
