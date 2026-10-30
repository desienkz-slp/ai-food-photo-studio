# Pemetaan awal requirement dan pengujian

Referensi ID berasal dari dokumen sumber BRD/PRD/TDS v1.0. Matriks ini adalah rencana pemetaan, bukan laporan hasil uji. Seluruh status: **belum diuji**.

| Bidang | Requirement | Bukti minimum yang direncanakan |
|---|---|---|
| Workspace dan akses | BR-001, BR-008, SEC-001–002 | Ownership, RBAC, uji isolasi lintas workspace T-009 |
| Upload dan original | PR-001–002, FG-001, SEC-004 | T-001–002; checksum dan permission; original tidak tertimpa |
| Chat dan background | BR-003–004, PR-003–004 | T-004, T-013; tambah uji pemetaan chat dan persistensi tiga mode |
| Fidelity dan mask | BR-002, PR-005–009, FG-002–008 | T-003–006, T-012; foreground deterministik, kasus transparan, mask review dan human review |
| Review, versi dan ekspor | BR-007, PR-010–012, FG-009–010 | T-010–011; tambah uji preview, approval wajib, penolakan ekspor tanpa approval dan lineage |
| Provider, usage dan biaya | BR-005–006, PR-013–015 | T-007–008, T-012–015; contract test terpisah dan rekonsiliasi usage jika tersedia |
| Benchmark | BR-009 | Minimal dua konfigurasi dan dataset sama; fidelity hard gate, kualitas, latency, reliability, cost per accepted image |
| Profil kategori | PR-016–017 | Tambah uji spesifik kategori F&B dan referensi gaya |

## Celah yang wajib diselesaikan

Dokumen sumber menyebut semua requirement P0 perlu test case, tetapi tidak memetakan prioritas P0 secara eksplisit dari MUST/SHOULD/LATER. Daftar T-001–015 juga belum mencakup seluruh acceptance secara langsung. Tetapkan pemetaan dan ID uji tambahan, lalu catat hasil aktual serta bukti untuk tiap requirement sebelum release.
