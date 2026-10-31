# Domain review lokal

Slice Python stdlib in-memory: `Review('v-001')` dimulai dalam status review; `approve()` menghasilkan snapshot approved baru; `export()` hanya mengembalikan identifier versi jika snapshot approved yang cocok persis tersedia, jika tidak `ExportDenied`. Misalnya `review.export(review.approve())`. Identifier adalah string non-kosong yang dibandingkan persis; pemanggil bertanggung jawab memastikan identifier benar-benar unik dan terikat ke isi immutable.

Ini hanya model keputusan lokal, bukan endpoint, penyimpanan, ekspor file, autentikasi, otorisasi, audit, atau enforcement menyeluruh. Objek dan approval dalam proses bukan bukti tepercaya; integrasi nyata memerlukan boundary tepercaya dan pengikatan versi ke konten. Tidak ada integrasi dengan komponen lain.

Jalankan dari root repo: `python -m unittest review_domain.test_review_domain -v`.
