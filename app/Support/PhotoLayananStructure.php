<?php

namespace App\Support;

/**
 * Struktur foto terorganisir per layanan & unit (dev-plan/teknisi/fase03 §5)
 * Mendefinisikan requirements untuk setiap tipe layanan & instruction text
 */
class PhotoLayananStructure
{
    // Photo types
    public const TYPE_LOKASI = 'lokasi';
    public const TYPE_CUCI = 'cuci';
    public const TYPE_SERVICE = 'service';

    /**
     * Struktur foto per layanan type
     *
     * @return array<string, array{
     *   type: string,
     *   label: string,
     *   repeatable: bool,
     *   positions: array<string, array{key: string, label: string, instruction: string}>
     * }>
     */
    public static function struktur(): array
    {
        return [
            self::TYPE_LOKASI => [
                'type' => self::TYPE_LOKASI,
                'label' => 'Foto Lokasi',
                'repeatable' => false,
                'positions' => [
                    'lokasi' => [
                        'key' => 'lokasi',
                        'label' => 'Tampak Depan Rumah/Kantor',
                        'instruction' => '📸 Tampak Depan Rumah/Kantor - tampilan depan secara keseluruhan',
                    ],
                ],
            ],
            self::TYPE_CUCI => [
                'type' => self::TYPE_CUCI,
                'label' => 'Cuci AC',
                'repeatable' => true,
                'positions' => [
                    'indoor' => [
                        'key' => 'indoor',
                        'label' => 'Cuci Indoor',
                        'instruction' => '🔧 Cuci Indoor - hasil pencucian bagian indoor sudah bersih',
                    ],
                    'outdoor' => [
                        'key' => 'outdoor',
                        'label' => 'Cuci Outdoor',
                        'instruction' => '🔧 Cuci Outdoor - hasil pencucian bagian outdoor sudah bersih',
                    ],
                    'area_indoor' => [
                        'key' => 'area_indoor',
                        'label' => 'Area Cuci (Indoor)',
                        'instruction' => '🏠 Area Cuci Indoor - area kerja saat pencucian indoor',
                    ],
                    'area_outdoor' => [
                        'key' => 'area_outdoor',
                        'label' => 'Area Cuci (Outdoor)',
                        'instruction' => '🏢 Area Cuci Outdoor - area kerja saat pencucian outdoor',
                    ],
                    'suhu' => [
                        'key' => 'suhu',
                        'label' => 'Foto Suhu Usai Cuci',
                        'instruction' => '🌡️ Suhu Indoor - capture layar temperature meter AC',
                    ],
                ],
            ],
            self::TYPE_SERVICE => [
                'type' => self::TYPE_SERVICE,
                'label' => 'Service AC',
                'repeatable' => true,
                'positions' => [
                    'kendala' => [
                        'key' => 'kendala',
                        'label' => 'Foto Kendala',
                        'instruction' => '⚠️ Kendala - foto menunjukkan masalah AC yang ada',
                    ],
                    'pengerjaan' => [
                        'key' => 'pengerjaan',
                        'label' => 'Foto Pengerjaan',
                        'instruction' => '🔧 Pengerjaan - proses service sedang berlangsung',
                    ],
                    'selesai' => [
                        'key' => 'selesai',
                        'label' => 'Foto Selesai Service',
                        'instruction' => '✅ Selesai Service - AC sudah berfungsi dengan baik',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get struktur untuk specific type
     */
    public static function untuk(string $type): array
    {
        return self::struktur()[$type] ?? [];
    }

    /**
     * Get label untuk type
     */
    public static function label(string $type): string
    {
        return self::untuk($type)['label'] ?? $type;
    }

    /**
     * Apakah type repeatable (support multiple units)?
     */
    public static function isRepeatable(string $type): bool
    {
        return self::untuk($type)['repeatable'] ?? false;
    }

    /**
     * Get positions untuk type
     *
     * @return array<string, array>
     */
    public static function positions(string $type): array
    {
        return self::untuk($type)['positions'] ?? [];
    }

    /**
     * Get instruction untuk photo position
     */
    public static function instruction(string $type, string $position): string
    {
        $positions = self::positions($type);
        return $positions[$position]['instruction'] ?? $position;
    }

    /**
     * Total foto wajib per type per unit
     */
    public static function totalFotoWajib(string $type): int
    {
        return count(self::positions($type));
    }

    /**
     * Get all types
     *
     * @return array<string>
     */
    public static function types(): array
    {
        return array_keys(self::struktur());
    }

    /**
     * Validate photo position untuk type
     */
    public static function isValidPosition(string $type, string $position): bool
    {
        return isset(self::positions($type)[$position]);
    }

    /**
     * Summary foto requirement untuk order
     * Asumsi: 1 lokasi + multiple cuci units + multiple service units
     */
    public static function totalFotoRequired(int $cuciUnits = 1, int $serviceUnits = 1): int
    {
        $lokasi = 1; // Lokasi: 1 foto
        $cuci = self::totalFotoWajib(self::TYPE_CUCI) * $cuciUnits; // 5 per unit
        $service = self::totalFotoWajib(self::TYPE_SERVICE) * $serviceUnits; // 3 per unit

        return $lokasi + $cuci + $service;
    }
}
