# Backend Laravel (prototipe)

Backend menyediakan endpoint kesehatan, autentikasi sesi, akses workspace, konfigurasi provider, pemeriksaan update, dan upload aset. Ini belum merupakan pipeline pengeditan gambar end-to-end dan **belum siap release atau produksi**. Konfigurasi provider bukan persetujuan menggunakan provider atau mengirim foto.

## Setup dan test lokal

Gunakan PHP 8.3 atau lebih baru yang memenuhi `composer.json`, termasuk GD dan PDO SQLite, serta Composer. Dari direktori `backend/`:

```sh
composer install
composer test
```

`composer test` menjalankan `php artisan config:clear` lalu `php artisan test`. `phpunit.xml` mengatur SQLite in-memory dan driver uji lainnya; hindari konfigurasi eksternal yang dapat mengarahkan test ke database penting. `composer install` menjalankan Composer hooks, termasuk package discovery. Untuk build aset Laravel secara terpisah, dengan Node.js dan npm yang kompatibel:

```sh
npm ci --ignore-scripts
npm run build
```

**Peringatan:** `composer run setup` bukan perintah instalasi aman tanpa pemeriksaan. Script itu menjalankan `composer install`, membuat `.env` dari contoh jika belum ada, menjalankan `key:generate`, lalu `migrate --force` terhadap database yang dikonfigurasi, dan menginstal serta membangun aset npm. Migrasi dapat mengubah database; key generation juga dapat mengubah kunci aplikasi yang sudah ada. Jangan jalankan terhadap database atau konfigurasi yang penting. Siapkan lingkungan lokal terisolasi dan tinjau konfigurasi serta migrasi sebelum menjalankan operasi tersebut. Jangan commit konfigurasi lokal atau secrets.

## Batasan upload

`POST /workspaces/{workspace}/assets` memerlukan header `Idempotency-Key`: 1–128 karakter ASCII berupa huruf, angka, `.`, `_`, `~`, atau `-`. Key berlaku per workspace dan perbandingan huruf besar/kecil mengikuti collation database; diperlukan collation case-sensitive untuk perilaku konsisten. Identitas payload adalah SHA-256 byte file, bukan metadata seperti nama file. Retry yang sudah committed dengan byte sama mengembalikan respons 201 asli; payload berbeda atau upload cocok yang masih pending mengembalikan 409. Retry pending tidak menulis atau menghapus original. Baris lama dapat memiliki key null; upload baru wajib menggunakan key.

Unique index menjadi mekanisme reservasi, tetapi request paralel dan perilaku isolasi, collation, lock, serta exception spesifik database belum diuji terhadap database produksi. Jika pembaruan status ambigu atau memengaruhi nol baris, file tetap disimpan agar original tidak terhapus setelah kemungkinan commit. Rekonsiliasi pending/orphan dan pemulihan crash belum diimplementasikan; baris pending dapat tertinggal tanpa batas, termasuk setelah kesalahan storage. Jangan hapus file berdasarkan hasil database yang ambigu. Migrasi tersedia, tetapi belum diverifikasi pada database nyata.
