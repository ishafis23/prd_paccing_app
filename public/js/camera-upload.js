/**
 * Alpine component untuk upload foto dengan pilihan kamera/gallery
 * Penggunaan: x-data="photoUpload('fieldName', orderId)"
 */
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

        /**
         * Buka dialog pilihan kamera/gallery
         */
        openDialog() {
            this.showDialog = true;
            this.error = null;
        },

        /**
         * Ambil foto langsung dari kamera (real-time)
         */
        openCamera() {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/*';
            input.capture = 'environment'; // Trigger kamera langsung
            input.onchange = (e) => this.handleFile(e);
            input.click();
        },

        /**
         * Ambil foto dari gallery (real-time)
         */
        openGallery() {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/*';
            input.capture = 'user'; // Selfie camera
            input.onchange = (e) => this.handleFile(e);
            input.click();
        },

        /**
         * Handle file selection (dari kamera atau gallery)
         */
        async handleFile(event) {
            const file = event.target.files?.[0];
            if (!file) return;

            // Validasi
            if (!file.type.startsWith('image/')) {
                this.error = 'File harus berupa gambar';
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                this.error = 'Ukuran file maksimal 5 MB';
                return;
            }

            // Show preview
            const reader = new FileReader();
            reader.onload = (e) => {
                this.preview = e.target.result;
                this.nama = file.name;
            };
            reader.readAsDataURL(file);

            // Upload real-time ke server
            await this.uploadToServer(file);
            this.showDialog = false;
        },

        /**
         * Upload file ke server (real-time, bukan saat submit form)
         */
        async uploadToServer(file) {
            this.uploading = true;
            this.error = null;

            const formData = new FormData();
            formData.append('file', file);
            formData.append('field_name', this.fieldName);
            formData.append('order_id', this.orderId);

            try {
                const response = await fetch(`/teknisi/order/${this.orderId}/temp-photo`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    },
                    body: formData,
                });

                if (!response.ok) {
                    const data = await response.json();
                    throw new Error(data.message || 'Upload gagal');
                }

                const data = await response.json();
                this.tempPhotoId = data.data.id;

                // Dispatch event ke Livewire untuk update model
                this.$dispatch('photo-uploaded', {
                    fieldName: this.fieldName,
                    tempPhotoId: this.tempPhotoId,
                    filePath: data.data.file_path,
                });
            } catch (err) {
                this.error = err.message || 'Terjadi kesalahan saat upload';
                this.preview = null;
                this.nama = '';
                this.tempPhotoId = null;
            } finally {
                this.uploading = false;
            }
        },

        /**
         * Hapus foto yang sudah diupload
         */
        async removePhoto() {
            if (!this.tempPhotoId) {
                this.preview = null;
                this.nama = '';
                return;
            }

            if (!confirm('Hapus foto ini?')) return;

            try {
                const response = await fetch(`/teknisi/temp-photo/${this.tempPhotoId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    },
                });

                if (!response.ok) throw new Error('Gagal menghapus foto');

                this.preview = null;
                this.nama = '';
                this.tempPhotoId = null;

                // Dispatch event ke Livewire
                this.$dispatch('photo-removed', {
                    fieldName: this.fieldName,
                });
            } catch (err) {
                this.error = 'Gagal menghapus foto: ' + err.message;
            }
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
        const response = await fetch(`/teknisi/order/${orderId}/temp-photos/cleanup`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            },
        });
        return response.ok;
    } catch (err) {
        console.error('Failed to cleanup temporary photos:', err);
        return false;
    }
}
