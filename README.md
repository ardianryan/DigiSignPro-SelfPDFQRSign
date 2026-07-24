# DigiSign Pro - Laravel + Inertia React Refactor Version

Ini adalah repositori versi refaktor dari aplikasi **DigiSign Pro** menggunakan **Laravel 11**, **Inertia.js**, dan **ReactJS (Tailwind CSS)**. Struktur kode ini dirancang agar memiliki tampilan yang sangat mirip dengan versi PHP Native saat ini, serta memuat seluruh fitur database dan penandatanganan berkas secara lengkap.

---

## Kebutuhan Sistem
* **PHP**: `>= 8.2`
* **Composer**: `>= 2.0`
* **Node.js**: `>= 18.0` & **NPM**
* **Database**: MySQL `>= 5.7` atau MariaDB `>= 10.3`

---

## Langkah Instalasi & Setup

Ikuti langkah-langkah berikut untuk memasang aplikasi di lingkungan lokal atau server Anda:

### 1. Pastikan Anda Berada di Direktori Utama Proyek

### 2. Pasang Dependensi PHP (Composer)
```bash
composer install
```

### 3. Pasang Dependensi Frontend (NPM)
Gunakan opsi `--legacy-peer-deps` untuk menghindari konflik versi Vite & React:
```bash
npm install --legacy-peer-deps
```

### 4. Salin Konfigurasi Environment (`.env`)
Salin file `.env.example` menjadi `.env`:
```bash
cp .env.example .env
```
Setelah disalin, buka file `.env` dan konfigurasikan koneksi database Anda:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nama_database_anda
DB_USERNAME=username_database
DB_PASSWORD=password_database
```

### 5. Generate Application Key
```bash
php artisan key:generate
```

### 6. Jalankan Migrasi Database
Jalankan migrasi untuk membuat tabel `users`, `app_settings`, dan `signatures` yang sesuai dengan skema versi PHP Native:
```bash
php artisan migrate
```

Migrasi bersifat **idempotent** dan mendukung **DB DigiSign lama**:
- Jika tabel sudah ada, hanya menambahkan kolom Laravel yang kurang (bukan drop data).
- Bisa dijalankan ulang lewat **Admin → Update App → Jalankan Migrasi Database**.
- Adapter legacy juga mengisi `document_name` kosong dan membuat tabel support (`sessions`, `password_reset_tokens`, cache, jobs).

**Import data lama (ringkas):**
1. Arahkan `.env` ke database MySQL lama, **atau** import dump `users` / `app_settings` / `signatures`.
2. Jalankan `php artisan migrate` (atau tombol migrasi di panel admin).
3. Pastikan file upload/S3 path masih valid.

### 7. Seed Data Awal (Admin + Settings)
```bash
php artisan db:seed
```

Akun default setelah seed:
| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@example.com` | `password` |
| User | `user@example.com` | `password` |

### 8. Storage Link (opsional, untuk logo/public files)
```bash
php artisan storage:link
```

> **Catatan:** Install wizard multi-step PHP native diganti alur standar Laravel di atas (lebih aman & portabel). Versi aplikasi tercatat di file `version.lock` (default `2.0.0`).

---

## Cara Menjalankan Aplikasi di Lokal

Jalankan dua perintah berikut di terminal terpisah:

* **Menjalankan Server Backend PHP**:
  ```bash
  php artisan serve
  ```
  Aplikasi akan berjalan di `http://127.0.0.1:8000`.

* **Menjalankan Compiling Asset Frontend (Vite)**:
  ```bash
  npm run dev
  ```

Untuk production build frontend:
```bash
npm run build
```

---

## Fitur & Pemetaan Struktur Kode Laravel

Berikut adalah tabel pemetaan halaman antarmuka versi PHP Native lama ke versi Laravel + React baru:

| Fitur / Halaman Native | File Native Lama | File Halaman React (Inertia) Baru |
| :--- | :--- | :--- |
| **Login & Register** | `views/auth/login.php` / `register.php` | `resources/js/Pages/Auth/Login.jsx` / `Register.jsx` |
| **Dashboard** | `views/dashboard.php` | `resources/js/Pages/Dashboard.jsx` |
| **Single Sign** | `views/sign/single.php` & `process_single.php` | `resources/js/Pages/Sign/Single.jsx` |
| **Bulk Sign** | `views/sign/bulk.php` & `process_bulk.php` | `resources/js/Pages/Sign/Bulk.jsx` |
| **Layanan TTE QR (Manual)** | `views/sign/qr_list.php` & `qr_create.php` | `resources/js/Pages/Sign/QrList.jsx` & `QrCreate.jsx` |
| **Riwayat** | `views/history.php` | `resources/js/Pages/History.jsx` |
| **Profil** | `views/profile.php` | `resources/js/Pages/Profile/Edit.jsx` |
| **Manajemen Pengguna** | `views/admin/users.php` | `resources/js/Pages/Admin/Users.jsx` |
| **Pengaturan Aplikasi** | `views/admin/settings.php` | `resources/js/Pages/Admin/Settings.jsx` |
| **Manajemen Storage** | `views/admin/storage.php` | `resources/js/Pages/Admin/Storage.jsx` |
| **Update App** | `views/admin/updater.php` | `resources/js/Pages/Admin/Updater.jsx` |
| **Backup & Restore** | `views/admin/backup.php` | `resources/js/Pages/Admin/Backup.jsx` |
| **Verifikasi Publik** | `views/verify/index.php` | `resources/js/Pages/Verify.jsx` |
| **Cookie Consent** | `includes/cookie_consent.php` | `resources/js/Components/CookieConsent.jsx` |

---

## Update App (ZIP Package)

Admin → **Sistem & Tools → Update App**.

Struktur paket ZIP:
```
manifest.json   # wajib
files/          # opsional — struktur file aplikasi yang ditimpa
update.sql      # opsional — SQL tambahan
```

Contoh `manifest.json`:
```json
{
  "version": "2.0.1",
  "release_date": "2026-07-24",
  "description": "Perbaikan minor & patch keamanan.",
  "author": "Dev Team"
}
```

File sensitif **tidak ditimpa**: `.env`, `vendor/`, `node_modules/`, `.git/`, log cache.

---

## Penanganan Migrasi Database via Web Panel
* **Menu**: Admin → Update App → Jalankan Migrasi Database  
* **Route**: `POST /admin/database/migrate` (admin only)  
* **Fungsi**: Memanggil `Artisan::call('migrate', ['--force' => true])` tanpa SSH.
