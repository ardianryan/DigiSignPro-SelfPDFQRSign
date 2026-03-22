<?php
require_once __DIR__ . '/../../includes/auth_session.php';
requireAdmin();

$page_title = "Update Aplikasi";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$lockFile = __DIR__ . '/../../config/version.lock';
$currentVersion = file_exists($lockFile) ? trim(file_get_contents($lockFile)) : '1.0.0';
?>

<div class="max-w-4xl mx-auto" x-data="updaterApp()">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-slate-800">Update System</h2>
        <p class="text-slate-500">Upgrade aplikasi ke versi terbaru melalui paket update (ZIP).</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Current Version Info -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 h-fit">
            <h3 class="text-lg font-semibold text-slate-700 mb-2">Versi Saat Ini</h3>
            <div class="flex items-center">
                <div class="text-4xl font-bold text-blue-600"><?php echo htmlspecialchars($currentVersion); ?></div>
                <div class="ml-4 px-3 py-1 bg-green-100 text-green-700 text-xs rounded-full">Production</div>
            </div>
            <div class="mt-6 p-4 bg-amber-50 border-l-4 border-amber-400 text-amber-800 rounded-r-lg">
                <div class="flex gap-3">
                    <svg class="w-5 h-5 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <div>
                        <p class="text-sm font-bold">Peringatan Penting!</p>
                        <p class="text-xs mt-1 leading-relaxed">Selalu lakukan backup database dan file aplikasi Anda sebelum melakukan pembaruan untuk mencegah kehilangan data jika terjadi kegagalan proses.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Update Form -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
            <h3 class="text-lg font-semibold text-slate-700 mb-4">Proses Update</h3>
            
            <!-- Step 1: Upload -->
            <div x-show="step === 'upload'" x-cloak>
                <div class="border-2 border-dashed border-slate-300 rounded-lg p-8 text-center hover:bg-slate-50 transition-colors">
                    <input type="file" id="update_file" accept=".zip" class="hidden" @change="fileSelected($event)">
                    <label for="update_file" class="cursor-pointer flex flex-col items-center">
                        <svg class="w-16 h-16 text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                        <span class="text-blue-600 font-bold hover:underline">Pilih File Paket Update (.zip)</span>
                        <span class="text-xs text-slate-400 mt-2" x-text="fileName || 'Upload file zip yang berisi manifest.json'"></span>
                    </label>
                </div>

                <button @click="analyzeUpdate()" :disabled="!file || isProcessing" class="w-full mt-6 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-xl disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-blue-200">
                    <span x-show="!isProcessing">Mulai Analisis Paket</span>
                    <span x-show="isProcessing">Menganalisis...</span>
                </button>
            </div>

            <!-- Step 2: Preview -->
            <div x-show="step === 'preview'" x-cloak class="space-y-4">
                <div class="bg-blue-50 p-4 rounded-lg flex items-center justify-between border border-blue-100">
                    <div>
                        <p class="text-xs text-blue-600 font-bold uppercase">Versi Baru Tersedia</p>
                        <p class="text-xl font-bold text-blue-800" x-text="manifest.version"></p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-blue-600 font-bold uppercase">Tanggal Rilis</p>
                        <p class="text-sm font-semibold text-blue-800" x-text="manifest.release_date"></p>
                    </div>
                </div>

                <div class="p-4 bg-slate-50 rounded-lg border border-slate-200">
                    <p class="text-sm font-bold text-slate-700 mb-2">Deskripsi Update:</p>
                    <p class="text-sm text-slate-600" x-text="manifest.description"></p>
                </div>

                <div>
                    <p class="text-sm font-bold text-slate-700 mb-2">File yang akan diperbarui:</p>
                    <div class="max-h-48 overflow-y-auto bg-slate-50 border border-slate-200 rounded-lg p-2 custom-scrollbar">
                        <template x-for="file in manifest.files">
                            <div class="flex items-center gap-2 p-1.5 text-xs text-slate-600 border-b border-slate-100 last:border-0">
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <span x-text="file"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="pt-4 flex gap-3">
                    <button @click="step = 'upload'; file = null; fileName = ''" class="flex-1 border border-slate-300 text-slate-700 font-bold py-3 px-4 rounded-xl hover:bg-slate-50 transition-colors">
                        Batal
                    </button>
                    <button @click="executeUpdate()" :disabled="isProcessing" class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-4 rounded-xl shadow-lg shadow-red-200 transition-all">
                        <span x-show="!isProcessing">Pasang Sekarang</span>
                        <span x-show="isProcessing">Memproses...</span>
                    </button>
                </div>
            </div>

            <!-- Processing / Spinner -->
            <div x-show="isProcessing && step !== 'upload' && step !== 'preview'" x-cloak class="text-center py-12">
                <div class="relative w-20 h-20 mx-auto mb-6">
                    <div class="absolute inset-0 border-4 border-blue-100 rounded-full"></div>
                    <div class="absolute inset-0 border-4 border-blue-600 rounded-full border-t-transparent animate-spin"></div>
                </div>
                <p class="text-lg font-bold text-slate-800" x-text="statusText"></p>
                <p class="text-sm text-slate-500 mt-2">Mohon jangan tinggalkan atau muat ulang halaman ini.</p>
            </div>

            <!-- Step 3: Success -->
            <div x-show="step === 'success'" x-cloak class="text-center py-8">
                <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <h4 class="text-2xl font-bold text-slate-800 mb-2">Update Berhasil!</h4>
                <p class="text-slate-600 mb-8" x-text="successMessage"></p>
                <button @click="window.location.reload()" class="w-full bg-slate-900 hover:bg-black text-white font-bold py-3 px-4 rounded-xl transition-all shadow-xl">
                    Selesai & Muat Ulang
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function updaterApp() {
    return {
        step: 'upload', // upload, preview, processing, success
        file: null,
        fileName: '',
        isProcessing: false,
        statusText: '',
        temp_id: '',
        manifest: { version: '', release_date: '', description: '', files: [] },
        successMessage: '',

        fileSelected(e) {
            this.file = e.target.files[0];
            if (this.file) {
                this.fileName = this.file.name;
            }
        },

        async analyzeUpdate() {
            if (!this.file) return;

            this.isProcessing = true;
            this.statusText = 'Mengunggah dan menganalisis paket...';

            const formData = new FormData();
            formData.append('update_file', this.file);
            formData.append('action', 'analyze');
            formData.append('csrf_token', '<?php echo get_csrf_token(); ?>');

            try {
                const response = await fetch('<?php echo BASE_URL; ?>/admin/process_update', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    this.manifest = result.manifest;
                    this.temp_id = result.temp_id;
                    this.step = 'preview';
                    this.isProcessing = false;
                } else {
                    Swal.fire('Analisis Gagal', result.message, 'error');
                    this.statusText = '';
                    this.isProcessing = false;
                }
            } catch (error) {
                console.error(error);
                Swal.fire('Error', 'Terjadi kesalahan koneksi.', 'error');
                this.isProcessing = false;
            }
        },

        async executeUpdate() {
            if (!this.temp_id) return;
            
            if (!confirm('Apakah Anda sudah yakin dan sudah melakukan backup? Proses ini akan menimpa file sistem.')) return;

            this.step = 'processing';
            this.isProcessing = true;
            this.statusText = 'Menerapkan pembaruan...';

            const formData = new FormData();
            formData.append('temp_id', this.temp_id);
            formData.append('action', 'execute');
            formData.append('csrf_token', '<?php echo get_csrf_token(); ?>');

            try {
                const response = await fetch('<?php echo BASE_URL; ?>/admin/process_update', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    this.statusText = 'Selesai!';
                    this.successMessage = result.message;
                    this.step = 'success';
                    this.isProcessing = false;
                } else {
                    Swal.fire('Update Gagal', result.message, 'error');
                    this.step = 'preview';
                    this.isProcessing = false;
                }
            } catch (error) {
                console.error(error);
                Swal.fire('Error', 'Terjadi kesalahan jaringan atau server saat eksekusi.', 'error');
                this.isProcessing = false;
                this.step = 'preview';
            }
        }
    }
}
</script>

<style>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>
