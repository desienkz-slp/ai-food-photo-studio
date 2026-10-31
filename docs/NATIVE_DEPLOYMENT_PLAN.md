# Rencana deployment native — draft, belum disetujui

Dokumen ini bukan izin melakukan perubahan server. Kondisi server, versi OS, kapasitas dan direktori yang telah ada harus diaudit ulang secara read-only sebelum perintah instalasi disusun. Jangan menaruh alamat internal, kredensial, atau file konfigurasi produksi di repository public.

## Tujuan

Menjalankan Laravel API, Next.js, queue dan image worker sebagai layanan terpisah; PostgreSQL, Redis, penyimpanan privat serta reverse proxy dilindungi dari akses publik langsung. Pilihan object storage dan topologi final belum ditetapkan. Server target memiliki kapasitas terbatas sehingga kelayakan resource harus dibuktikan dengan pengukuran, bukan diasumsikan.

## Prasyarat dan keputusan

1. Inventaris read-only OS, package manager, layanan, port, firewall, storage, pengguna dan direktori lama; jangan memeriksa atau menyalin secrets ke log.
2. Tentukan versi PHP/Composer, Node.js, Python, PostgreSQL dan Redis yang didukung dependency aplikasi dan sistem operasi; uji versi tersebut di staging sebelum instalasi server.
3. Tentukan TLS, nama domain, akses jaringan, user service dengan least privilege, penyimpanan foto privat, retensi, kuota, proteksi upload dan batas worker.
4. Inventaris dan backup direktori lama tanpa menghapusnya. Pastikan backup database, foto dan konfigurasi terenkripsi di lokasi terpisah, dengan restore drill ke lingkungan terisolasi.
5. Siapkan rencana migration expand/contract, dampak, waktu maintenance, pemeriksaan kompatibilitas dan rollback spesifik. Jangan menjalankan migrasi hanya karena dokumen ini tersedia.
6. Siapkan mekanisme distribusi secrets di luar Git, akses terbatas, redaksi log dan prosedur audit. Jangan mencatat nilai secrets.
7. Tentukan monitoring, batas biaya, alert, proses pemulihan, serta health/readiness yang tidak menganggap service sehat hanya karena proses hidup.

## Urutan yang diusulkan

Setelah review dan persetujuan eksplisit: backup terverifikasi dan restore drill; instal dependensi sistem yang dipin dan ditinjau; siapkan user/direktori dengan izin minimum; konfigurasi layanan dan database; deploy release yang dapat diverifikasi; jalankan migrasi hanya dengan izin eksplisit; verifikasi readiness dan smoke test; aktifkan akses sesuai kebijakan. Setiap langkah memerlukan perintah spesifik dan validasi yang cocok dengan OS aktual sebelum eksekusi.

## Bootstrap release eksplisit

Setelah persetujuan deployment, clone commit yang ditinjau ke direktori release baru yang unik dan verifikasi identitas commit; `git clone` tidak menjalankan installer atau hook. Dari root checkout, jalankan:

```sh
DRY_RUN=1 ./scripts/install-release.sh --install
./scripts/install-release.sh --install
```

`DRY_RUN=1` memeriksa keberadaan file lock dan command, tetapi tidak menguji versi/ekstensi, mengunduh dependensi, atau membangun aplikasi. Jalankan tanpa DRY_RUN hanya setelah peninjauan dan persetujuan: installer memerlukan PHP >= 8.3 dengan GD dan seluruh ekstensi yang diwajibkan lockfile, Composer, Node.js/npm yang kompatibel dengan lockfile, akses registry, dan ruang disk; platform Composer divalidasi sebelum instalasi. Composer plugins dan scripts dinonaktifkan, termasuk package discovery Laravel; npm install lifecycle scripts juga dinonaktifkan. `npm run build` secara eksplisit tetap mengeksekusi kode build dari checkout dan dependensi, sehingga tinjau dan percayai keduanya sebelum menjalankannya. Jika dependensi membutuhkan lifecycle scripts, build dapat gagal; jangan mengabaikan kegagalan. Proses ini tidak menyiapkan atau memulai worker/orchestrator, mengonfigurasi service, memigrasi database, atau membuktikan readiness. Package discovery dan konfigurasi runtime Laravel memerlukan review dan langkah terpisah yang disetujui.

Operator harus menyiapkan konfigurasi di luar Git melalui mekanisme rahasia yang disetujui: kunci aplikasi Laravel yang dikelola aman, konfigurasi database, session/queue/cache dan Redis bila dipakai, storage privat dan izin, URL/origin dan TLS, serta konfigurasi frontend yang diperlukan saat build/runtime. Siapkan kredensial provider hanya jika penggunaannya disetujui. Jangan mencetak nilai rahasia atau mengandalkan installer untuk membuat/membacanya; Next.js dapat memasukkan variabel publik ke bundle, jadi jangan berikan secret sebagai variabel publik. Lindungi konfigurasi dan log dengan hak akses minimum.

Migrasi produksi **tidak pernah otomatis**: perlu rencana expand/contract, backup dan restore drill tervalidasi, review dampak, serta persetujuan eksplisit terpisah sebelum operator menjalankannya secara manual. Jangan jalankan `composer run setup` karena menghasilkan key dan menjalankan migrasi. Instalasi sukses bukan izin aktivasi; endpoint fail-closed atau readiness yang belum terpenuhi tetap memblokir release.

## Validasi dan rollback

Verifikasi status service, akses yang diizinkan dan ditolak, endpoint health/readiness, isolasi workspace, upload aman, integritas original, job, approval dan ekspor; gunakan hanya data uji berizin. Uji kegagalan service dan pemulihan. Jika gagal, hentikan akses/release baru dan kembalikan versi sebelumnya; pulihkan database dan object storage dari backup yang sesuai bila perlu, dengan prosedur yang telah diuji dan otorisasi untuk perubahan data. Mencatat versi release dan hasil validasi tidak boleh mencakup secrets atau foto.

## Status

Draft saja. Belum ada instalasi, perubahan konfigurasi, migrasi atau deployment yang dilakukan melalui rencana ini. Fidelity pada foto F&B nyata dan sizing masih belum terbukti.
