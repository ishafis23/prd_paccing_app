<x-filament-panels::page>
    @php
        $rp = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.');
        $angka = fn ($n) => number_format((float) $n, 0, ',', '.');
        $kartu = 'rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5';
        $judulSeksi = 'text-base font-bold text-gray-800';
        $th = 'px-4 py-3';
    @endphp

    <div class="space-y-6">
        {{-- Filter bulan --}}
        <div class="flex flex-wrap items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
            <label class="text-sm font-semibold text-gray-600" for="pimpinan-bulan">Periode</label>
            <input id="pimpinan-bulan" type="month" wire:model.live="bulan"
                class="rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500">
            <span class="text-sm font-semibold text-gray-600">{{ $bulanLabel }}</span>
            <span class="ml-auto text-xs font-medium text-gray-400">Neraca = posisi saat ini</span>
        </div>

        {{-- ================= ASPEK CUSTOMER ================= --}}
        <section class="space-y-4">
            <h2 class="{{ $judulSeksi }}">Aspek Customer</h2>

            <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
                <div class="{{ $kartu }}">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Customer</p>
                    <p class="mt-1 text-2xl font-bold text-gray-800">{{ $angka($customer['total']) }}</p>
                </div>
                <div class="{{ $kartu }}">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Aktif</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-600">{{ $angka($customer['aktif']) }}</p>
                </div>
                <div class="{{ $kartu }}">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Lead</p>
                    <p class="mt-1 text-2xl font-bold text-amber-600">{{ $angka($customer['lead']) }}</p>
                </div>
                <div class="{{ $kartu }}">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Nonaktif</p>
                    <p class="mt-1 text-2xl font-bold text-gray-500">{{ $angka($customer['nonaktif']) }}</p>
                </div>
                <div class="{{ $kartu }}">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Baru {{ $bulanLabel }}</p>
                    <p class="mt-1 text-2xl font-bold text-primary-600">{{ $angka($customer['baru_bulan_ini']) }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="{{ $kartu }}">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Unit AC</p>
                    <p class="mt-1 text-2xl font-bold text-gray-800">{{ $angka($unit['total_unit']) }}</p>
                    <p class="mt-1 text-xs text-gray-400">
                        {{ $angka($unit['customer_punya_unit']) }} customer terdaftar unit ·
                        rata-rata {{ $unit['rata_rata'] }} unit/customer
                    </p>
                </div>
                <div class="{{ $kartu }} lg:col-span-2">
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
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="{{ $kartu }} border-l-4 border-amber-400">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Perlu Difollow Up</p>
                        <p class="mt-1 text-2xl font-bold text-amber-600">{{ $angka($followUp['perlu_difollow_up']) }}</p>
                    </div>
                    <div class="{{ $kartu }} border-l-4 border-blue-400">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Sudah Di-follow Up (Pending)</p>
                        <p class="mt-1 text-2xl font-bold text-blue-600">{{ $angka($followUp['sudah_pending']) }}</p>
                    </div>
                    <div class="{{ $kartu }} border-l-4 border-emerald-400">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Sudah Di-follow Up (Terlaksana)</p>
                        <p class="mt-1 text-2xl font-bold text-emerald-600">{{ $angka($followUp['terlaksana']) }}</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- ================= ASPEK KEUANGAN ================= --}}
        <section class="space-y-4">
            <h2 class="{{ $judulSeksi }}">Aspek Keuangan — {{ $bulanLabel }}</h2>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="{{ $kartu }} border-l-4 border-emerald-400">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pendapatan</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-600">{{ $rp($keuangan['pendapatan']) }}</p>
                    <p class="mt-1 text-xs text-gray-400">
                        Jasa {{ $rp($keuangan['pendapatan_jasa']) }} · Material {{ $rp($keuangan['pendapatan_material']) }}
                    </p>
                </div>
                <div class="{{ $kartu }} border-l-4 border-rose-400">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pengeluaran</p>
                    <p class="mt-1 text-2xl font-bold text-rose-600">{{ $rp($keuangan['pengeluaran']) }}</p>
                    @if ($keuangan['pengeluaran_pending'] > 0)
                        <p class="mt-1 text-xs text-amber-600">+ {{ $rp($keuangan['pengeluaran_pending']) }} menunggu approval</p>
                    @endif
                </div>
                <div class="{{ $kartu }} border-l-4 {{ $keuangan['laba_rugi'] >= 0 ? 'border-emerald-400' : 'border-rose-400' }}">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Laba / Rugi</p>
                    <p class="mt-1 text-2xl font-bold {{ $keuangan['laba_rugi'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                        {{ $rp($keuangan['laba_rugi']) }}
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                {{-- Neraca --}}
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
                    <div class="border-b border-gray-100 bg-gray-50/60 px-4 py-3">
                        <h3 class="text-sm font-bold text-gray-700">Neraca (versi dasar)</h3>
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

                {{-- Arus Kas --}}
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
            </div>
        </section>

        {{-- ================= ASPEK PERFORMA ================= --}}
        <section class="space-y-4">
            <h2 class="{{ $judulSeksi }}">Aspek Performa — {{ $bulanLabel }}</h2>

            {{-- Klasifikasi pengerjaan --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                @php
                    $labelKlasifikasi = ['cuci' => 'Cuci', 'service' => 'Service', 'pemasangan' => 'Pemasangan'];
                    $warnaKlasifikasi = ['cuci' => 'primary', 'service' => 'amber', 'pemasangan' => 'emerald'];
                @endphp
                @foreach ($labelKlasifikasi as $key => $label)
                    @php $k = $klasifikasi[$key] ?? ['unit' => 0, 'rupiah' => 0, 'transaksi' => 0]; @endphp
                    <div class="{{ $kartu }}">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-800">{{ $angka($k['unit']) }} <span class="text-sm font-normal text-gray-400">unit</span></p>
                        <p class="mt-1 text-sm font-semibold text-gray-600">{{ $rp($k['rupiah']) }}</p>
                        <p class="text-xs text-gray-400">{{ $angka($k['transaksi']) }} baris layanan</p>
                    </div>
                @endforeach
            </div>

            {{-- Pengerjaan teknisi --}}
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
                <div class="border-b border-gray-100 bg-gray-50/60 px-4 py-3">
                    <h3 class="text-sm font-bold text-gray-700">Pengerjaan per Teknisi</h3>
                </div>
                @if (empty($performa))
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
                                @foreach ($performa as $p)
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

            {{-- Kehadiran teknisi --}}
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
                <div class="border-b border-gray-100 bg-gray-50/60 px-4 py-3">
                    <h3 class="text-sm font-bold text-gray-700">Kehadiran Teknisi</h3>
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
        </section>
    </div>
</x-filament-panels::page>
