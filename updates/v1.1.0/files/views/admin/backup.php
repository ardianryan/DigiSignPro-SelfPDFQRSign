<?php
require_once __DIR__ . '/../../includes/auth_session.php';
requireAdmin();

$page_title = "Backup & Restore";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="max-w-4xl mx-auto" x-data="backupApp()">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-slate-800">Backup & Restore</h2>
        <p class="text-slate-500">Kelola data aplikasi dan media penyimpanan.</p>
    </div>

    <!-- Tabs -->
    <div class="flex border-b border-slate-200 mb-6">
        <button @click="activeTab = 'backup'" :class="activeTab === 'backup' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700'" class="px-6 py-3 border-b-2 font-medium transition-colors">
            Backup Data
        </button>
        <button @click="activeTab = 'restore'" :class="activeTab === 'restore' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700'" class="px-6 py-3 border-b-2 font-medium transition-colors">
            Restore Data
        </button>
    </div>

    <!-- Backup Section -->
    <div x-show="activeTab === 'backup'" class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <h3 class="text-lg font-semibold text-slate-700 mb-4">Backup Data</h3>
        <p class="text-sm text-slate-500 mb-6">Pilih komponen yang ingin Anda backup. Hasil backup akan berupa file ZIP yang berisi data JSON (database) dan file media.</p>

        <form action="<?php echo BASE_URL; ?>/admin/process_backup" method="POST">
            <div class="space-y-4 mb-6">
                <label class="flex items-center space-x-3 p-4 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50">
                    <input type="checkbox" name="backup_db" value="1" checked class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500">
                    <div>
                        <span class="block font-medium text-slate-700">Database (JSON)</span>
                        <span class="text-xs text-slate-500">Mengekspor semua tabel database ke format JSON.</span>
                    </div>
                </label>

                <label class="flex items-center space-x-3 p-4 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50">
                    <input type="checkbox" name="backup_media" value="1" checked class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500">
                    <div>
                        <span class="block font-medium text-slate-700">Media Files (Uploads)</span>
                        <span class="text-xs text-slate-500">Mencadangkan seluruh folder uploads/ (PDF, Tanda Tangan, dll).</span>
                    </div>
                </label>
            </div>

            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition-colors flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Download Backup (.zip)
            </button>
        </form>
    </div>

    <!-- Restore Section -->
    <div x-show="activeTab === 'restore'" class="bg-white p-6 rounded-xl shadow-sm border border-slate-200" style="display: none;">
        <h3 class="text-lg font-semibold text-slate-700 mb-4">Restore Data</h3>
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-yellow-700">
                        Warning: Proses restore akan <strong>MENIMPA</strong> data yang ada. Pastikan Anda telah melakukan backup sebelum melakukan restore.
                    </p>
                </div>
            </div>
        </div>

        <div x-show="!isProcessing && !isSuccess">
            <div class="border-2 border-dashed border-slate-300 rounded-lg p-6 text-center hover:bg-slate-50 transition-colors mb-6">
                <input type="file" id="restore_file" accept=".zip" class="hidden" @change="fileSelected($event)">
                <label for="restore_file" class="cursor-pointer flex flex-col items-center">
                    <svg class="w-12 h-12 text-slate-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                    <span class="text-blue-600 font-medium hover:underline">Pilih File Backup (.zip)</span>
                    <span class="text-xs text-slate-400 mt-1" x-text="fileName || 'Format .zip'"></span>
                </label>
            </div>

            <div class="space-y-4 mb-6" x-show="file">
                <p class="font-medium text-slate-700">Opsi Restore:</p>
                <label class="flex items-center space-x-3">
                    <input type="checkbox" x-model="restoreDb" class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500">
                    <span class="text-slate-700">Restore Database (jika ada)</span>
                </label>
                <label class="flex items-center space-x-3">
                    <input type="checkbox" x-model="restoreMedia" class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500">
                    <span class="text-slate-700">Restore Media Files (jika ada)</span>
                </label>
            </div>

            <button @click="startRestore()" :disabled="!file" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                Mulai Restore
            </button>
        </div>

        <!-- Progress State -->
        <div x-show="isProcessing" class="text-center py-8">
            <svg class="animate-spin h-10 w-10 text-blue-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            <p class="text-slate-700 font-medium" x-text="statusText"></p>
        </div>

        <!-- Success State -->
        <div x-show="isSuccess" class="text-center py-6">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <h4 class="text-xl font-bold text-slate-800">Restore Berhasil!</h4>
            <p class="text-slate-600 mt-2 mb-6" x-text="successMessage"></p>
            <button @click="window.location.reload()" class="bg-slate-800 text-white px-6 py-2 rounded-lg hover:bg-slate-900">
                Selesai
            </button>
        </div>
    </div>
</div>

<script>
function backupApp() {
    return {
        activeTab: 'backup',
        file: null,
        fileName: '',
        restoreDb: true,
        restoreMedia: true,
        isProcessing: false,
        isSuccess: false,
        statusText: 'Memproses...',
        successMessage: '',

        fileSelected(e) {
            this.file = e.target.files[0];
            if (this.file) {
                this.fileName = this.file.name;
            }
        },

        async startRestore() {
            if (!this.file) return;
            if (!confirm('Apakah Anda yakin ingin melakukan restore? Data yang ada akan ditimpa.')) return;

            this.isProcessing = true;
            this.statusText = 'Mengupload dan memproses backup...';

            const formData = new FormData();
            formData.append('backup_file', this.file);
            formData.append('restore_db', this.restoreDb ? '1' : '0');
            formData.append('restore_media', this.restoreMedia ? '1' : '0');

            try {
                const response = await fetch('<?php echo BASE_URL; ?>/admin/process_restore', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    this.isSuccess = true;
                    this.successMessage = result.message;
                } else {
                    alert('Error: ' + result.message);
                    this.isProcessing = false;
                }
            } catch (error) {
                alert('Terjadi kesalahan koneksi.');
                this.isProcessing = false;
            }
        }
    }
}
</script>
