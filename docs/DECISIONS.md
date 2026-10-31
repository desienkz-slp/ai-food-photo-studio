# Keputusan arsitektur dan isu terbuka

## Baseline dari dokumen sumber

- Internal-first dengan batas workspace dan jalur menuju SaaS.
- Next.js untuk frontend; Laravel sebagai API/orchestrator; PostgreSQL, Redis queue, object storage dan worker pemrosesan gambar. Deployment awal dipilih native tanpa container; sizing harus divalidasi terhadap kapasitas lingkungan aktual.
- Background-only compositing diutamakan untuk menjaga foreground; original immutable dan output versioned.
- Protokol percakapan dan kapabilitas image generation/editing dipisahkan melalui adapter dan registry.

## Isu yang memerlukan konfirmasi

1. **Gerbang PoC vs foundation:** rencana PoC meminta gate fidelity sebelum UI penuh. Roadmap foundation dapat didesain paralel; jangan melabeli UI siap produksi sebelum PoC lulus.
2. **Ekspor dan approval:** roadmap mencantumkan ekspor lebih awal daripada approval. Ekspor final tetap diblokir sampai approval yang diwajibkan tersedia; prototipe tidak menjadi jalur final.
3. **Full-image AI edit vs balanced:** eksperimen full-image hanya boleh dipertimbangkan sebagai benchmark terkontrol setelah persetujuan, bukan pengecualian aturan perlindungan produk di workflow balanced.
4. **Target dan kapasitas:** deployment native pada server yang ditentukan pemilik; pembagian layanan, storage/backup dan batas resource belum disetujui. Tidak mengasumsikan seluruh stack memadai pada satu host.
5. **Lisensi:** MIT, Copyright (c) 2026 Netora; lihat `LICENSE`.

## Provider endpoint security and migration

Provider configuration stores a display name and endpoint alongside provider type/model. API keys use Laravel encryption and are hidden from JSON responses. Endpoint validation requires HTTPS and rejects credentials, IP literals, localhost, cloud metadata, and `.internal` hosts. This makes no HTTP connection. SSRF gap: future clients need egress controls, DNS/rebinding defenses, private/link-local/loopback/IPv6 blocking, redirect blocking, and an explicit allowlist. Migration 2026_10_31_000007 adds nullable name and endpoint fields for compatibility.
