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
        videoReady: false,
        decoded: false,
        error: null,
        instance: null,
        readyTimeout: null,

        start() {
            this.error = null;
            this.videoReady = false;
            this.decoded = false;

            // Fitur getUserMedia sendiri tidak ada di browser ini (WebView
            // lama, dll) — jangan lanjut, browser tidak akan pernah nanya
            // izin apa pun kalau API-nya memang tidak tersedia.
            if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
                this.error = 'Browser ini tidak mendukung akses kamera dari halaman web. Coba pakai Chrome/Safari versi terbaru.';

                return;
            }

            this.scanning = true;

            this.$nextTick(() => {
                try {
                    const video = this.$refs.qrVideo;

                    video.addEventListener('playing', () => {
                        this.videoReady = true;
                        clearTimeout(this.readyTimeout);
                    });

                    // Kalau stream tidak pernah mulai (izin diam2 diblok
                    // browser, kamera dipakai app lain, dll) — jangan
                    // biarkan layar hitam tanpa penjelasan selamanya.
                    this.readyTimeout = setTimeout(() => {
                        if (!this.videoReady) {
                            this.error = 'Kamera tidak merespons. Pastikan izin kamera diaktifkan utk browser ini, lalu coba lagi.';
                            this.stop();
                        }
                    }, 8000);

                    this.instance = new QrScanner(
                        video,
                        (result) => this.onDecoded(result.data, baseUrl),
                        {
                            highlightScanRegion: true,
                            highlightCodeOutline: true,
                            preferredCamera: 'environment',
                        }
                    );

                    this.instance.start().catch((err) => {
                        clearTimeout(this.readyTimeout);
                        this.error = 'Tidak bisa mengakses kamera: ' + (err?.message ?? err);
                        this.scanning = false;
                    });
                } catch (err) {
                    clearTimeout(this.readyTimeout);
                    this.error = 'Gagal membuka kamera: ' + (err?.message ?? err);
                    this.scanning = false;
                }
            });
        },

        stop() {
            clearTimeout(this.readyTimeout);
            if (this.instance) {
                this.instance.stop();
                this.instance.destroy();
                this.instance = null;
            }
            this.scanning = false;
            this.videoReady = false;
            this.decoded = false;
        },

        onDecoded(text, baseUrl) {
            // Jangan panggil stop() penuh di sini — itu juga menyembunyikan
            // kotak (scanning=false). Matikan kamera saja, tapi kotaknya
            // tetap tampil dgn overlay "terdeteksi" supaya user lihat
            // konfirmasi SEBELUM halaman pindah (sebelumnya langsung
            // redirect diam2, user kira macet — keluhan user 19 Sep).
            clearTimeout(this.readyTimeout);
            if (this.instance) {
                this.instance.stop();
                this.instance.destroy();
                this.instance = null;
            }
            this.decoded = true;

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

            setTimeout(() => {
                window.location.href = baseUrl.replace(/\/$/, '') + '/' + encodeURIComponent(kode);
            }, 500);
        },
    }));
});
