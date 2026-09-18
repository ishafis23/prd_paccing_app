import './bootstrap';
import QrScanner from 'qr-scanner';

/**
 * Scan QR absensi kantor lewat kamera HP (dev-plan/15 §5, revisi 18 Sep):
 * sebelumnya teknisi harus scan pakai app kamera bawaan HP di luar
 * aplikasi ini — sekarang kamera dibuka langsung di halaman Absensi.
 * Hasil scan cuma diambil SEGMEN TERAKHIR path-nya sbg "kode" lalu
 * dipasang ke baseUrl kita sendiri (App\Support\Url::absolute, dikirim
 * dari server) — supaya kalaupun QR fisik di kantor pernah ditukar orang
 * tak dikenal, kita tetap navigasi ke domain sendiri, bukan ikut URL apa
 * pun yang ada di dalam QR.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('qrScanner', (baseUrl) => ({
        scanning: false,
        error: null,
        instance: null,

        start() {
            this.error = null;
            this.scanning = true;

            this.$nextTick(() => {
                this.instance = new QrScanner(
                    this.$refs.qrVideo,
                    (result) => this.onDecoded(result.data, baseUrl),
                    {
                        highlightScanRegion: true,
                        highlightCodeOutline: true,
                        preferredCamera: 'environment',
                    }
                );

                this.instance.start().catch((err) => {
                    this.error = 'Tidak bisa mengakses kamera: ' + (err?.message ?? err);
                    this.scanning = false;
                });
            });
        },

        stop() {
            if (this.instance) {
                this.instance.stop();
                this.instance.destroy();
                this.instance = null;
            }
            this.scanning = false;
        },

        onDecoded(text, baseUrl) {
            this.stop();

            let kode = text;
            try {
                const parsed = new URL(text, window.location.origin);
                const segments = parsed.pathname.split('/').filter(Boolean);
                if (segments.length > 0) {
                    kode = segments[segments.length - 1];
                }
            } catch (e) {
                // teks hasil scan bukan URL — pakai apa adanya sbg kode.
            }

            window.location.href = baseUrl.replace(/\/$/, '') + '/' + encodeURIComponent(kode);
        },
    }));
});
