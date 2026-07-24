# Rencana: Legacy DigiSign → Laravel

## Kenapa dipisah?

| | Session PHP native (lama) | Session Laravel (baru) |
|--|---------------------------|-------------------------|
| Penyimpanan | `$_SESSION` / file PHP | `sessions` table / file driver Laravel |
| Auth | custom cookie/session | Guard `web` + CSRF |
| Reset password | `users.reset_token` | `password_reset_tokens` |

Session **tidak bisa** dipindah 1:1. Setelah pindah stack, user **wajib login ulang**.

Karena itu cutover **bukan** fitur update harian.

---

## Tiga jalur (rencana enak)

```
┌─────────────────────────────────────────────────────────┐
│ 1. CI/CD                                                │
│    Deploy kode (git, build, restart PHP-FPM/container)  │
│    → setiap release                                     │
└─────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────┐
│ 2. Migrasi Database (harian)                            │
│    Admin → Migrasi Database + password admin            │
│    atau: php artisan migrate                            │
│    → setelah release yang menambah migration            │
└─────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────┐
│ 3. Cutover Legacy (SEKALI)                              │
│    CLI (disarankan, "di luar" request web):             │
│      php artisan digisign:legacy-cutover                │
│    atau form sekali di halaman Migrasi (hilang setelah) │
│    → hanya saat pindah dari DigiSign PHP native         │
└─────────────────────────────────────────────────────────┘
```

---

## Apa yang dilakukan cutover sekali?

1. Adapt skema DigiSign lama → kolom Laravel (idempotent)
2. `php artisan migrate --force`
3. Generate **API key** untuk user yang belum punya
4. Tulis lock:
   - file: `storage/app/legacy_cutover.lock`
   - kolom: `app_settings.legacy_cutover_at`
5. Setelah itu **tidak ditawarkan lagi** (kecuali `--force` di CLI)

**Tidak** memindahkan session aktif. User lama login ulang dengan email + password (bcrypt tetap valid).

---

## Langkah cutover (ops)

1. Backup DB + storage/uploads
2. Deploy kode Laravel (CI/CD)
3. Set `.env` ke DB lama (atau restore dump)
4. Jalankan **sekali**:
   ```bash
   php artisan digisign:legacy-cutover
   ```
   atau dry-run:
   ```bash
   php artisan digisign:legacy-cutover --dry-run
   ```
5. Cek login admin, generate key API di Profil, uji sign + verify
6. Update berikutnya: hanya CI/CD + migrasi harian

---

## Keamanan

- Web cutover: admin only + **password diisi ulang** + checkbox “user login ulang”
- CLI: akses shell server (lebih aman daripada buka di internet)
- ZIP app update: **sudah dihapus** (CI/CD)

---

## Fresh install Laravel (bukan dari legacy)

- Install biasa: `migrate` + `db:seed`
- Cutover **tidak wajib**; form cutover bisa diabaikan
- Atau jalankan cutover sekali di staging untuk menandai lock (opsional)
