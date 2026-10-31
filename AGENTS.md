# Aturan pengembangan

- Ikuti BRD/PRD/TDS dan `docs/TRACEABILITY.md`; jangan melemahkan acceptance gate untuk menyatakan pekerjaan selesai.
- Original immutable; simpan checksum dan lineage; jangan mengubah produk secara generatif dalam area yang dilindungi; kondisi tidak pasti wajib masuk review; ekspor final memerlukan approval eksplisit.
- Tulis test perilaku terlebih dahulu dan jalankan sampai gagal karena fitur belum tersedia, lalu implementasi minimum dan jalankan ulang; sertakan perintah dan hasil aktual.
- Foto asli, secrets, model weights, rincian infrastruktur internal dan audit tidak boleh masuk Git public atau log.
- Provider cloud, biaya dan transfer foto memerlukan persetujuan eksplisit. Jangan deploy, migrasi database, hapus data, rotasi secret, atau ubah production tanpa persetujuan dan rencana rollback.
- Beri setiap subagent brief lengkap, scope file eksklusif, acceptance criteria, batas tindakan dan bukti. Reviewer independen menilai perubahan; integrator memverifikasi ulang. Worktree bukan batas keamanan.
- Jangan mengklaim benchmark, tes, build, deployment, atau kesiapan berhasil tanpa output aktual. Uji sintetis tidak membuktikan kualitas pada foto makanan nyata.
