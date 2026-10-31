# AI Food Photography Studio

Prototipe aplikasi untuk membantu pengeditan foto makanan dan minuman dengan mengutamakan keaslian produk. **Belum siap release atau produksi**: komponen belum menjadi alur edit end-to-end, dan kualitas serta keamanan belum tervalidasi untuk penggunaan nyata.

## Status fitur

- `frontend/`: antarmuka Next.js lokal; belum terintegrasi dengan upload nyata atau layanan AI.
- `backend/`: prototipe Laravel untuk autentikasi sesi, workspace, konfigurasi provider, pemeriksaan update, dan upload aset; lihat [catatan backend](backend/README.md) untuk batasan penting.
- `orchestrator/`: prototipe policy EditPlan yang konservatif, bukan eksekutor atau parser instruksi alami.
- `worker/`: primitive compositing RGB dalam memori dengan mask biner, bukan worker pengolahan gambar end-to-end.
- Belum ada integrasi lengkap frontend–backend–worker, segmentasi tervalidasi, generasi gambar, ekspor final, atau bukti benchmark fidelity. Jangan menganggap policy atau mask sebagai jaminan perlindungan produk.

Penggunaan provider eksternal dan transfer foto memerlukan persetujuan tersendiri. Gunakan hanya data uji lokal; jangan masukkan secrets atau foto sensitif ke repository.

## Akun default

Seeder (`php artisan db:seed`) membuat satu akun super admin dan workspace default secara idempotent. Login memakai **email** sebagai identifier.

| Peran | Nama | Email (login) | Password |
| --- | --- | --- | --- |
| Super admin | `superadmin` | `superadmin@food-photo.local` | `photosuper` |

Workspace default: **Dapur Utama** (super admin sebagai `owner`).

> **PENTING:** Ganti password default segera setelah login pertama di lingkungan nyata. Seeder bersifat idempotent (`firstOrCreate`) sehingga menjalankannya ulang saat deploy tidak menimpa password yang sudah diubah.

## Instalasi server lengkap (Debian/Ubuntu)

Untuk memasang dependency OS, clone release, build aplikasi, membuat service systemd, memasang reverse proxy Nginx, dan mengaktifkan service, gunakan satu script berikut. `git clone` sendiri **tidak** mengeksekusi script secara otomatis.

### 1. Prasyarat dan backup

- Server Debian/Ubuntu dengan akses `root`/`sudo`, akses outbound ke GitHub dan repository.
- DNS atau alamat host yang akan dipakai pengguna.
- Port publik default: `8089` (akses langsung: `http://<server-host>:8089/`). Dapat diubah dengan `PUBLIC_PORT`.
- Backup database dan storage sebelum migration/aktivasi.
- Siapkan konfigurasi aplikasi di luar Git; jangan commit `.env`, password, token, API key, atau connection string.
- Script saat ini mengasumsikan package manager `apt-get` dan nama paket PHP 8.3 tersedia pada repository OS.

### 2. Clone installer

```sh
cd /tmp
git clone --branch main \
  https://github.com/desienkz-slp/ai-food-photo-studio.git \
  ai-food-photo-studio-installer
cd ai-food-photo-studio-installer
```

Verifikasi release yang akan digunakan:

```sh
git rev-parse HEAD
git status --short
```

### 3. Siapkan konfigurasi eksternal

Buat file konfigurasi yang hanya dapat dibaca service. Jangan menyalin nilai secret ke README, terminal history, atau log:

```sh
sudo install -d -m 0750 /etc/food-photo-studio
sudo install -m 0640 -o root -g www-data /dev/null \
  /etc/food-photo-studio/backend.env
# Isi backend.env melalui mekanisme secret management yang disetujui.
```

Minimal, sesuaikan nilai yang diwajibkan oleh `backend/.env.example`, termasuk `APP_KEY`, `APP_URL`, dan koneksi database. Validasi format tanpa mencetak nilainya.

### 4. Dry-run

```sh
sudo DRY_RUN=1 PUBLIC_PORT=8089 RELEASE_ID=$(date -u +%Y%m%d%H%M%S) \
  ./scripts/install-and-activate.sh --dry-run
```

Dry-run tidak memasang paket, clone, menulis konfigurasi, membuat service, migration, atau mengaktifkan proses.

