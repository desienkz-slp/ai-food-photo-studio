# Kriteria siap untuk pilot internal

Status saat ini: **belum siap**. Checklist ini adalah rencana penerimaan; tiap item memerlukan bukti yang dapat diperiksa, bukan hanya pernyataan atau keluaran subagent.

- [ ] Requirement MVP MUST dipetakan ke kasus uji, hasil aktual, dan versi artefak; kekurangan dicatat.
- [ ] Upload aman: signature, format, ukuran/dimensi dan decoding; original immutable dan checksum tetap sama.
- [ ] Query dan akses asset/job/version dibatasi workspace; uji lintas-workspace dan otorisasi peran.
- [ ] Mask dan output dapat ditinjau; kegagalan dan mask tidak pasti masuk needs_review atau gagal aman.
- [ ] Foreground dilindungi, exposure dibatasi; seluruh output memiliki lineage, policy, versi dan status validasi.
- [ ] Approval eksplisit dibutuhkan sebelum ekspor final; uji bahwa draft/needs_review ditolak.
- [ ] Idempotency, pemulihan worker, bounded retry, limit biaya dan kesalahan provider diuji.
- [ ] Adapter protokol dan kapabilitas memiliki contract tests; provider eksternal dan pemrosesan data hanya dengan izin.
- [ ] Evaluasi manusia pada minimal lima foto F&B berizin; benchmark 20–50 foto dan dua konfigurasi sesuai gerbang P0 sebelum menetapkan kualitas produksi.
- [ ] Tidak ada isu keamanan Critical/High yang belum ditangani; secrets dan foto tidak masuk repo publik atau log.
- [ ] Akses jaringan privat dan konfigurasi layanan terverifikasi; rencana rollback disetujui; backup database dan object storage diuji melalui restore drill.
- [ ] Dokumentasi operasional, pemantauan, failure path, retensi, consent, dan biaya tersedia; perubahan production mendapat persetujuan eksplisit.

Tes sintetis hanya membuktikan perilaku teknis, bukan kualitas segmentasi atau fidelity pada foto makanan nyata.
