<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\Http;

/**
 * Ekstrak koordinat dari link Google Maps yang biasa di-paste Admin dari
 * chat WA customer (link penuh atau short link maps.app.goo.gl).
 */
class GoogleMapsLinkService
{
    private const ALLOWED_HOSTS = [
        'maps.app.goo.gl',
        'goo.gl',
        'google.com',
        'www.google.com',
        'maps.google.com',
    ];

    /**
     * @return array{lat: float, lng: float}
     */
    public function resolveCoordinates(string $link): array
    {
        $link = trim($link);
        $host = parse_url($link, PHP_URL_HOST);

        if ($host === null || ! in_array($host, self::ALLOWED_HOSTS, true)) {
            throw new BusinessRuleException('Link harus berupa link Google Maps (google.com/maps atau maps.app.goo.gl).');
        }

        try {
            $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; crm-paccing-bot/1.0)'])
                ->timeout(8)
                ->get($link);
        } catch (\Throwable $e) {
            throw new BusinessRuleException('Gagal membuka link Google Maps. Coba lagi atau isi koordinat manual.');
        }

        // URL final setelah redirect (short link goo.gl -> link lengkap berisi koordinat).
        $finalUrl = (string) $response->effectiveUri();
        $haystack = $finalUrl.' '.$response->body();

        $coordinates = $this->extractFromText($haystack);

        if ($coordinates === null) {
            throw new BusinessRuleException('Koordinat tidak ditemukan di link tersebut. Isi latitude/longitude manual.');
        }

        return $coordinates;
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function extractFromText(string $text): ?array
    {
        // Pin tempat spesifik, lebih akurat dari titik tengah peta.
        if (preg_match('/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', $text, $m)) {
            return $this->bulatkan($m[1], $m[2]);
        }

        // Titik tengah peta pada URL, contoh: /@-5.1477,119.4327,17z/
        if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $text, $m)) {
            return $this->bulatkan($m[1], $m[2]);
        }

        // Parameter query q=lat,lng atau ll=lat,lng
        if (preg_match('/[?&](?:q|ll)=(-?\d+\.\d+),(-?\d+\.\d+)/', $text, $m)) {
            return $this->bulatkan($m[1], $m[2]);
        }

        return null;
    }

    /**
     * Bulatkan ke 7 desimal (presisi kolom DB) — cast (float) polos bisa
     * menghasilkan sisa presisi ganjil (mis. 119.44289239999999) yang
     * ditolak validasi HTML5 `step` pada input Latitude/Longitude.
     *
     * @return array{lat: float, lng: float}
     */
    private function bulatkan(string $lat, string $lng): array
    {
        return ['lat' => round((float) $lat, 7), 'lng' => round((float) $lng, 7)];
    }
}
