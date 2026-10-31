# AI Food Photography Studio

Prototipe aplikasi untuk membantu pengeditan foto makanan dan minuman dengan mengutamakan keaslian produk. **Belum siap release atau produksi**: komponen belum menjadi alur edit end-to-end, dan kualitas serta keamanan belum tervalidasi untuk penggunaan nyata.

## Status fitur

- `frontend/`: antarmuka Next.js lokal; belum terintegrasi dengan upload nyata atau layanan AI.
- `backend/`: prototipe Laravel untuk autentikasi sesi, workspace, konfigurasi provider, pemeriksaan update, dan upload aset; lihat [catatan backend](backend/README.md) untuk batasan penting.
- `orchestrator/`: prototipe policy EditPlan yang konservatif, bukan eksekutor atau parser instruksi alami.
- `worker/`: primitive compositing RGB dalam memori dengan mask biner, bukan worker pengolahan gambar end-to-end.
- Belum ada integrasi lengkap frontend–backend–worker, segmentasi tervalidasi, generasi gambar, ekspor final, atau bukti benchmark fidelity. Jangan menganggap policy atau mask sebagai jaminan perlindungan produk.

Penggunaan provider eksternal dan transfer foto memerlukan persetujuan tersendiri. Gunakan hanya data uji lokal; jangan masukkan secrets atau foto sensitif ke repository.

## Bootstrap release (eksplisit)

Clone sendiri tidak menjalankan instalasi atau Git hook. Setelah meninjau source dan lockfile pada checkout release baru, operator dapat menjalankan `DRY_RUN=1 ./scripts/install-release.sh --install`, lalu secara eksplisit `./scripts/install-release.sh --install`. Perintah kedua mengunduh dependensi dan menjalankan build frontend; jangan jalankan pada server tanpa persetujuan deployment. Lihat [rencana deployment](docs/NATIVE_DEPLOYMENT_PLAN.md) untuk prasyarat, konfigurasi eksternal, keterbatasan dan approval. Migrasi selalu manual dan memerlukan persetujuan terpisah; bootstrap tidak mengaktifkan layanan. Aplikasi tetap belum siap produksi.

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