### 5. Instalasi dan aktivasi

Setelah backup, konfigurasi, dan rollback plan disetujui:

```sh
sudo PUBLIC_PORT=8089 RELEASE_ID=$(date -u +%Y%m%d%H%M%S) \
  ./scripts/install-and-activate.sh
```

Urutan script:

1. Install Git, Nginx, PHP 8.3/FPM, extension PHP, Composer, Node.js, dan npm.
2. Clone release ke `/opt/food-photo-studio/releases/<release-id>`.
3. Jalankan `scripts/install-release.sh --install` untuk Composer dependency, npm dependency, dan frontend build.
4. Tulis unit `food-photo-backend.service` dan `food-photo-frontend.service`.
5. Tulis Nginx reverse proxy dan symlink `/opt/food-photo-studio/current`.
6. Jalankan `nginx -t`, `systemctl daemon-reload`, dan `systemctl enable --now`.
7. Migration **tidak** dijalankan kecuali operator memberikan flag eksplisit.

### 6. Migration database (gate terpisah)

Setelah backup database diverifikasi dan migration disetujui secara eksplisit:

```sh
sudo RUN_MIGRATIONS=1 RELEASE_ID=<release-id> \
  ./scripts/install-and-activate.sh
```

Jangan mengaktifkan migration berulang tanpa memahami status database. Script tidak menyediakan rollback migration otomatis.

### 7. Verifikasi service dan web

```sh
sudo systemctl status food-photo-backend food-photo-frontend nginx --no-pager
sudo journalctl -u food-photo-backend -u food-photo-frontend -n 100 --no-pager
curl -fsS http://127.0.0.1:8089/
curl -fsS http://127.0.0.1:8000/up
# akses dari client: http://172.18.20.148:8089/
```

Kemudian akses dari browser menggunakan `http://<server-host>:8089/`. Jika health check gagal, jangan hapus release lama; hentikan aktivasi dan rollback symlink ke release sebelumnya.

### 8. Rollback

Pertahankan semua direktori release. Untuk rollback aplikasi yang belum menjalankan migration inkompatibel:

```sh
sudo ln -sfn /opt/food-photo-studio/releases/<previous-release> /opt/food-photo-studio/current
sudo systemctl restart food-photo-backend food-photo-frontend
sudo nginx -t && sudo systemctl reload nginx
```

Detail prasyarat, backup, konfigurasi, dan batasan ada di [rencana deployment](docs/NATIVE_DEPLOYMENT_PLAN.md). Script tidak membuat secret, tidak menampilkan secret, dan tidak mengeksekusi Git hook.

## Bootstrap release (dependency saja)

Jika hanya ingin memasang dependency dan build tanpa mengaktifkan service, jalankan `DRY_RUN=1 ./scripts/install-release.sh --install`, lalu secara eksplisit `./scripts/install-release.sh --install`. Migrasi tetap manual dan memerlukan approval terpisah.


## Build dan test lokal

Gunakan lingkungan dan database lokal terisolasi, bukan data yang penting. Instal dependensi dari masing-masing direktori; versi PHP backend minimal 8.3 dengan ekstensi GD. Frontend memerlukan Node.js dan npm; gunakan versi Node yang kompatibel dengan dependensi di lockfile.

```sh
# Frontend (dari root)
cd frontend
npm ci
npm run lint
npm test
npm run build
cd ..

# Backend: baca backend/README.md sebelum instalasi atau migrasi
cd backend
composer install
composer test
cd ..

# Python: standard library
python3 -m unittest discover -s orchestrator/tests -v
(cd worker && PYTHONDONTWRITEBYTECODE=1 python3 -m unittest -v test_worker)
```

Test backend menggunakan konfigurasi SQLite in-memory dalam `phpunit.xml`; pastikan ekstensi PDO SQLite tersedia dan tidak ada konfigurasi lingkungan eksternal yang mengarah ke database penting. Test tidak membuktikan kompatibilitas database produksi. **Jangan jalankan `composer run setup` tanpa membaca peringatannya**: script tersebut menjalankan migrasi paksa dan mengubah konfigurasi lokal.

Prinsip produk dan rencana lebih lanjut ada di `docs/ROADMAP.md` dan `docs/WORKFLOW.md`. Lisensi: [MIT](LICENSE), Copyright (c) 2026 Netora.
