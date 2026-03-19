<?php
require_once __DIR__ . '/../../includes/auth_session.php';
requireAdmin();
require_once __DIR__ . '/../../includes/Storage.php';

// Handle AJAX Test S3 Connection - MUST BE BEFORE ANY HTML OUTPUT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_s3') {
    header('Content-Type: application/json');
    $testSettings = [
        's3_endpoint' => $_POST['s3_endpoint'] ?? '',
        's3_region' => $_POST['s3_region'] ?? '',
        's3_bucket' => $_POST['s3_bucket'] ?? '',
        's3_access_key' => $_POST['s3_access_key'] ?? '',
        's3_secret_key' => $_POST['s3_secret_key'] ?? '',
    ];
    $result = Storage::testConnection($conn, $testSettings);
    echo json_encode($result);
    exit;
}

$success_msg = '';
$error_msg = '';

$page_title = "Pengaturan Aplikasi";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Handle Delete Logo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_logo') {
    $res = $conn->query("SELECT app_logo FROM app_settings WHERE id = 1");
    $cur = $res ? $res->fetch_assoc() : null;
    $logo_rel = $cur['app_logo'] ?? null;
    if ($logo_rel) {
        $logo_file = __DIR__ . '/../../public/' . $logo_rel;
        if (file_exists($logo_file)) {
            @unlink($logo_file);
        }
    }
    $stmt = $conn->prepare("UPDATE app_settings SET app_logo = NULL WHERE id = 1");
    if ($stmt && $stmt->execute()) {
        $success_msg = "Logo aplikasi berhasil dihapus. Menggunakan default tanpa logo.";
    } else {
        $error_msg = "Gagal menghapus logo aplikasi: " . $conn->error;
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $app_name = $_POST['app_name'];
    $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
    $registration_open = isset($_POST['registration_open']) ? 1 : 0;
    $max_upload_mb = isset($_POST['max_upload_size_mb']) ? (int)$_POST['max_upload_size_mb'] : 10;
    $max_upload_bytes = $max_upload_mb * 1048576; // Convert MB to Bytes
    
    $max_upload_bulk_mb = isset($_POST['max_upload_size_bulk_mb']) ? (int)$_POST['max_upload_size_bulk_mb'] : 50;
    $max_upload_bulk_bytes = $max_upload_bulk_mb * 1048576; // Convert MB to Bytes
    
    // Timezone handling (Indonesia options only)
    $allowed_tz = ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'];
    $timezone = $_POST['timezone'] ?? 'Asia/Jakarta';
    if (!in_array($timezone, $allowed_tz, true)) {
        $timezone = 'Asia/Jakarta';
    }
    $max_prefix_length = isset($_POST['max_prefix_length']) ? (int)$_POST['max_prefix_length'] : 3;
    if ($max_prefix_length < 2) $max_prefix_length = 2;
    if ($max_prefix_length > 9) $max_prefix_length = 9;
    // Ensure DB column length supports the configured max prefix length
    try {
        if ($resCol = @$conn->query("SHOW COLUMNS FROM users LIKE 'signature_prefix'")) {
            if ($resCol->num_rows > 0) {
                $colInfo = $resCol->fetch_assoc();
                $type = $colInfo['Type'] ?? 'varchar(3)';
                if (preg_match('/varchar\((\d+)\)/i', $type, $m)) {
                    $currentLen = (int)$m[1];
                    if ($currentLen < $max_prefix_length) {
                        @$conn->query("ALTER TABLE users MODIFY signature_prefix VARCHAR($max_prefix_length) DEFAULT 'DS'");
                    }
                }
            } else {
                // Column missing: add with adequate length
                @$conn->query("ALTER TABLE users ADD COLUMN signature_prefix VARCHAR($max_prefix_length) DEFAULT 'DS' AFTER signature_path");
            }
        }
    } catch (Exception $e) {
        // silently ignore schema adjustment errors to not block settings save
    }
    
    // Handle Logo Upload
    $logo_path = null;
    if (isset($_FILES['app_logo']) && $_FILES['app_logo']['error'] == 0) {
        $target_dir = __DIR__ . "/../../public/assets/images/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['app_logo']['name'], PATHINFO_EXTENSION);
        $new_filename = "logo_" . time() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['app_logo']['tmp_name'], $target_file)) {
            $logo_path = "assets/images/" . $new_filename;
        } else {
            $error_msg = "Gagal mengupload logo.";
        }
    }

    if (!$error_msg) {
        $hasTz = false;
        if ($col = @$conn->query("SHOW COLUMNS FROM app_settings LIKE 'timezone'")) {
            $hasTz = $col->num_rows > 0;
        }
        if (!$hasTz) {
            @$conn->query("ALTER TABLE app_settings ADD COLUMN timezone VARCHAR(64) DEFAULT 'Asia/Jakarta'");
            if ($col2 = @$conn->query("SHOW COLUMNS FROM app_settings LIKE 'timezone'")) {
                $hasTz = $col2->num_rows > 0;
            }
        }
        $hasPrefixLen = false;
        if ($colp = @$conn->query("SHOW COLUMNS FROM app_settings LIKE 'max_prefix_length'")) {
            $hasPrefixLen = $colp->num_rows > 0;
        }
        if (!$hasPrefixLen) {
            @$conn->query("ALTER TABLE app_settings ADD COLUMN max_prefix_length INT DEFAULT 3");
            if ($colp2 = @$conn->query("SHOW COLUMNS FROM app_settings LIKE 'max_prefix_length'")) {
                $hasPrefixLen = $colp2->num_rows > 0;
            }
        }
        
        // Dynamic Update Query Construction
        $fields = ["app_name=?", "maintenance_mode=?", "registration_open=?", "max_upload_size=?", "max_upload_size_bulk=?"];
        $types = "siiii";
        $params = [$app_name, $maintenance_mode, $registration_open, $max_upload_bytes, $max_upload_bulk_bytes];

        if ($logo_path) {
            $fields[] = "app_logo=?";
            $types .= "s";
            $params[] = $logo_path;
        }

        if ($hasTz) {
            $fields[] = "timezone=?";
            $types .= "s";
            $params[] = $timezone;
        }

        if ($hasPrefixLen) {
            $fields[] = "max_prefix_length=?";
            $types .= "i";
            $params[] = $max_prefix_length;
        }

        // S3 Settings Handling
        $fields[] = "storage_mode=?";
        $fields[] = "s3_endpoint=?";
        $fields[] = "s3_region=?";
        $fields[] = "s3_bucket=?";
        $fields[] = "s3_access_key=?";
        $fields[] = "s3_secret_key=?";
        $fields[] = "s3_directory=?";
        $fields[] = "s3_public_url=?";
        $types .= "ssssssss";
        $params[] = $_POST['storage_mode'] ?? 'local';
        $params[] = $_POST['s3_endpoint'] ?? NULL;
        $params[] = $_POST['s3_region'] ?? 'us-east-1';
        $params[] = $_POST['s3_bucket'] ?? NULL;
        $params[] = $_POST['s3_access_key'] ?? NULL;
        $params[] = $_POST['s3_secret_key'] ?? NULL;
        $params[] = $_POST['s3_directory'] ?? 'digisign/';
        $params[] = $_POST['s3_public_url'] ?? NULL;
        
        $sql = "UPDATE app_settings SET " . implode(", ", $fields) . " WHERE id=1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $success_msg = "Pengaturan berhasil disimpan.";
        } else {
            $error_msg = "Gagal menyimpan pengaturan: " . $conn->error;
        }
    }
}

