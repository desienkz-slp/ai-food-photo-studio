# Walkthrough — Editing Gambar AI (Fase 1–3, commit `a55614f`)

Pipeline aset diubah dari identity-only (output `.rgb` mentah) menjadi editing gambar AI gaya "GPT image panel".

## Backend
- **Migration non-destruktif** `2026_11_01_000016`: tambah kolom nullable `output_mime`, `output_ext`, `prompt` di `processing_jobs`. Baris lama tetap valid.
- **Kontrak `ImageEditor`** + value object `EditedImage` (bytes + mime + ext).
- **`LocalIdentityCompositor`** kini implement `ImageEditor` dan mengeluarkan **PNG nyata** (verifikasi identitas piksel via worker Python tetap jalan). Ini fallback aman.
- **`AiImageEditor`** baru: panggil provider OpenAI-compatible `POST {endpoint}/images/edits` (multipart image+prompt+model), `response_format=b64_json`. Timeout tegas: connect 5s, total 60s. Fail-closed (error → job `failed`, tak commit output). Tanpa prompt → fallback identity.
- **Feature flag** `services.model_providers.editing_enabled` (`MODEL_PROVIDER_EDITING_ENABLED`). Default `false` → identity. `AppServiceProvider` bind `ImageEditor` sesuai flag.
- **`ProcessingJobController`**: terima `prompt` (maks 2000), pakai `ImageEditor`, simpan PNG + mime/ext, verifikasi SHA-256.
- **`ImageServeController`** baru: `GET .../assets/{asset}/image` (asli) & `GET .../assets/{asset}/processing-jobs/{job}/output` (hasil). Verifikasi SHA sebelum serve, `nosniff`, `private, no-store`.
- **Export download**: serve dengan mime gambar asli (png/jpg) bila dikenali.

## Frontend
- **Image viewer**: preview foto asli per aset + preview hasil AI per kandidat (`<img>` ke endpoint serve).
- **Kolom prompt**: textarea "Instruksi edit AI (opsional)" per draft; tombol "Proses & setujui". Prompt dikirim ke processing-job.
- CSS classic baru: `.image-preview`, `.prompt-field`.

## Verifikasi nyata (server, commit `a55614f`)
- Migration terpasang: kolom `output_mime, output_ext, prompt` ada di `processing_jobs`.
- E2E alur: upload=201, versi=201, proses=201, job `succeeded`.
- **Output = PNG nyata** (`output_mime=image/png`, `file` → `PNG image data`).
- Serve gambar asli & hasil: `Content-Type: image/png` + `X-Content-Type-Options: nosniff`.
- Lokal: frontend 16/16 vitest lulus, PHP lint bersih, `next build` sukses.

## Cara mengaktifkan editing AI nyata
1. Superadmin isi provider (endpoint OpenAI-compatible + **API key valid** + model gambar).
2. Set env server `MODEL_PROVIDER_EDITING_ENABLED=true`, restart backend.
3. Upload foto → isi prompt → "Proses & setujui". Tanpa langkah 2, sistem tetap identity (hasil = asli).

## Catatan Fase 4 (belum)
Variasi/N-kandidat, pilihan ukuran, dan riwayat prompt belum dibuat (opsional, menyusul).

---


## Perubahan arsitektur provider (commit `3897b50`)
Provider AI kini **global**: diatur hanya oleh superadmin, dipakai semua tenant.

- **users**: kolom baru `is_super_admin` (boolean). superadmin=true.
- **provider_configurations**: `workspace_id` jadi nullable, unik per `type` (satu set global). Data lama diciutkan non-destruktif (keep newest per type).
- **Backend**: route baru `GET/POST /providers`. GET untuk semua user auth, POST hanya superadmin (403 untuk lainnya). `/session` kini mengembalikan `is_super_admin`.
- **nginx**: rute `/providers` di-proxy ke Laravel (sebelumnya jatuh ke Next.js → 404).
- **Frontend**: panel "Pengaturan provider" global (tanpa dropdown workspace) dan hanya tampil untuk superadmin.

### Verifikasi nyata (server)
- superadmin: `GET /providers`=200, `POST /providers`=200.
- user biasa: `GET`=200, `POST`=**403**.
- `/session` superadmin: `is_super_admin:true`.
- Tes: frontend 16/16 lulus, semua PHP lint bersih, `next build` sukses.

---

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
