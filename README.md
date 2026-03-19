# DigiSign - Sistem Tanda Tangan Elektronik & Verifikasi Dokumen

DigiSign adalah aplikasi berbasis web untuk manajemen tanda tangan elektronik (TTE) dan verifikasi dokumen. Aplikasi ini dirancang untuk memfasilitasi penandatanganan dokumen secara digital (Single & Bulk), pembuatan QR Code verifikasi (TTE QR), serta validasi keaslian dokumen.

## 🚀 Fitur Utama

### 1. Tanda Tangan Elektronik (Electronic Signature)
- **Single Sign**: Upload dan tanda tangani satu dokumen PDF dengan penempatan QR Code dan visual tanda tangan yang presisi.
- **Bulk Sign (Massal)**: Tanda tangani banyak dokumen sekaligus menggunakan template posisi yang sama. Mendukung ZIP output untuk kemudahan unduh.
- **TTE QR (Manual)**: Pembuatan QR Code verifikasi untuk ditempelkan secara manual pada dokumen fisik atau desain lain.

### 2. Keamanan & Verifikasi
- **Enkripsi PDF**: Dokumen yang ditandatangani diproteksi (read-only/paraphrase) untuk mencegah modifikasi pasca-tanda tangan.
- **QR Code Verifikasi**: Setiap dokumen memiliki QR Code unik yang mengarah ke halaman verifikasi online.
- **Validasi Dokumen**: Halaman publik untuk memverifikasi keaslian dokumen berdasarkan token unik.

### 3. Manajemen & Skalabilitas
- **Manajemen User**: Role-based access control (Admin & User).
- **Pengaturan Aplikasi**: Konfigurasi logo, zona waktu, batas upload, dll.
- **Sistem Update Terintegrasi**: Fitur update aplikasi berbasis ZIP untuk kemudahan maintenance dan scalability.

---

## 🛠 Tech Stack

- **Backend**: PHP Native - Ringan dan mudah dikustomisasi.
- **Database**: MySQL / MariaDB.
- **Frontend**: Tailwind CSS (UI), Alpine.js (Interaktivitas), Flowbite.
- **PDF Processing**: FPDF, FPDI, FPDI-Protection.
- **QR Code**: chillerlan/php-qrcode.

---

## 📈 Scalability & Update System

Aplikasi ini dilengkapi dengan **Sistem Update Otomatis** untuk memudahkan pengembangan jangka panjang (Scalability). Admin dapat memperbarui aplikasi tanpa harus mengakses server secara langsung via FTP/SSH.

### Cara Melakukan Update Aplikasi

1.  Login sebagai **Admin**.
2.  Masuk ke menu **Pengaturan > Update App** (atau via sidebar menu "Update App").
3.  Upload file paket update (`.zip`).
4.  Sistem akan otomatis:
    - Mengekstrak file baru.
    - Memperbarui struktur database (jika ada `update.sql`).
    - Memperbarui file sistem.
    - Mencatat versi baru.

### Struktur Paket Update (.zip)

Setiap paket update harus berupa file ZIP yang berisi:

1.  `manifest.json` (Wajib): Informasi meta data update.
2.  `files/` (Opsional): Folder berisi struktur file aplikasi yang akan ditambahkan/ditimpa.
3.  `update.sql` (Opsional): Skrip SQL untuk migrasi database.

**Contoh `manifest.json`:**
```json
{
  "version": "1.1.0",
  "release_date": "2025-02-01",
  "description": "Perbaikan bug pada fitur Bulk Sign dan penambahan filter pencarian.",
  "author": "Dev Team"
}
```

---

## 📝 Changelog

### v1.3.2 (Object Storage S3 & UI Enhancements) - 2026-03-19
**Fitur Baru & Perbaikan:**
- **S3/R2 Public URL**: Pemisahan antara Endpoint API S3 dan URL Publik untuk akses file. Berguna untuk Cloudflare R2 dengan custom domain atau public bucket URL.
- **Manajemen Storage UI**: Halaman baru untuk memantau isi bucket S3, statistik penggunaan, dan transparansi direktori aktif.
- **Auto-Migration DB**: Sistem secara otomatis mengecek dan menambahkan kolom database yang diperlukan saat menyimpan pengaturan.
- **Bugfix S3 URL**: Memperbaiki konstruksi URL S3/R2 agar tidak terjadi double domain (malformed URL).
- **Bugfix History Delete**: Memperbaiki error "Undefined variable: stmt" saat menghapus riwayat dokumen.
- **Production Hardening**: Penangkapan error yang lebih aman (Throwable) untuk mencegah halaman blank di lingkungan produksi.
- **Full SDK Package**: Paket update kini menyertakan folder `vendor` lengkap dengan AWS SDK untuk kemudahan instalasi.

