#!/usr/bin/env bash
# =============================================================================
#  refresh-cache.sh — jalankan di ROOT folder proyek pada server (SSH /
#  cPanel "Terminal") SETELAH setiap deploy git pull / perubahan .env.
#
#  Syarat .env PRODUCTION (jangan pernah di-commit):
#      APP_URL=https://mycompany.web.id/paccing/public
#      ASSET_URL=            <- kosong / tidak diisi (hindari duplikasi path)
#
#  Catatan: script ini tidak menjalankan composer/npm; urutannya sengaja
#  config:clear > cache ulang agar APP_URL baru langsung terbaca.
# =============================================================================
set -euo pipefail
cd "$(dirname "$0")"

echo "== [1/6] Validasi APP_URL / ASSET_URL di .env =="
if [ ! -f .env ]; then
    echo "ERROR: .env tidak ditemukan." >&2
    exit 1
fi
grep -E '^APP_URL=' .env && echo "  -> pastikan berisi base + subfolder (mis. .../paccing/public)" || true
if grep -qE '^ASSET_URL=.+$' .env; then
    echo "WARNING: ASSET_URL terisi — bisa memicu duplikasi path. Kosongkan bila memakai subfolder." >&2
fi

echo "== [2/6] Symlink public/storage -> storage/app/public =="
php artisan storage:link

echo "== [3/6] Jalankan migrasi (aman diulang) =="
php artisan migrate --force

echo "== [4/6] Bersihkan semua cache lama =="
php artisan optimize:clear

echo "== [5/6] Bangun ulang cache produksi =="
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "== [6/6] Restart queue worker (bila dipakai) =="
php artisan queue:restart || true

echo ""
echo "Selesai. Uji: 1) https://mycompany.web.id/paccing/public/  "
echo "             2) file upload di panel admin (logo/QRIS/foto) lalu buka resi."
