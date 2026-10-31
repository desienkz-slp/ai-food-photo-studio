# Rencana perbaikan menuju real test

Status: draft operasional; belum mengubah server.

## Tujuan
Menyediakan release yang dapat di-clone ke `ai-image`, dijalankan native, dan diuji melalui browser dengan rollback yang jelas.

## Gate dan urutan

1. **Bekukan release lokal**
   - audit diff, secret, lisensi, migration, dan file yang akan dipublikasi;
   - jalankan suite backend/frontend/Python dari working tree final;
   - pastikan lifecycle `process -> candidate immutable -> approve exact candidate -> export exact bytes` tetap teruji.
2. **Validasi repository**
   - commit hanya artefak yang disetujui;
   - push ke GitHub;
   - verifikasi commit remote dan clean clone disposable.
3. **Audit server read-only**
   - verifikasi OS, CPU/RAM/disk, port, service, Git, PHP/Composer, Node/npm, PostgreSQL/Redis, dan direktori release;
   - jangan tampilkan secret.
4. **Persiapan native server**
   - siapkan backup/rollback dan konfigurasi secret di luar Git;
   - install runtime/web server hanya setelah target dan dampak tervalidasi;
   - clone release ke direktori unik, bukan `git pull` atau menimpa release lama.
5. **Aktivasi bertahap**
   - install dependency/build;
   - validasi konfigurasi tanpa mencetak secret;
   - migrasi hanya setelah backup dan persetujuan eksplisit;
   - aktifkan service/reverse proxy/worker dengan health check.
6. **Real test**
   - akses web via URL/port yang disepakati;
   - gunakan foto berizin;
   - uji login, upload, candidate, review, approval, export, download, dan isolasi workspace;
   - simpan bukti hasil tanpa menyimpan foto/secret ke Git.

## Rollback

- Pertahankan release sebelumnya.
- Hentikan aktivasi baru bila health check gagal.
- Kembalikan symlink/service ke release sebelumnya.
- Jangan menghapus release atau data sebelum pemulihan diverifikasi.

## Blocker saat ini

- Release commit belum dibuat/push.
- Audit command server belum berhasil dieksekusi melalui console NOC.
- Web service target belum terdeteksi dari browser.
- PostgreSQL/storage/queue/worker/backup/restore belum diverifikasi.
- `Definition of Ready` belum terpenuhi.
