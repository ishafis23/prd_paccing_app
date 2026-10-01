/**
 * Alpine component untuk upload foto dengan pilihan kamera/gallery
 * Menggunakan Camera API (getUserMedia) untuk kamera yang lebih reliable
 * Penggunaan: x-data="photoUpload('fieldName', orderId)"
 */
function compressImageFile(file, maxDimension = 1200, quality = 0.7) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        const url = URL.createObjectURL(file);

        img.onload = () => {
            URL.revokeObjectURL(url);

            let { width, height } = img;
            if (width > maxDimension || height > maxDimension) {
                if (width > height) {
                    height = Math.round((height * maxDimension) / width);
                    width = maxDimension;
                } else {
                    width = Math.round((width * maxDimension) / height);
                    height = maxDimension;
                }
            }

            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            canvas.getContext('2d').drawImage(img, 0, 0, width, height);

            canvas.toBlob((blob) => {
                if (!blob) {
                    reject(new Error('Gagal memproses gambar.'));
                    return;
                }

                resolve(
                    new File(
                        [blob],
                        (file.name || 'foto').replace(/\.[^.]+$/, '') + '.jpg',
                        { type: 'image/jpeg' }
                    )
                );
            }, 'image/jpeg', quality);
        };

        img.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('Gagal membaca gambar.'));
        };

        img.src = url;
    });
}

function photoUpload(fieldName, orderId) {
    return {
        fieldName,
        orderId,
        showDialog: false,
        preview: null,
        nama: '',
        uploading: false,
        progress: 0,
        error: null,
        tempPhotoId: null,
        stream: null, // Untuk menyimpan camera stream

        /**
         * Buka dialog pilihan kamera/gallery
         */
        openDialog() {
            this.showDialog = true;
            this.error = null;
        },

        /**
         * Ambil foto langsung dari kamera.
         *
         * Pakai file input + capture="environment" supaya aplikasi kamera
         * bawaan HP terbuka. Pendekatan getUserMedia sebelumnya dibuang:
         * tidak ada UI preview/shutter, butuh HTTPS/izin khusus, dan sering
         * menghasilkan error "Gagal capture foto dari camera" (video 0x0).
         */
        openCamera() {
            this.showDialog = false;
            this.error = null;

            this.useCameraFileInput();
        },

        /**
         * Fallback: Gunakan file input dengan capture attribute untuk kamera
         */
        useCameraFileInput() {
            this.openFilePicker({ accept: 'image/*', capture: 'environment' });
        },

        /**
         * Ambil foto dari gallery (file input tanpa capture)
         *
         * PENTING: Di Android, accept="image/*" memicu intent yg diklaim
         * aplikasi Kamera (sehingga galeri malah buka kamera). Untuk galeri
         * kita buang accept agar sistem membuka file picker/galeri, bukan
         * intent kamera. Validasi tipe gambar tetap dilakukan di handleFile().
         */
        openGallery() {
            this.showDialog = false;
            this.error = null;

            this.openFilePicker({ accept: '' });
        },

        /**
         * Buat + tempel input ke DOM lalu klik.
         * Menempel ke DOM penting utk WebView Android: input yg terlepas
         * (detached) kadang membuat accept/capture diabaikan.
         */
        openFilePicker({ accept = 'image/*', capture = null } = {}) {
            const input = document.createElement('input');
            input.type = 'file';
            if (accept) {
                input.accept = accept;
            }
            if (capture) {
                input.capture = capture;
            }
            input.style.position = 'fixed';
            input.style.left = '-9999px';
            input.style.width = '1px';
            input.style.height = '1px';
            input.style.opacity = '0';

            const cleanup = () => input.remove();
            input.onchange = (e) => {
                this.handleFile(e);
                cleanup();
            };

            document.body.appendChild(input);
            input.click();

            // Fallback cleanup bila picker dibatalkan (tidak ada event change)
            setTimeout(cleanup, 120000);
        },

        /**
         * Handle file selection (dari gallery atau fallback camera)
         */
        async handleFile(event) {
            const file = event.target.files?.[0];
            if (!file) return;

            this.error = null;
            this.showDialog = false;

            // Validasi
            if (!file.type.startsWith('image/')) {
                this.error = 'File harus berupa gambar';
                return;
            }

            // Preview pakai file asli (cepat, tanpa menunggu kompres)
            this.preview = URL.createObjectURL(file);
            this.nama = file.name;

            // Kompres dulu supaya tidak kena limit 5 MB & upload cepat
            let compressed = file;
            try {
                compressed = await compressImageFile(file);
            } catch (err) {
                this.error = 'Gagal memproses foto: ' + (err?.message ?? err);
                this.preview = null;
                this.nama = '';
                return;
            }

            if (compressed.size > 5 * 1024 * 1024) {
                this.error = 'Ukuran file maksimal 5 MB';
                return;
            }

            // Upload real-time ke server
            await this.uploadToServer(compressed);
        },

        /**
         * Upload file ke server (real-time, bukan saat submit form)
         */
        async uploadToServer(file) {
            this.uploading = true;
            this.error = null;
            this.progress = 0;

            // Upload lewat Livewire supaya property di server menjadi
            // UploadedFile yang valid (dipakai saat submit: ->store(),
            // ->getSize(), validasi 'image', dll). Endpoint temp-photo
            // custom tidak dipakai lagi karena dulu mengubah property
            // menjadi string path sehingga submit error
            // "Call to a member function getSize() on string".
            this.$wire.upload(
                this.fieldName,
                file,
                () => {
                    this.uploading = false;
                },
                (message) => {
                    this.uploading = false;
                    this.error = 'Gagal upload foto: ' + message;
                    this.preview = null;
                    this.nama = '';
                },
                (event) => {
                    this.progress = event.detail.progress;
                }
            );
        },

        /**
         * Hapus foto yang sudah dipilih
         */
        async removePhoto() {
            if (!confirm('Hapus foto ini?')) return;

            this.$wire.set(this.fieldName, null);
            this.preview = null;
            this.nama = '';
            this.tempPhotoId = null;
            this.error = null;
        },
    };
}

/**
 * Helper untuk auto-restore temporary photos saat page load
 */
async function restoreTemporaryPhotos(orderId) {
    try {
        const response = await fetch(`/teknisi/order/${orderId}/temp-photos`);
        if (!response.ok) return {};

        const data = await response.json();
        return data.data || {};
    } catch (err) {
        console.error('Failed to restore temporary photos:', err);
        return {};
    }
}

/**
 * Helper untuk cleanup temporary photos saat submit berhasil
 */
async function cleanupTemporaryPhotos(orderId) {
    try {
        const response = await fetch(
            `/teknisi/order/${orderId}/temp-photos/cleanup`,
            {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector(
                        'meta[name="csrf-token"]'
                    )?.content,
                },
            }
        );
        return response.ok;
    } catch (err) {
        console.error('Failed to cleanup temporary photos:', err);
        return false;
    }
}
