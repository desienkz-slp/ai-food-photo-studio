# Walkthrough — Instalasi Produksi AI Food Photo Studio (Single-File Installer)

## Tujuan
Satu file `.sh` di git yang, saat dijalankan di server `ai-image` (172.18.20.148), memasang
seluruh dependency + web app (Laravel backend, Next.js frontend, Nginx) dan merilis ke produksi.

## Hasil Akhir — TERVERIFIKASI ✅
- Dijalankan bersih dari nol (tanpa intervensi manual) via commit SHA `8d5fc1c`.
- `systemctl is-active food-photo-backend food-photo-frontend nginx` → **active / active / active**.
- `curl http://172.18.20.148:8089/` → **200**, `<title>Food Photo Studio</title>`.
- `curl http://127.0.0.1:8000/up` → **200**.
- PHP **8.4.27**, Node **v20.20.2**, release aktif `/opt/food-photo-studio/releases/20261031113942`.

## File Tunggal
`scripts/install-and-activate.sh` — dijalankan:
```bash
ssh ai-image 'curl -4 -fsSL https://raw.githubusercontent.com/desienkz-slp/ai-food-photo-studio/main/scripts/install-and-activate.sh -o /tmp/install.sh && bash /tmp/install.sh'
```

## Yang Dilakukan Installer (idempoten)
1. Paksa apt IPv4 (`Acquire::ForceIPv4`) — server punya IPv6 rusak.
2. Tambah PPA Ondrej **manual** (keyserver + sources.list, `gpg --batch --no-tty`) — hindari `add-apt-repository`/launchpadlib yang hang.
3. Purge Node 12 distro → pasang Node 20 (NodeSource).
4. Pasang PHP **8.4** + ekstensi, Nginx, Composer.
5. Buat direktori app, pastikan `APP_ROOT` 0755 (agar `www-data` bisa traverse).
6. Nonaktifkan service legacy uvicorn bila ada.
7. Clone release, auto-generate `/etc/food-photo-studio/backend.env` (SQLite, `APP_KEY` via openssl) bila belum ada.
8. `composer install` + `npm ci && npm run build` (via `install-release.sh`).
9. `php artisan migrate --force` (default aktif).
10. Tulis systemd units + vhost Nginx (port 8089), restart service, reload nginx.

## Masalah yang Ditemukan & Diperbaiki (investigate-first, bukti dari server)
| Gejala | Akar Masalah | Perbaikan |
|---|---|---|
| `add-apt-repository` timeout | IPv6 rusak; launchpadlib hit API via IPv6 | apt ForceIPv4 + PPA manual |
| `dpkg: trying to overwrite .../common.gypi` | `libnode-dev` 12 konflik Node 20 | purge node12 sebelum install |
| `vendor/autoload.php` not found | `artisan key:generate` dipanggil sebelum composer | `APP_KEY` via `openssl rand` |
| `symfony/clock requires php >=8.4.1 failed` | composer.lock butuh PHP 8.4 | ganti 8.3 → **8.4** |
| `gpg: cannot open '/dev/tty'` | gpg butuh tty di bawah nohup | `gpg --batch --yes --no-tty` |
| service `200/CHDIR Permission denied` | `APP_ROOT` mode 700 user lain | chmod 0755 APP_ROOT |
| nginx tetap port 80 | `enable --now` tak reload nginx berjalan | `systemctl reload-or-restart nginx` |

## Commit Terkait (branch main)
`8fc8897` → `55320aa` (self-contained) → `65c8722` (.gitattributes LF) →
`531cec5` (ForceIPv4+PPA manual) → `a7350e8` (purge node12) → `feff9d0` (openssl APP_KEY) →
`29efa29` (PHP 8.4) → `858d344` (gpg no-tty) → `8d5fc1c` (reload nginx + restart services).

## Catatan
- Deploy ulang: fetch via **commit SHA** (bukan `main`) untuk hindari cache CDN raw GitHub.
- Rule workflow tersimpan di `.agents/rules/workflow-deploy.md`.