// Handle Clear Temp
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_temp') {
    $tempDir = __DIR__ . '/../../public/uploads/temp/';
    $deletedCount = 0;
    
    if (is_dir($tempDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            if ($todo($fileinfo->getRealPath())) {
                if ($todo === 'unlink') $deletedCount++;
            }
        }
        $success_msg = "Berhasil membersihkan $deletedCount file temporary.";
    } else {
        $error_msg = "Folder temp tidak ditemukan.";
    }
}

// Helper for Temp Stats
function getTempStats() {
    $dir = __DIR__ . '/../../public/uploads/temp/';
    $size = 0;
    $count = 0;
    if (!is_dir($dir)) return ['size' => 0, 'count' => 0];
    
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
        $size += $file->getSize();
        $count++;
    }
    return ['size' => $size, 'count' => $count];
}

function formatBytes($bytes, $precision = 2) { 
    $units = array('B', 'KB', 'MB', 'GB', 'TB'); 
    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 
    $bytes /= pow(1024, $pow); 
    return round($bytes, $precision) . ' ' . $units[$pow]; 
}

$tempStats = getTempStats();

// Fetch Current Settings
$result = $conn->query("SELECT * FROM app_settings WHERE id = 1");
$settings = $result->fetch_assoc();
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-slate-800">Pengaturan Aplikasi</h2>
        <p class="text-slate-500">Konfigurasi umum sistem DigiSign Pro</p>
    </div>

    <?php if($success_msg): ?>
        <script>
            Swal.fire({ icon: 'success', title: 'Berhasil', text: '<?php echo $success_msg; ?>', timer: 1500, showConfirmButton: false });
        </script>
    <?php endif; ?>
    <?php if($error_msg): ?>
        <script>
            Swal.fire({ icon: 'error', title: 'Error', text: '<?php echo $error_msg; ?>' });
        </script>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="bg-white rounded-xl shadow-sm border border-slate-200 p-8">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Left Column: General Info -->
            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nama Aplikasi</label>
                    <input type="text" name="app_name" value="<?php echo htmlspecialchars($settings['app_name']); ?>" required class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Maksimal Upload Single Sign (MB)</label>
                    <input type="number" name="max_upload_size_mb" value="<?php echo round(($settings['max_upload_size'] ?? 10485760) / 1048576); ?>" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-slate-500 mt-1">Batas ukuran file PDF untuk Single Sign.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Maksimal Upload Bulk Sign (MB)</label>
                    <input type="number" name="max_upload_size_bulk_mb" value="<?php echo round(($settings['max_upload_size_bulk'] ?? 52428800) / 1048576); ?>" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-slate-500 mt-1">Batas ukuran file ZIP untuk Bulk Sign.</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Maksimal Panjang Prefix Tanda Tangan</label>
                    <input type="number" name="max_prefix_length" min="2" max="9" value="<?php echo $settings['max_prefix_length'] ?? 3; ?>" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-slate-500 mt-1">Jumlah karakter maksimal untuk prefix tanda tangan user (Min: 2, Max: 9, Default: 3).</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Zona Waktu Indonesia</label>
                    <?php $current_tz = $settings['timezone'] ?? 'Asia/Jakarta'; ?>
                    <select name="timezone" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="Asia/Jakarta" <?php echo $current_tz === 'Asia/Jakarta' ? 'selected' : ''; ?>>Asia/Jakarta (WIB)</option>
                        <option value="Asia/Makassar" <?php echo $current_tz === 'Asia/Makassar' ? 'selected' : ''; ?>>Asia/Makassar (WITA)</option>
                        <option value="Asia/Jayapura" <?php echo $current_tz === 'Asia/Jayapura' ? 'selected' : ''; ?>>Asia/Jayapura (WIT)</option>
                    </select>
                    <p class="text-xs text-slate-500 mt-1">Pengaturan zona waktu mempengaruhi waktu pada aplikasi.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Logo Aplikasi</label>
                    <div class="flex items-center space-x-4">
                        <?php if($settings['app_logo']): ?>
                            <img src="<?php echo BASE_URL . '/' . $settings['app_logo']; ?>" alt="Current Logo" class="h-12 w-auto object-contain bg-slate-100 rounded p-1">
                            <form method="POST" onsubmit="return confirm('Hapus logo aplikasi dan kembali ke default?');">
                                <input type="hidden" name="action" value="delete_logo">
                                <button type="submit" class="bg-red-50 text-red-600 hover:bg-red-100 px-3 py-2 rounded text-xs font-medium transition-colors">
                                    Hapus Logo
                                </button>
                            </form>
                        <?php endif; ?>
                        <input type="file" name="app_logo" accept="image/*" class="block w-full text-sm text-slate-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-full file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100
                        "/>
                    </div>
                </div>
            </div>

            <!-- Right Column: Toggles -->
            <div class="space-y-6 border-l pl-8 border-slate-100">
                
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-medium text-slate-900">Maintenance Mode</h3>
                        <p class="text-xs text-slate-500">Jika aktif, hanya Admin yang bisa login.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="maintenance_mode" class="sr-only peer" <?php echo $settings['maintenance_mode'] ? 'checked' : ''; ?>>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-medium text-slate-900">Pendaftaran User</h3>
                        <p class="text-xs text-slate-500">Izinkan pengguna baru mendaftar (Login page).</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="registration_open" class="sr-only peer" <?php echo $settings['registration_open'] ? 'checked' : ''; ?>>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <div class="pt-6 border-t border-slate-100" 
                     x-data="{ 
                        mode: '<?php echo $settings['storage_mode'] ?? 'local'; ?>',
                        endpoint: '<?php echo htmlspecialchars($settings['s3_endpoint'] ?? ''); ?>',
                        bucket: '<?php echo htmlspecialchars($settings['s3_bucket'] ?? ''); ?>',
                        region: '<?php echo htmlspecialchars($settings['s3_region'] ?? 'auto'); ?>',
                        directory: '<?php echo htmlspecialchars($settings['s3_directory'] ?? 'digisign/'); ?>',
                        access_key: '<?php echo htmlspecialchars($settings['s3_access_key'] ?? ''); ?>',
                        secret_key: '<?php echo htmlspecialchars($settings['s3_secret_key'] ?? ''); ?>',
                        public_url: '<?php echo htmlspecialchars($settings['s3_public_url'] ?? ''); ?>',
                        testing: false,
                        async testS3Connection() {
                            if (!this.endpoint || !this.bucket || !this.access_key || !this.secret_key) {
                                Swal.fire({ icon: 'warning', title: 'Data Belum Lengkap', text: 'Silakan isi Endpoint, Bucket, dan Keys terlebih dahulu.' });
                                return;
                            }
                            this.testing = true;
                            const formData = new FormData();
                            formData.append('action', 'test_s3');
                            formData.append('s3_endpoint', this.endpoint);
                            formData.append('s3_region', this.region);
                            formData.append('s3_bucket', this.bucket);
                            formData.append('s3_access_key', this.access_key);
                            formData.append('s3_secret_key', this.secret_key);

                            try {
                                const response = await fetch(window.location.href, {
                                    method: 'POST',
                                    body: formData
                                });
                                const result = await response.json();
                                Swal.fire({
                                    icon: result.status,
                                    title: result.status === 'success' ? 'Berhasil' : 'Gagal',
                                    text: result.message
                                });
                            } catch (e) {
                                console.error('S3 Test Error:', e);
                                Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan sistem saat menghubungi server.' });
                            } finally {
                                this.testing = false;
                            }
                        }
                    }">
                    <h3 class="text-lg font-bold text-slate-800 mb-4">Penyimpanan S3 / Cloudflare R2</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Mode Penyimpanan</label>
                            <select name="storage_mode" x-model="mode" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="local">Local Only</option>
                                <option value="s3">S3 / R2 Only</option>
                                <option value="both">Both (Local & S3)</option>
                            </select>
                        </div>

                        <div x-show="mode !== 'local'" x-transition>
                            <div class="space-y-4 pt-2">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Endpoint API URL</label>
                                    <input type="text" name="s3_endpoint" x-model="endpoint" placeholder="https://<account_id>.r2.cloudflarestorage.com" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Public File URL (Optional)</label>
                                    <input type="text" name="s3_public_url" x-model="public_url" placeholder="https://pub-abc.r2.dev atau https://cdn.domain.com" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                    <p class="text-[10px] text-slate-400 mt-1 italic">Gunakan jika URL untuk akses file berbeda dengan endpoint API S3.</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Bucket Name</label>
                                    <input type="text" name="s3_bucket" x-model="bucket" placeholder="my-bucket-name" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1">Region</label>
                                        <input type="text" name="s3_region" x-model="region" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1">Directory / Prefix</label>
                                        <input type="text" name="s3_directory" x-model="directory" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Access Key ID</label>
                                    <input type="password" name="s3_access_key" x-model="access_key" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Secret Access Key</label>
                                    <input type="password" name="s3_secret_key" x-model="secret_key" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                </div>
                                <div class="pt-2">
                                    <button type="button" @click="testS3Connection" :disabled="testing" class="w-full bg-indigo-50 text-indigo-700 hover:bg-indigo-100 border border-indigo-200 py-2.5 rounded-lg font-semibold transition-all flex items-center justify-center gap-2">
                                        <template x-if="!testing">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                        </template>
                                        <template x-if="testing">
                                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        </template>
                                        <span x-text="testing ? 'Menghubungkan...' : 'Test Koneksi S3 / R2'"></span>
                                    </button>
                                    <p class="text-[10px] text-slate-400 mt-1.5 text-center italic">* Pastikan Bucket, Endpoint, dan Keys sudah terisi sebelum test.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="mt-8 pt-6 border-t border-slate-100 flex justify-end">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg transition-colors shadow-sm">
                Simpan Perubahan
            </button>
        </div>

    </form>

    <!-- Maintenance Section -->
    <div class="mt-8 bg-white rounded-xl shadow-sm border border-slate-200 p-8">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Manajemen Penyimpanan</h3>
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-slate-600">File Temporary (Uploads & ZIP)</p>
                <div class="text-2xl font-bold text-slate-800 mt-1">
                    <?php echo formatBytes($tempStats['size']); ?>
                    <span class="text-sm font-normal text-slate-500">(<?php echo $tempStats['count']; ?> files)</span>
                </div>
                <p class="text-xs text-slate-400 mt-1">Folder: uploads/temp/</p>
            </div>
            
            <form method="POST" onsubmit="return confirm('Yakin ingin menghapus semua file temporary?');">
                <input type="hidden" name="action" value="clear_temp">
                <button type="submit" class="bg-red-50 text-red-600 hover:bg-red-100 px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    Bersihkan File Temp
                </button>
            </form>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