### v1.3.0 (Feature Update) - 2026-03-19
**Fitur Baru & Perbaikan:**
- **S3/R2 Public URL**: Pemisahan antara Endpoint API S3 dan URL Publik untuk akses file. Berguna untuk Cloudflare R2 dengan custom domain atau public bucket URL.
- **Production Hardening**: Penanganan error yang lebih baik pada modul Storage dan penandatanganan untuk mencegah error 500 di lingkungan produksi.
- **Improved UI**: Penambahan field konfigurasi Public URL di halaman Pengaturan.
- **Full SDK Package**: Versi ini mendukung deployment dengan AWS SDK yang sudah terintegrasi dalam paket vendor.

### v1.1.2 (Patch) - 2026-02-10
**Perubahan & Perbaikan:**
- Drag & drop upload diaktifkan pada halaman Single Sign, Bulk Sign, dan TTE QR.
- Peningkatan format keterangan QR: nama penanda tangan lebih besar & bold, jabatan bold.
- Perataan keterangan QR disesuaikan:
  - Posisi kanan: rata tengah vertikal terhadap QR.
  - Posisi bawah: rata tengah horizontal terhadap QR.
- Penyesuaian jarak antara QR dan keterangan agar lebih dekat dan rapi.

**File yang diupdate:**
- `views/sign/single.php` (upload drag & drop, preview caption)
- `views/sign/bulk.php` (upload drag & drop ZIP, preview caption)
- `views/sign/qr_create.php` (upload drag & drop di Step 3)
- `views/sign/process_single.php` (logika render PDF untuk caption QR)
- `views/sign/process_bulk.php` (logika render PDF untuk caption QR)

**Panduan fitur terbaru:**
- Drag & Drop:
  - Single Sign: seret file PDF ke area upload atau klik Pilih File.
  - Bulk Sign: seret file ZIP ke area upload atau klik Pilih File ZIP.
  - TTE QR (Step 3): seret file PDF final ke area upload.
- Keterangan QR:
  - Aktifkan opsi “Tampilkan Keterangan QR”.
  - Pilih posisi “Di Bawah QR” atau “Di Samping Kanan”.
  - Nama penanda tangan ditampilkan lebih besar & bold, jabatan bold.

 ### v1.1.1 (Patch) - 2026-02-05
 **Perbaikan:**
 - Konsistensi respons JSON pada Bulk Sign (preview & proses) untuk mencegah error parsing.
 - Penanganan error di frontend saat respons bukan JSON murni.
 
### v1.1.0 (Feature Update) - 2026-01-28
**Fitur Baru:**
- **Backup & Restore**: Menambahkan fitur backup database (JSON) dan media files (ZIP) serta restore data melalui menu Admin.

### v1.0.0 (Production Release) - 2026-01-28
**Fitur Baru & Peningkatan:**
- **Core**: Rilis perdana versi Production.
- **Security**: Implementasi password parafrase **WAJIB** pada fitur TTE QR (sebelumnya opsional).
- **Filesystem**: Standardisasi format nama file output menjadi `PERIHAL_VERIFYCODE_signed.pdf` untuk kemudahan identifikasi.
- **Scalability**: Implementasi modul **Updater System** (UI & Backend) untuk memungkinkan update via upload ZIP.
- **Versioning**: Pemisahan manajemen versi ke file `version.lock` (versi `1.0.0`) agar terpisah dari `installed.lock` (timestamp instalasi).
- **UI/UX**: Perbaikan label dan validasi form pada halaman pembuatan QR.

---

## ⚙️ Instalasi

1.  Clone repository ini.
2.  Jalankan `composer install` untuk mengunduh dependensi.
3.  Buat database MySQL baru.
4.  Akses web (`/install`) untuk menjalankan Installation Wizard.
5.  Hapus folder `install/` setelah instalasi berhasil (atau sistem akan menguncinya via `installed.lock`).

---

## 📂 Struktur Direktori

```
digisign/
├── config/             # Koneksi DB, version.lock, installed.lock
├── includes/           # Komponen UI (Header, Sidebar)
├── public/             # Entry point web & Uploads
├── views/              # Halaman Frontend & Backend Logic
│   ├── admin/          # Fitur Admin (User, Settings, Updater)
│   ├── auth/           # Login/Register
│   ├── sign/           # Fitur Tanda Tangan (Single, Bulk, QR)
│   └── verify/         # Halaman Verifikasi Publik
├── vendor/             # Composer Dependencies
└── README.md           # Dokumentasi ini
```
