<?php
if (file_exists(__DIR__ . '/../../config/installed.lock')) {
    die("Aplikasi sudah terinstall. Hapus file config/installed.lock jika ingin menginstall ulang.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="../favicon.ico">
    <title>Instalasi DigiSign</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-xl w-full bg-white rounded-xl shadow-lg overflow-hidden" x-data="installer()">
        <!-- Header -->
        <div class="bg-blue-600 p-6 text-center">
            <h1 class="text-2xl font-bold text-white">Instalasi DigiSign</h1>
            <p class="text-blue-100 text-sm mt-1">Setup Database & Konfigurasi Awal</p>
        </div>

        <!-- Progress Bar -->
        <div class="bg-slate-200 h-1 w-full">
            <div class="bg-blue-500 h-1 transition-all duration-300" :style="'width: ' + progress + '%'"></div>
        </div>

        <div class="p-8">
            <!-- Step 1: Welcome -->
            <div x-show="step === 1">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 text-blue-600 mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    </div>
                    <h2 class="text-xl font-bold text-slate-800">Selamat Datang</h2>
                    <p class="text-slate-600 mt-2 text-sm">Wizard ini akan membantu Anda mengkonfigurasi database dan membuat akun administrator.</p>
                </div>

                <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200 mb-6 text-sm text-yellow-800">
                    <p class="font-bold mb-1">Prasyarat:</p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>PHP Versi 7.4 atau lebih baru</li>
                        <li>Ekstensi MySQLi aktif</li>
                        <li>Izin tulis (Write Permission) di folder <code>config/</code></li>
                    </ul>
                </div>

                <button @click="nextStep()" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition-colors">
                    Mulai Instalasi
                </button>
            </div>

            <!-- Step 2: Database Config -->
            <div x-show="step === 2" style="display: none;">
                <h2 class="text-xl font-bold text-slate-800 mb-6">Konfigurasi Database</h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Database Host</label>
                        <input type="text" x-model="db.host" class="w-full border border-slate-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500" placeholder="localhost">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Database Username</label>
                        <input type="text" x-model="db.username" class="w-full border border-slate-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500" placeholder="root">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Database Password</label>
                        <input type="password" x-model="db.password" class="w-full border border-slate-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500" placeholder="">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nama Database</label>
                        <input type="text" x-model="db.name" class="w-full border border-slate-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500" placeholder="digisign">
                        <p class="text-xs text-slate-500 mt-1">Jika belum ada, akan dibuat otomatis.</p>
                    </div>
                </div>

                <div class="mt-8 flex justify-between">
                    <button @click="step--" class="text-slate-500 hover:text-slate-700 font-medium">Kembali</button>
                    <button @click="testConnection()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition-colors flex items-center" :disabled="isLoading">
                        <span x-show="!isLoading">Lanjut</span>
                        <span x-show="isLoading" class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Memeriksa...
                        </span>
                    </button>
                </div>
            </div>

            <!-- Step 3: Admin Account -->
            <div x-show="step === 3" style="display: none;">
                <h2 class="text-xl font-bold text-slate-800 mb-6">Buat Akun Admin</h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nama Lengkap</label>
                        <input type="text" x-model="admin.name" class="w-full border border-slate-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                        <input type="email" x-model="admin.email" class="w-full border border-slate-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                        <input type="password" x-model="admin.password" class="w-full border border-slate-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div class="mt-8 flex justify-between">
                    <button @click="step--" class="text-slate-500 hover:text-slate-700 font-medium">Kembali</button>
                    <button @click="runInstall()" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg transition-colors flex items-center" :disabled="isInstalling">
                        <span x-show="!isInstalling">Instal Sekarang</span>
                        <span x-show="isInstalling" class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Menginstal...
                        </span>
                    </button>
                </div>
            </div>

            <!-- Step 4: Success -->
            <div x-show="step === 4" style="display: none;" class="text-center">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-green-100 text-green-600 mb-6">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <h2 class="text-2xl font-bold text-slate-800 mb-2">Instalasi Berhasil!</h2>
                <p class="text-slate-600 mb-8">DigiSign telah siap digunakan. Silakan login menggunakan akun admin yang baru saja dibuat.</p>
                
                <a href="../login" class="inline-block w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition-colors">
                    Ke Halaman Login
                </a>
            </div>
        </div>
    </div>

    <!-- Alpine.js -->
    <script src="//unpkg.com/alpinejs" defer></script>
    <script>
        function installer() {
            return {
                step: 1,
                progress: 25,
                isLoading: false,
                isInstalling: false,
                db: {
                    host: 'localhost',
                    username: 'root',
                    password: '',
                    name: 'digisign'
                },
                admin: {
                    name: 'Administrator',
                    email: 'admin@example.com',
                    password: ''
                },

                nextStep() {
                    this.step++;
                    this.progress = this.step * 25;
                },

                testConnection() {
                    if(!this.db.host || !this.db.username || !this.db.name) {
                        Swal.fire('Error', 'Mohon lengkapi data database', 'error');
                        return;
                    }

                    this.isLoading = true;
                    
                    fetch('/install/process.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            action: 'test_connection',
                            db: this.db
                        })
                    })
                    .then(async res => {
                        const ct = res.headers.get('content-type') || '';
                        if (!res.ok || !ct.includes('application/json')) {
                            const txt = await res.text();
                            throw new Error('Respon server bukan JSON (' + res.status + '): ' + txt.slice(0, 200));
                        }
                        return res.json();
                    })
                    .then(data => {
                        this.isLoading = false;
                        if(data.status === 'success') {
                            this.nextStep();
                        } else {
                            Swal.fire('Koneksi Gagal', data.message, 'error');
                        }
                    })
                    .catch(err => {
                        this.isLoading = false;
                        Swal.fire('Error', 'Terjadi kesalahan sistem', 'error');
                        console.error(err);
                    });
                },

                runInstall() {
                    if(!this.admin.email || !this.admin.password) {
                        Swal.fire('Error', 'Mohon lengkapi data admin', 'error');
                        return;
                    }

                    this.isInstalling = true;

                    fetch('/install/process.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            action: 'install',
                            db: this.db,
                            admin: this.admin
                        })
                    })
                    .then(async res => {
                        const ct = res.headers.get('content-type') || '';
                        if (!res.ok || !ct.includes('application/json')) {
                            const txt = await res.text();
                            throw new Error('Respon server bukan JSON (' + res.status + '): ' + txt.slice(0, 200));
                        }
                        return res.json();
                    })
                    .then(data => {
                        this.isInstalling = false;
                        if(data.status === 'success') {
                            this.nextStep();
                        } else {
                            Swal.fire('Instalasi Gagal', data.message, 'error');
                        }
                    })
                    .catch(err => {
                        this.isInstalling = false;
                        Swal.fire('Error', 'Terjadi kesalahan sistem', 'error');
                        console.error(err);
                    });
                }
            }
        }
    </script>
</body>
</html>
