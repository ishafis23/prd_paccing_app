<x-filament-panels::page>
    @php
        $rp = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.');
        $angka = fn ($n) => number_format((float) $n, 0, ',', '.');
        $tgl = fn ($t) => \Illuminate\Support\Carbon::parse($t)->translatedFormat('d M Y');
        $kartu = 'rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5';
        $th = 'px-4 py-3';
        $tabUtama = 'flex-1 rounded-lg px-3 py-2 text-sm font-bold transition';
        $tabSub = 'rounded-full px-3 py-1.5 text-xs font-bold ring-1 transition';
    @endphp

    <div class="space-y-5">
        {{-- Filter bulan --}}
        <div class="flex flex-wrap items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
            <label class="text-sm font-semibold text-gray-600" for="pimpinan-bulan">Periode</label>
            <input id="pimpinan-bulan" type="month" wire:model.live="bulan"
                class="rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500">
            <span class="text-sm font-semibold text-gray-600">{{ $bulanLabel }}</span>
            <span class="ml-auto text-xs font-medium text-gray-400">Neraca = posisi saat ini</span>
        </div>

        {{-- Tab aspek --}}
        <div class="flex gap-1 rounded-xl bg-white p-1 shadow-sm ring-1 ring-gray-950/5">
            @foreach (['customer' => '1 · Aspek Customer', 'keuangan' => '2 · Aspek Keuangan', 'performa' => '3 · Aspek Performa'] as $key => $label)
                <button type="button" wire:click="setTab('{{ $key }}')"
                    class="{{ $tabUtama }} {{ $tab === $key ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-50' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- ================= TAB 1: CUSTOMER ================= --}}
        @if ($tab === 'customer')
            <div class="space-y-5">
                <div class="flex flex-wrap gap-4">
                    <div class="{{ $kartu }} min-w-[9rem] flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Customer</p>
                        <p class="mt-1 text-2xl font-bold text-gray-800">{{ $angka($customer['total']) }}</p>
                    </div>
                    <div class="{{ $kartu }} min-w-[9rem] flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Aktif</p>
                        <p class="mt-1 text-2xl font-bold text-emerald-600">{{ $angka($customer['aktif']) }}</p>
                    </div>
                    <div class="{{ $kartu }} min-w-[9rem] flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Lead</p>
                        <p class="mt-1 text-2xl font-bold text-amber-600">{{ $angka($customer['lead']) }}</p>
                    </div>
                    <div class="{{ $kartu }} min-w-[9rem] flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Nonaktif</p>
                        <p class="mt-1 text-2xl font-bold text-gray-500">{{ $angka($customer['nonaktif']) }}</p>
                    </div>
                    <div class="{{ $kartu }} min-w-[9rem] flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Baru {{ $bulanLabel }}</p>
                        <p class="mt-1 text-2xl font-bold text-primary-600">{{ $angka($customer['baru_bulan_ini']) }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-4">
                    <div class="{{ $kartu }} min-w-[14rem] flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Unit AC</p>
                        <p class="mt-1 text-2xl font-bold text-gray-800">{{ $angka($unit['total_unit']) }}</p>
                        <p class="mt-1 text-xs text-gray-400">
                            {{ $angka($unit['customer_punya_unit']) }} customer terdaftar unit ·
                            rata-rata {{ $unit['rata_rata'] }} unit/customer
                        </p>
                    </div>
                    <div class="{{ $kartu }} min-w-[18rem] flex-[2]">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Top Customer (jumlah unit)</p>
                        @if (empty($unit['top']))
                            <p class="mt-2 text-sm text-gray-400">Belum ada unit terdaftar.</p>
                        @else
                            <ul class="mt-2 space-y-1 text-sm">
                                @foreach ($unit['top'] as $t)
                                    <li class="flex items-center justify-between gap-3">
                                        <span class="truncate text-gray-700">{{ $t['nama'] }}</span>
                                        <span class="font-semibold text-gray-800">{{ $angka($t['jumlah_unit']) }} unit</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                <div>
                    <p class="mb-2 text-sm font-semibold text-gray-600">Follow-up jadwal cuci — {{ $bulanLabel }}</p>
                    <div class="flex flex-wrap gap-4">
                        <div class="{{ $kartu }} min-w-[12rem] flex-1 border-l-4 border-amber-400">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Perlu Difollow Up</p>
                            <p class="mt-1 text-2xl font-bold text-amber-600">{{ $angka($followUp['perlu_difollow_up']) }}</p>
                        </div>
                        <div class="{{ $kartu }} min-w-[12rem] flex-1 border-l-4 border-blue-400">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Sudah Di-follow Up (Pending)</p>
                            <p class="mt-1 text-2xl font-bold text-blue-600">{{ $angka($followUp['sudah_pending']) }}</p>
                        </div>
                        <div class="{{ $kartu }} min-w-[12rem] flex-1 border-l-4 border-emerald-400">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Sudah Di-follow Up (Terlaksana)</p>
                            <p class="mt-1 text-2xl font-bold text-emerald-600">{{ $angka($followUp['terlaksana']) }}</p>
                        </div>
                    </div>
                </div>

                {{-- Area jangkauan (placeholder) --}}
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
                    <div class="border-b border-gray-100 bg-gray-50/60 px-4 py-3">
                        <h3 class="text-sm font-bold text-gray-700">Area Jangkauan (per kecamatan)</h3>
                    </div>
                    <div class="p-6 text-center">
                        <p class="text-sm font-semibold text-gray-500">Segera hadir</p>
                        <p class="mt-1 text-xs text-gray-400">
                            Menunggu penambahan kolom kecamatan pada data customer/alamat.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- ================= TAB 2: KEUANGAN ================= --}}
        @if ($tab === 'keuangan')
            <div class="space-y-4">
                <div class="flex flex-wrap gap-2">
                    @foreach (['pendapatan' => '2.1 Pendapatan', 'pengeluaran' => '2.2 Pengeluaran', 'laba_rugi' => '2.3 Laba/Rugi', 'neraca' => '2.4 Neraca', 'arus_kas' => '2.5 Arus Kas'] as $key => $label)
                        <button type="button" wire:click="setKeuangan('{{ $key }}')"
                            class="{{ $tabSub }} {{ $keuangan === $key ? 'bg-gray-800 text-white ring-gray-800' : 'bg-white text-gray-600 ring-gray-200 hover:bg-gray-50' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                {{-- 2.1 Pendapatan --}}
                @if ($keuangan === 'pendapatan')
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="{{ $kartu }} border-l-4 border-emerald-400">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pendapatan {{ $bulanLabel }}</p>
                            <p class="mt-1 text-2xl font-bold text-emerald-600">{{ $rp($keuanganRingkas['pendapatan']) }}</p>
                        </div>
                        <div class="{{ $kartu }}">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Jasa</p>
                            <p class="mt-1 text-2xl font-bold text-gray-800">{{ $rp($keuanganRingkas['pendapatan_jasa']) }}</p>
                        </div>
                        <div class="{{ $kartu }}">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Material</p>
                            <p class="mt-1 text-2xl font-bold text-gray-800">{{ $rp($keuanganRingkas['pendapatan_material']) }}</p>
                        </div>
                    </div>

                    @include('filament.pages.partials.dashboard-pimpinan-rekap', [
                        'harian' => $pendapatanHarian,
                        'pekanan' => $pendapatanPekanan,
                        'labelTotal' => 'Pendapatan',
                        'kartu' => $kartu,
                        'th' => $th,
                    ])
                @endif

                {{-- 2.2 Pengeluaran --}}
                @if ($keuangan === 'pengeluaran')
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="{{ $kartu }} border-l-4 border-rose-400">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pengeluaran {{ $bulanLabel }}</p>
                            <p class="mt-1 text-2xl font-bold text-rose-600">{{ $rp($keuanganRingkas['pengeluaran']) }}</p>
                        </div>
                        <div class="{{ $kartu }} border-l-4 border-amber-400">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Menunggu Approval</p>
                            <p class="mt-1 text-2xl font-bold text-amber-600">{{ $rp($keuanganRingkas['pengeluaran_pending']) }}</p>
                            <p class="mt-1 text-xs text-gray-400">Belum dihitung ke total & laba/rugi</p>
                        </div>
                    </div>

                    @include('filament.pages.partials.dashboard-pimpinan-rekap', [
                        'harian' => $pengeluaranHarian,
                        'pekanan' => $pengeluaranPekanan,
                        'labelTotal' => 'Pengeluaran',
                        'kartu' => $kartu,
                        'th' => $th,
                    ])
                @endif

                {{-- 2.3 Laba/Rugi --}}
                @if ($keuangan === 'laba_rugi')
                    <div class="{{ $kartu }} border-l-4 {{ $keuanganRingkas['laba_rugi'] >= 0 ? 'border-emerald-400' : 'border-rose-400' }}">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Laba / Rugi {{ $bulanLabel }}</p>
                        <p class="mt-1 text-2xl font-bold {{ $keuanganRingkas['laba_rugi'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                            {{ $rp($keuanganRingkas['laba_rugi']) }}
                        </p>
                    </div>
                    @include('filament.pages.partials.dashboard-pimpinan-laba-rugi', [
                        'harian' => $labaRugiHarian,
                        'pekanan' => $labaRugiPekanan,
                        'kartu' => $kartu,
                        'th' => $th,
                        'rp' => $rp,
                        'tgl' => $tgl,
                    ])
                @endif

                {{-- 2.4 Neraca --}}
                @if ($keuangan === 'neraca')
                    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
                        <div class="border-b border-gray-100 bg-gray-50/60 px-4 py-3">
                            <h3 class="text-sm font-bold text-gray-700">Neraca (versi dasar, posisi saat ini)</h3>
                        </div>
                        <table class="w-full text-left text-sm">
                            <tbody class="divide-y divide-gray-100">
                                <tr><td class="{{ $th }} text-gray-600">Kas</td><td class="{{ $th }} text-right font-semibold">{{ $rp($neraca['kas']) }}</td></tr>
                                <tr><td class="{{ $th }} text-gray-600">Persediaan (stok)</td><td class="{{ $th }} text-right font-semibold">{{ $rp($neraca['persediaan']) }}</td></tr>
                                <tr><td class="{{ $th }} text-gray-600">Piutang (order belum lunas)</td><td class="{{ $th }} text-right font-semibold">{{ $rp($neraca['piutang']) }}</td></tr>
                                <tr class="bg-gray-50/60"><td class="{{ $th }} font-bold text-gray-800">Total Aset</td><td class="{{ $th }} text-right font-bold">{{ $rp($neraca['total_aset']) }}</td></tr>
                                <tr><td class="{{ $th }} text-gray-600">Kewajiban</td><td class="{{ $th }} text-right font-semibold">{{ $rp($neraca['kewajiban']) }}</td></tr>
                                <tr class="bg-gray-50/60"><td class="{{ $th }} font-bold text-gray-800">Ekuitas</td><td class="{{ $th }} text-right font-bold text-primary-700">{{ $rp($neraca['ekuitas']) }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- 2.5 Arus Kas --}}
                @if ($keuangan === 'arus_kas')
                    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
                        <div class="border-b border-gray-100 bg-gray-50/60 px-4 py-3">
                            <h3 class="text-sm font-bold text-gray-700">Arus Kas — {{ $bulanLabel }}</h3>
                        </div>
                        <table class="w-full text-left text-sm">
                            <tbody class="divide-y divide-gray-100">
                                <tr><td class="{{ $th }} text-gray-600">Kas Masuk</td><td class="{{ $th }} text-right font-semibold text-emerald-600">{{ $rp($arusKas['masuk']) }}</td></tr>
                                <tr><td class="{{ $th }} text-gray-600">Kas Keluar</td><td class="{{ $th }} text-right font-semibold text-rose-600">{{ $rp($arusKas['keluar']) }}</td></tr>
                                <tr class="bg-gray-50/60"><td class="{{ $th }} font-bold text-gray-800">Arus Kas Bersih</td><td class="{{ $th }} text-right font-bold {{ $arusKas['bersih'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $rp($arusKas['bersih']) }}</td></tr>
                                <tr><td class="{{ $th }} text-gray-600">Saldo Awal</td><td class="{{ $th }} text-right font-semibold">{{ $rp($arusKas['saldo_awal']) }}</td></tr>
                                <tr class="bg-gray-50/60"><td class="{{ $th }} font-bold text-gray-800">Saldo Akhir</td><td class="{{ $th }} text-right font-bold text-primary-700">{{ $rp($arusKas['saldo_akhir']) }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif

        {{-- ================= TAB 3: PERFORMA ================= --}}
        @if ($tab === 'performa')
            <div class="space-y-4">
                <div class="flex flex-wrap gap-2">
                    @foreach (['teknisi' => '3.1 Pengerjaan per Teknisi', 'kehadiran' => '3.2 Rekap Kehadiran', 'klasifikasi' => '3.3 Klasifikasi Pengerjaan'] as $key => $label)
                        <button type="button" wire:click="setPerforma('{{ $key }}')"
                            class="{{ $tabSub }} {{ $performa === $key ? 'bg-gray-800 text-white ring-gray-800' : 'bg-white text-gray-600 ring-gray-200 hover:bg-gray-50' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                {{-- 3.1 Pengerjaan per teknisi --}}
                @if ($performa === 'teknisi')
                    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
                        <div class="border-b border-gray-100 bg-gray-50/60 px-4 py-3">
                            <h3 class="text-sm font-bold text-gray-700">Pengerjaan per Teknisi — {{ $bulanLabel }}</h3>
                        </div>
                        @if (empty($performaTeknisi))
                            <p class="p-6 text-center text-sm text-gray-400">Belum ada teknisi aktif.</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead class="border-b border-gray-100 bg-gray-50/60 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        <tr>
                                            <th class="{{ $th }}">Teknisi</th>
                                            <th class="{{ $th }} text-right">Order Selesai</th>
                                            <th class="{{ $th }} text-right">Unit</th>
                                            <th class="{{ $th }} text-right">Rupiah</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($performaTeknisi as $p)
                                            <tr class="hover:bg-gray-50/60">
                                                <td class="{{ $th }} font-medium text-gray-700">{{ $p['nama'] }}</td>
                                                <td class="{{ $th }} text-right">{{ $angka($p['orders']) }}</td>
                                                <td class="{{ $th }} text-right">{{ $angka($p['unit']) }}</td>
                                                <td class="{{ $th }} text-right font-semibold">{{ $rp($p['rupiah']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- 3.2 Kehadiran --}}
                @if ($performa === 'kehadiran')
                    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
                        <div class="border-b border-gray-100 bg-gray-50/60 px-4 py-3">
                            <h3 class="text-sm font-bold text-gray-700">Rekap Kehadiran Teknisi — {{ $bulanLabel }}</h3>
                        </div>
                        @if (empty($kehadiran))
                            <p class="p-6 text-center text-sm text-gray-400">Belum ada teknisi aktif.</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead class="border-b border-gray-100 bg-gray-50/60 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        <tr>
                                            <th class="{{ $th }}">Teknisi</th>
                                            <th class="{{ $th }} text-right">Hadir</th>
                                            <th class="{{ $th }} text-right">Normal</th>
                                            <th class="{{ $th }} text-right">Bonus</th>
                                            <th class="{{ $th }} text-right">Telat</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($kehadiran as $h)
                                            <tr class="hover:bg-gray-50/60">
                                                <td class="{{ $th }} font-medium text-gray-700">{{ $h['nama'] }}</td>
                                                <td class="{{ $th }} text-right font-semibold">{{ $angka($h['hadir']) }}</td>
                                                <td class="{{ $th }} text-right">{{ $angka($h['normal']) }}</td>
                                                <td class="{{ $th }} text-right text-emerald-600">{{ $angka($h['bonus']) }}</td>
                                                <td class="{{ $th }} text-right text-rose-600">{{ $angka($h['telat']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- 3.3 Klasifikasi pengerjaan --}}
                @if ($performa === 'klasifikasi')
                    @php
                        $labelKlasifikasi = ['cuci' => 'Cuci', 'service' => 'Service', 'pemasangan' => 'Pemasangan'];
                    @endphp
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        @foreach ($labelKlasifikasi as $key => $label)
                            @php $k = $klasifikasi[$key] ?? ['unit' => 0, 'rupiah' => 0, 'transaksi' => 0, 'items' => []]; @endphp
                            <div class="{{ $kartu }}">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</p>
                                <p class="mt-1 text-2xl font-bold text-gray-800">
                                    {{ $angka($k['unit']) }} <span class="text-sm font-normal text-gray-400">unit</span>
                                </p>
                                <p class="text-sm font-semibold text-gray-600">{{ $rp($k['rupiah']) }}</p>
                                <p class="text-xs text-gray-500">Jasa {{ $rp($k['jasa'] ?? 0) }} · Material {{ $rp($k['material'] ?? 0) }}</p>
                                <p class="text-xs text-gray-400">{{ $angka($k['transaksi']) }} baris layanan</p>

                                <div class="mt-3 border-t border-gray-100 pt-3">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Rincian</p>
                                    @if (empty($k['items']))
                                        <p class="mt-1 text-xs text-gray-400">Belum ada pengerjaan.</p>
                                    @else
                                        <ul class="mt-1 space-y-1 text-xs">
                                            @foreach ($k['items'] as $it)
                                                <li class="flex items-center justify-between gap-2">
                                                    <span class="truncate text-gray-600">{{ $it['label'] }}</span>
                                                    <span class="whitespace-nowrap text-gray-500">{{ $angka($it['unit']) }} unit · {{ $rp($it['rupiah']) }} (Jasa {{ $rp($it['jasa'] ?? 0) }} · Mat. {{ $rp($it['material'] ?? 0) }})</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
