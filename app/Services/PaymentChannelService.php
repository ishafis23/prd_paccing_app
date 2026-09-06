<?php

namespace App\Services;

use App\Enums\PaymentChannelType;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\PaymentChannel;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Pengelolaan master channel pembayaran (keputusan B15/B16).
 *
 * - CRUD + toggle aktif: Admin/Owner.
 * - Finance & Teknisi: read-only (query aktif lewat scope model).
 */
class PaymentChannelService
{
    use RestrictsByRole;

    /**
     * @param  array{nama: string, jenis: string, atas_nama?: ?string, nomor_rekening?: ?string, nama_bank?: ?string, gambar?: ?string, aktif?: bool}  $data
     */
    public function create(array $data, User $by): PaymentChannel
    {
        $this->assertRole($by, [RoleName::Admin, RoleName::Owner]);
        $jenis = $this->resolusiJenis($data['jenis'] ?? null);
        $this->validasiSpesifikJenis($jenis, $data);

        $channel = PaymentChannel::create([
            'nama' => trim((string) $data['nama']),
            'jenis' => $jenis,
            'atas_nama' => $this->nullJikaKosong($data['atas_nama'] ?? null),
            'nomor_rekening' => $this->nullJikaKosong($data['nomor_rekening'] ?? null),
            'nama_bank' => $this->nullJikaKosong($data['nama_bank'] ?? null),
            'gambar' => $this->nullJikaKosong($data['gambar'] ?? null),
            'aktif' => (bool) ($data['aktif'] ?? true),
            'dicatat_oleh' => $by->id,
        ]);

        return $channel->fresh();
    }

    /**
     * @param  array{nama?: string, jenis?: string, atas_nama?: ?string, nomor_rekening?: ?string, nama_bank?: ?string, gambar?: ?string, aktif?: bool}  $data
     */
    public function update(PaymentChannel $channel, array $data, User $by): PaymentChannel
    {
        $this->assertRole($by, [RoleName::Admin, RoleName::Owner]);
        $jenis = $this->resolusiJenis($data['jenis'] ?? $channel->jenis->value);
        $this->validasiSpesifikJenis($jenis, $data);

        $channel->nama = trim((string) ($data['nama'] ?? $channel->nama));
        $channel->jenis = $jenis;
        $channel->atas_nama = $this->nullJikaKosong($data['atas_nama'] ?? $channel->atas_nama);
        $channel->nomor_rekening = $this->nullJikaKosong($data['nomor_rekening'] ?? $channel->nomor_rekening);
        $channel->nama_bank = $this->nullJikaKosong($data['nama_bank'] ?? $channel->nama_bank);
        $channel->gambar = $this->nullJikaKosong($data['gambar'] ?? $channel->gambar);
        $channel->aktif = (bool) ($data['aktif'] ?? $channel->aktif);
        $channel->save();

        return $channel->fresh();
    }

    public function setAktif(PaymentChannel $channel, bool $aktif, User $by): PaymentChannel
    {
        $this->assertRole($by, [RoleName::Admin, RoleName::Owner]);
        $channel->aktif = $aktif;
        $channel->save();

        return $channel->fresh();
    }

    public function hapus(PaymentChannel $channel, User $by): void
    {
        $this->assertRole($by, [RoleName::Admin, RoleName::Owner]);
        $channel->delete();
    }

    /**
     * Channel aktif untuk ditampilkan ke customer (sisi teknisi/resi).
     */
    public function daftarAktif(): Collection
    {
        return PaymentChannel::query()->aktif()->terurut()->get();
    }

    private function resolusiJenis(?string $jenis): PaymentChannelType
    {
        return PaymentChannelType::tryFrom((string) $jenis)
            ?? throw new BusinessRuleException('Jenis channel tidak valid.');
    }

    /**
     * Aturan spesifik jenis (dijaga di Service, bukan hanya di form UI).
     */
    private function validasiSpesifikJenis(PaymentChannelType $jenis, array $data): void
    {
        if (trim((string) ($data['nama'] ?? '')) === '') {
            throw new BusinessRuleException('Nama channel wajib diisi.');
        }

        if ($jenis === PaymentChannelType::Bank) {
            if (trim((string) ($data['nomor_rekening'] ?? '')) === '') {
                throw new BusinessRuleException('Nomor rekening wajib diisi untuk channel bank.');
            }

            if (trim((string) ($data['nama_bank'] ?? '')) === '') {
                throw new BusinessRuleException('Nama bank wajib diisi untuk channel bank.');
            }
        }
    }

    private function nullJikaKosong(mixed $nilai): ?string
    {
        $nilai = is_string($nilai) ? trim($nilai) : $nilai;

        return ($nilai === null || $nilai === '') ? null : (string) $nilai;
    }
}
