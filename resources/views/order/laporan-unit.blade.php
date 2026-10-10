@php
    /** @var \App\Models\Order $order */
    $order = $getState();
    $service = app(\App\Services\UnitReportService::class);
    $units = $service->unitAktif($order);
    $foto = $order->workReports->flatMap->photos;
@endphp

@if ($units->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Belum ada data keterangan unit untuk order ini (order lama atau belum dikerjakan).
        Foto laporan tetap tersedia di tab Order / resi.
    </p>
@else
    <div class="space-y-3">
        @foreach ($units as $u)
            @php
                $item = $u->orderItem;
                $lengkap = $service->lengkap($u, $item);
                $butuh = $service->butuhKeterangan($item);
                $fotoUnit = $u->unit_no === $units->where('order_item_id', $item->id)->min('unit_no')
                    ? $foto->where('order_item_id', $item->id)->sortBy('urutan')
                    : collect();
            @endphp
            <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                <div class="flex items-center justify-between gap-2">
                    <p class="font-semibold text-gray-900 dark:text-white">
                        Unit {{ $u->unit_no }} &middot; {{ $u->jenis_pekerjaan ?: $item->nama_layanan }}
                    </p>
                    <span class="text-xs font-semibold {{ $lengkap ? 'text-green-600' : ($butuh ? 'text-amber-600' : 'text-gray-400') }}">
                        {{ $lengkap ? 'Lengkap' : ($butuh ? 'Perlu diisi' : 'Opsional') }}
                    </span>
                </div>
                <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-sm md:grid-cols-4">
                    <div><dt class="text-xs text-gray-500">Lokasi</dt><dd>{{ $u->lokasi_label ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Posisi</dt><dd>{{ $u->posisi ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Bagian</dt><dd>{{ \App\Models\OrderUnitReport::BAGIAN[$u->bagian] ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Kondisi</dt><dd>{{ \App\Models\OrderUnitReport::KONDISI[$u->kondisi] ?? 'Belum diisi' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Suhu</dt><dd>{{ $u->suhu !== null ? $u->suhu.' °C' : '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">RPM</dt><dd>{{ $u->rpm !== null ? (int) $u->rpm : '—' }}</dd></div>
                    <div class="col-span-2"><dt class="text-xs text-gray-500">Catatan kondisi</dt><dd>{{ $u->catatan_kondisi ?: '—' }}</dd></div>
                </dl>
                @if ($fotoUnit->isNotEmpty())
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($fotoUnit as $f)
                            <a href="{{ asset('storage/'.ltrim($f->path, '/')) }}" target="_blank" class="block w-24">
                                <img src="{{ asset('storage/'.ltrim($f->path, '/')) }}" alt="{{ $f->slot }}" class="h-20 w-24 rounded object-cover">
                                <span class="block truncate text-[10px] text-gray-500">{{ \App\Support\FotoLaporanSlot::untuk($item->kategori)[$f->slot] ?? $f->slot }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
