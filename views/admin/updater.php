<?php
require_once __DIR__ . '/../../includes/auth_session.php';
requireAdmin();

$page_title = "Update Aplikasi";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$lockFile = __DIR__ . '/../../config/version.lock';
$currentVersion = file_exists($lockFile) ? trim(file_get_contents($lockFile)) : '1.0.0';
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-slate-800">Update System</h2>
        <p class="text-slate-500">Upgrade aplikasi ke versi terbaru melalui paket update (ZIP).</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Current Version Info -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
            <h3 class="text-lg font-semibold text-slate-700 mb-2">Versi Saat Ini</h3>
            <div class="flex items-center">
                <div class="text-4xl font-bold text-blue-600"><?php echo htmlspecialchars($currentVersion); ?></div>
                <div class="ml-4 px-3 py-1 bg-green-100 text-green-700 text-xs rounded-full">Production</div>
            </div>
            <p class="mt-4 text-sm text-slate-500">
                Pastikan Anda selalu melakukan backup database dan file sebelum melakukan update.
            </p>
        </div>

        <!-- Update Form -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200" x-data="updaterApp()">
            <h3 class="text-lg font-semibold text-slate-700 mb-4">Upload Paket Update</h3>
            
            <div x-show="!isProcessing && !isSuccess">
                <div class="border-2 border-dashed border-slate-300 rounded-lg p-6 text-center hover:bg-slate-50 transition-colors">
                    <input type="file" id="update_file" accept=".zip" class="hidden" @change="fileSelected($event)">
                    <label for="update_file" class="cursor-pointer flex flex-col items-center">
                        <svg class="w-12 h-12 text-slate-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                        <span class="text-blue-600 font-medium hover:underline">Pilih File ZIP</span>
                        <span class="text-xs text-slate-400 mt-1" x-text="fileName || 'Format .zip dengan manifest.json'"></span>
                    </label>
                </div>

                <button @click="uploadUpdate()" :disabled="!file" class="w-full mt-4 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                    Install Update
                </button>
            </div>

            <!-- Progress State -->
            <div x-show="isProcessing" class="text-center py-8">
                <svg class="animate-spin h-10 w-10 text-blue-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <p class="text-slate-700 font-medium" x-text="statusText"></p>
                <p class="text-xs text-slate-500 mt-1">Jangan tutup halaman ini.</p>
            </div>

            <!-- Success State -->
            <div x-show="isSuccess" class="text-center py-6">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <h4 class="text-xl font-bold text-slate-800">Update Berhasil!</h4>
                <p class="text-slate-600 mt-2 mb-6" x-text="successMessage"></p>
                <button @click="window.location.reload()" class="bg-slate-800 text-white px-6 py-2 rounded-lg hover:bg-slate-900">
                    Refresh Halaman
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function updaterApp() {
    return {
        file: null,
        fileName: '',
        isProcessing: false,
        isSuccess: false,
        statusText: 'Mengupload paket...',
        successMessage: '',

        fileSelected(e) {
            this.file = e.target.files[0];
            if (this.file) {
                this.fileName = this.file.name;
            }
        },

        async uploadUpdate() {
            if (!this.file) return;

            this.isProcessing = true;
            this.statusText = 'Mengupload paket...';

            const formData = new FormData();
            formData.append('update_file', this.file);
            formData.append('csrf_token', '<?php echo get_csrf_token(); ?>');

            try {
                const response = await fetch('<?php echo BASE_URL; ?>/admin/process_update', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    this.statusText = 'Update selesai!';
                    this.successMessage = result.message;
                    this.isSuccess = true;
                    this.isProcessing = false;
                } else {
                    Swal.fire('Update Gagal', result.message, 'error');
                    this.isProcessing = false;
                }
            } catch (error) {
                console.error(error);
                Swal.fire('Error', 'Terjadi kesalahan jaringan atau server.', 'error');
                this.isProcessing = false;
            }
        }
    }
}
</script>
