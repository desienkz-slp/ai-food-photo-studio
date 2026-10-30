# Workflow pengembangan dan subagent

Dokumen ini mengatur proses pengembangan, bukan arsitektur agent pada runtime aplikasi.

## Siklus kerja

1. Orkestrator menyusun brief per perubahan: tujuan, ID requirement, batas scope, acceptance criteria, risiko, larangan, bukti uji dan kondisi eskalasi.
2. Planner/reviewer memeriksa brief terhadap dokumen sumber; handoff tidak boleh melemahkan kriteria tanpa keputusan pemilik.
3. Builder bekerja pada scope yang terisolasi dengan satu penulis per file. Gunakan worktree/branch terpisah bila kerja paralel; worktree bukan sandbox keamanan dan tidak otomatis mengisolasi kredensial atau akses jaringan.
4. Tulis dan jalankan test gagal terlebih dahulu untuk setiap perilaku baru, lalu implementasi minimum dan ulangi suite yang relevan. Laporkan perintah serta output nyata.
5. Reviewer independen menilai diff, keamanan, fidelity, kontrak dan hasil uji. Integrator memverifikasi ulang, menggabungkan perubahan kecil, dan tidak menandai selesai jika bukti tidak ada atau ada blocker.
6. Operasi server, publikasi dan deployment tidak didelegasikan otomatis; rencana, backup, rollback, validasi dan izin yang relevan ditinjau terpisah.

## Batas operasional

- Jangan mencatat secrets, data foto privat atau detail infrastruktur internal dalam repository public.
- Jangan menjalankan API berbayar atau mengirim foto eksternal tanpa persetujuan provider, model, budget dan transfer data.
- Jangan deploy, migrasi database, menghapus data, merotasi secret atau mengubah konfigurasi production tanpa persetujuan eksplisit.
- Subagent harus diberi konteks lengkap; laporan subagent diperiksa kembali oleh orkestrator. Hasil review statis bukan bukti eksekusi.
- Tentukan durasi, cakupan, anggaran dan kriteria stop; hentikan dan eskalasi jika ada konflik atau hasil ambigu.

## Catatan pelajaran

Review historis menemukan bahwa instruksi builder dapat menyimpang dari gate planner. Karena itu requirement dan bukti acceptance menjadi kontrak kanonis, dan setiap revisi handoff wajib diperiksa sebelum tindakan berisiko.
