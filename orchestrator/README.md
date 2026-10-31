# EditPlan v1 dan policy (prototipe)

Antarmuka `orchestrator.policy.evaluate(plan)` menerima objek Python yang berasal dari data JSON tepercaya-tipe (dict/list/string/integer), bukan teks instruksi bebas. Tidak ada parser bahasa alami atau provider: instruksi ambigu harus diklarifikasi sebelum membentuk plan. Field asing atau hilang ditolak, bukan diabaikan.

Contoh: `{"version": 1, "background": {"mode": "replace", "description": "meja marmer putih"}, "operations": []}`. Kontrak v1:

- `version`: integer 1 (bukan boolean).
- `background`: `{"mode":"keep"}` mempertahankan latar, `{"mode":"remove"}` menghapus latar, atau `{"mode":"replace","description":"..."}` mengganti latar dengan deskripsi tidak kosong, maksimal 500 karakter. Tidak ada field lain.
- `operations`: array maksimal 32 objek tepat dengan `target` dan `action`. `background`/`adjust_lighting`, serta `background` mode `remove`/`replace`, selalu ditolak tanpa bukti proteksi tervalidasi yang dapat diverifikasi; karena boundary eksekusi belum ada, hasilnya konservatif deny. `product`/`retouch` menghasilkan review, bukan izin eksekusi. Operasi lain, termasuk generasi produk, ditolak.
- `Decision.decision` adalah `allow`, `review`, atau `deny`, dan `reasons` adalah tuple alasan. Hanya `allow` memenuhi policy; `review` dan `deny` tidak boleh dieksekusi. Tidak ada mekanisme override atau persetujuan implisit.

Deskripsi latar adalah data bebas dan **tidak dianalisis secara semantik**; tidak menjamin ketiadaan instruksi tersembunyi. Konsumen harus membatasi penerapannya ke latar dengan mask tervalidasi dan menjaga produk/original; jika mask atau maksud tidak pasti, wajib review. Hasil policy bukan izin provider, bukti fidelity, persetujuan ekspor, maupun sistem AI siap produksi. Tidak ada eksekutor, segmentasi, parser chat, persistensi, atau integrasi di sini.

Jalankan dari root: `python3 -m unittest discover -s orchestrator/tests -v`.
