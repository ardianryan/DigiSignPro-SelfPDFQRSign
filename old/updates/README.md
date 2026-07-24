# Update Packages

Folder ini akan digunakan untuk menyimpan paket update yang dihasilkan setiap kali ada perubahan kode.
Setiap folder versi (contoh: `v1.1.0/`) akan berisi:

1. `manifest.json` - Metadata update.
2. `files/` - Struktur folder yang berisi file yang diubah/ditambah.
3. `update.sql` - (Opsional) Query database jika ada perubahan struktur DB.

Anda dapat meng-zip isi folder versi tersebut untuk diupload ke menu **Update App** di production.
