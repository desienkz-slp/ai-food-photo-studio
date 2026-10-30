# Roadmap implementasi

Dokumen perencanaan, bukan bukti implementasi atau komitmen jadwal. Persyaratan dan keputusan detail berada pada dokumen sumber yang disimpan secara lokal.

## P0 — Discovery dan proof of concept

Inventaris lingkungan; siapkan dataset foto berizin; uji segmentasi dan compositing; lakukan smoke test minimal 5 foto sebelum benchmark representatif 20–50 foto. Bandingkan sekurangnya dua konfigurasi pada dataset yang sama dengan review manusia. Fidelity adalah gerbang wajib; biaya dan latency diukur jika tersedia. Pengiriman foto ke provider atau penggunaan API berbayar membutuhkan persetujuan khusus sebelumnya.

**Exit:** original utuh, semua hasil dapat ditelusuri dan direview, tidak ada perubahan produk kritis; hasil benchmark dan kegagalan dicatat. Ambang awal segmentasi minimal 4/5 pada sekurangnya 90% foto adalah usulan yang memerlukan konfirmasi pemilik.

## P1 — Foundation

Autentikasi, workspace, proyek, upload tervalidasi, original immutable, database, storage dan queue. Uji ownership lintas workspace, file berbahaya dan checksum. Kegiatan desain dapat disiapkan sambil PoC berjalan, tetapi UI lengkap mengikuti gerbang PoC.

## P2 — Vision dan pipeline gambar

Segmentasi, review mask, penyesuaian cahaya terbatas, compositing dan jalur kegagalan aman. Ekspor final tetap mensyaratkan approval; jangan menganggap ekspor prototipe sebagai fitur final.

## P3 — Agent dan provider

Chat Bahasa Indonesia, EditPlan tervalidasi, policy deny-by-default dan tool registry. Adapter terpisah untuk Chat Completions, Responses dan Messages; kemampuan image generation/editing dicatat terpisah dan diuji secara kontrak.

## P4 — Fidelity QA

Validasi independen, histori versi, approval, telemetry dan cost accounting; uji idempotency, bounded retries, kegagalan provider, budget dan pemulihan worker.

## P5 — Pilot internal

Rencana deployment dan rollback, akses privat, backup/restore teruji, observability, kontrol keamanan dan pengukuran KPI. Eksekusi deployment memerlukan persetujuan terpisah.

## P6 — Kesiapan SaaS

Evaluasi tenant management, quota, billing dan signup setelah pilot serta unit economics disetujui. Fitur ini bukan scope MVP.

## Keputusan terbuka

- Pilihan provider/model, persetujuan transfer foto, anggaran dan dataset.
- Toleransi exposure, confidence mask, kebijakan needs-review dan ukuran output.
- Retensi data, penyimpanan, backup terpisah, restore drill dan akses produksi.
- Prioritas P0 belum dipetakan eksplisit dari MUST/SHOULD dalam dokumen sumber; buat matriks yang disetujui sebelum mengklaim kelengkapan.
