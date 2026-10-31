# Workflow: Edit → Build → Commit → Push → Deploy ke server ai-image

Berlaku untuk proyek **AI-Photo-Studio** (repo `desienkz-slp/ai-food-photo-studio`).

Setiap kali melakukan perubahan kode di lokal, WAJIB menyelesaikan siklus penuh berikut sebelum menyatakan tugas selesai:

## 1. Edit di lokal
- Ubah kode di `C:\Users\desie\OneDrive\Documents\AI-Photo-Studio`.
- Script shell (`.sh`) harus berakhiran LF (sudah dipaksa via `.gitattributes`). Jika mengedit di Windows, normalkan CRLF→LF sebelum commit.

## 2. Build / Validasi
- Shell script: `bash -n <file>` (syntax) lalu `DRY_RUN=1 bash <file>` bila didukung.
- Backend (Laravel): `composer install` + `php artisan test` bila relevan.
- Frontend (Next.js): `npm run build`.

## 3. Commit
- `git add <file>` lalu `git commit -m "<pesan jelas>"`.

## 4. Push ke GitHub
- `git push origin main`.
- Catat commit SHA hasil push.

## 5. Update ke server ai-image
- SSH: `root@172.18.20.148` (alias `ai-image`, key ed25519 passwordless).
- Deploy pakai single-file installer, **fetch via commit SHA** (bukan `main`) untuk hindari cache CDN raw.githubusercontent:
  ```bash
  ssh ai-image 'curl -4 -fsSL https://raw.githubusercontent.com/desienkz-slp/ai-food-photo-studio/<SHA>/scripts/install-and-activate.sh -o /tmp/install.sh && nohup bash /tmp/install.sh > /var/log/food-photo-install.log 2>&1 & echo LAUNCHED'
  ```
- Installer idempoten: deteksi PHP 8.4 / Node 20 yang sudah ada, clone release baru, composer+npm build, migrasi, aktifkan service, reload nginx.

## 6. Verifikasi (WAJIB, bukti nyata)
- `systemctl is-active food-photo-backend food-photo-frontend nginx` → ketiganya `active`.
- `curl -4 http://172.18.20.148:8089/` → `200`.
- `curl -4 http://127.0.0.1:8000/up` → `200`.

## Catatan lingkungan server (bukti terverifikasi 2026-10-31)
- Ubuntu 22.04. IPv6 rusak → apt pakai `Acquire::ForceIPv4`; PPA Ondrej ditambah manual (bukan `add-apt-repository`, yang hang via launchpadlib).
- PHP **8.4** wajib (composer.lock `symfony/clock` butuh `>=8.4.1`), bukan 8.3.
- Node 20 dari NodeSource; purge node12 distro dulu (konflik `libnode-dev`).
- `APP_ROOT` (`/opt/food-photo-studio`) harus mode 0755 agar `www-data` bisa traverse.
- Port publik: **8089** (nginx reverse proxy → frontend 3000, `/api` & `/sanctum` → backend 8000).
- Env backend: `/etc/food-photo-studio/backend.env` (SQLite, auto-generate saat pertama install; tidak ditimpa).
