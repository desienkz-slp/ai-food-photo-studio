# Walkthrough — Halaman Login + Perbaikan "Belum bisa simpan pengaturan"

## Halaman Login (terbaru, commit `40e05df`)
Dibuat halaman login khusus tema **classic** yang menggerbang seluruh app:
- `AuthPanel` kini menerima `children`. Saat belum login → tampil layar penuh
  (card parchment/burgundy, judul "Selamat Datang", field Email/Password, tombol "Masuk").
- Saat sudah login → muncul **session bar** ("Sesi aktif" + Logout) dan konten workspace dirender.
- CSS baru: `.login-screen`, `.login-card`, `.session-bar` di `globals.css`.
- Tes diselaraskan; **16/16 vitest lulus**, `next build` sukses, deploy ke `ai-image` aktif.

Login (sama seperti sebelumnya): `admin@food-photo.local` / `ChangeMe123!` di http://172.18.20.148:8089/

---

# Walkthrough — Perbaikan "Belum bisa simpan pengaturan"

## Gejala
Tombol **Simpan pengaturan** tidak berfungsi; panel menampilkan
"Tidak ada workspace tersedia", dropdown workspace kosong.

## Investigasi (bukti nyata, bukan tebakan)
1. DB produksi kosong: `users=0`, `workspaces=0` → tidak ada akun & workspace.
2. Setelah seed akun, login tetap gagal: `/session` & `/workspaces` balas **404 Next.js**.
   - Sebab: nginx hanya proxy `/api/` & `/sanctum/` ke Laravel; rute
     `/csrf-cookie`, `/login`, `/session`, `/workspaces` jatuh ke frontend.
3. Setelah fix nginx, login balas **419 CSRF token mismatch** (direct & proxy sama).
4. Tabel `sessions` selalu **0 baris** atas request HTTP, padahal tulis via CLI (root) berhasil.
5. Tes tulis sebagai `www-data`:
   `attempt to write a readonly database`.
   - **Akar masalah:** direktori `shared/database` milik `root:root` (0755).
     SQLite butuh tulis **direktori** (untuk file `-wal`/`-journal`), bukan hanya file DB.
     Session gagal tersimpan diam-diam → token CSRF tak punya pasangan → 419.

## Perbaikan
| # | Masalah | Fix | Commit |
|---|---------|-----|--------|
| 1 | DB kosong (no user/workspace) | Seed akun admin + workspace + membership owner | (runtime) |
| 2 | nginx tak proxy rute backend | Tambah `location` regex untuk csrf-cookie/login/logout/session/workspaces/health | `7f5d8ff` |
| 3 | SQLite readonly (dir root-owned) | `chown -R www-data shared/database` + `chmod 0775` (live) & di install script | `04d70ce` |

## Verifikasi end-to-end (API nyata)
- Login: **HTTP 200** (direct `:8000` & proxy `:8089`)
- `GET /workspaces` → `{"data":[{"id":1,"name":"Dapur Utama"}]}`
- `POST /workspaces/1/providers` → **HTTP 201**, tersimpan dengan
  `communication_mode: "response"`
- Tabel `sessions` kini terisi saat request HTTP.

## Kredensial login (ubah segera)
- URL: http://172.18.20.148:8089/
- Email: `admin@food-photo.local`
- Password: `ChangeMe123!`

> [!WARNING]
> Ganti password default setelah login pertama.

## Cara pakai
1. Buka URL, login dengan kredensial di atas.
2. Pilih workspace **Dapur Utama** di dropdown.
3. Klik **Muat pengaturan** → isi provider → **Simpan pengaturan**.
