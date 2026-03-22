<?php
require_once __DIR__ . '/../includes/auth_session.php';
requireLogin();

$page_title = "Profil Saya";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$user_id = $_SESSION['user_id'];
$msg = '';
$msg_type = '';

// Fetch max prefix length setting
$settings_result = $conn->query("SELECT max_prefix_length FROM app_settings WHERE id = 1");
$settings = $settings_result->fetch_assoc();
$max_prefix_len = $settings['max_prefix_length'] ?? 3;

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF Token Validation Failed.");
    }
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $position = trim($_POST['position']);
        $prefix = strtoupper(trim($_POST['signature_prefix']));
        
        $errors = [];
        
        // Validation
        if (empty($name) || empty($email)) {
            $errors[] = "Nama dan Email wajib diisi.";
        }
        
        if (strlen($prefix) < 2 || strlen($prefix) > $max_prefix_len || !preg_match('/^[A-Z]+$/', $prefix)) {
            $errors[] = "Prefix harus 2-$max_prefix_len huruf kapital (A-Z).";
        }
        
        // Check email uniqueness if changed
        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $email, $user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = "Email sudah digunakan oleh pengguna lain.";
            }
        }
        
        // Update Profile
        if (empty($errors)) {
            $target_len = $max_prefix_len;
            if ($target_len < 2) $target_len = 2;
            if ($target_len > 9) $target_len = 9;
            $col = @$conn->query("SHOW COLUMNS FROM users LIKE 'signature_prefix'");
            if ($col && $col->num_rows > 0) {
                $info = $col->fetch_assoc();
                $type = $info['Type'] ?? 'varchar(3)';
                if (preg_match('/varchar\((\d+)\)/i', $type, $m)) {
                    $curLen = (int)$m[1];
                    if ($curLen < $target_len) {
                        @$conn->query("ALTER TABLE users MODIFY signature_prefix VARCHAR($target_len) DEFAULT 'DS'");
                    }
                }
            }
            $update_sql = "UPDATE users SET name = ?, email = ?, position = ?, signature_prefix = ? WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("ssssi", $name, $email, $position, $prefix, $user_id);
            
            if ($stmt->execute()) {
                // Update Session Data
                $_SESSION['name'] = $name;
                $_SESSION['position'] = $position;
                
                $msg = "Profil berhasil diperbarui.";
                $msg_type = "success";
            } else {
                $msg = "Gagal memperbarui profil: " . $conn->error;
                $msg_type = "error";
            }
        } else {
            $msg = implode("<br>", $errors);
            $msg_type = "error";
        }
    }
    
    if (isset($_POST['update_password'])) {
        $current_pass = $_POST['current_password'];
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];
        
        if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
            $msg = "Semua field password wajib diisi.";
            $msg_type = "error";
        } elseif ($new_pass !== $confirm_pass) {
            $msg = "Password baru dan konfirmasi tidak cocok.";
            $msg_type = "error";
        } else {
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            
            if (password_verify($current_pass, $user['password'])) {
                $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $new_hash, $user_id);
                
                if ($stmt->execute()) {
                    $msg = "Password berhasil diubah.";
                    $msg_type = "success";
                } else {
                    $msg = "Gagal mengubah password: " . $conn->error;
                    $msg_type = "error";
                }
            } else {
                $msg = "Password saat ini salah.";
                $msg_type = "error";
            }
        }
    }
}

// Fetch Current Data
$stmt = $conn->prepare("SELECT name, email, role, position, signature_prefix FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo "User not found.";
    exit;
}

// Default prefix if empty
if (empty($user['signature_prefix'])) {
    $user['signature_prefix'] = 'DS';
}
?>

<div class="mb-8">
    <h2 class="text-3xl font-bold text-slate-800">Profil Saya</h2>
    <p class="text-slate-500 mt-1">Kelola informasi akun dan preferensi tanda tangan Anda.</p>
</div>

<?php if (!empty($msg)): ?>
    <div class="mb-6 p-4 rounded-lg <?php echo $msg_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
        <?php echo $msg; ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Edit Profile Form -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-6 pb-2 border-b border-slate-100">Informasi Dasar</h3>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nama Lengkap</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Jabatan / Posisi</label>
                    <input type="text" name="position" value="<?php echo htmlspecialchars($user['position']); ?>"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Role</label>
                    <input type="text" value="<?php echo ucfirst($user['role']); ?>" disabled
                           class="w-full border border-slate-200 bg-slate-50 rounded-lg px-3 py-2 text-sm text-slate-500">
                </div>
                
                <div class="pt-4 border-t border-slate-100 mt-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Prefix Tanda Tangan (2-<?php echo $max_prefix_len; ?> Huruf)</label>
                    <p class="text-xs text-slate-500 mb-2">Digunakan sebagai awalan kode verifikasi dokumen Anda (Contoh: DS-2024...)</p>
                    <div class="flex gap-2">
                         <input type="text" name="signature_prefix" value="<?php echo htmlspecialchars($user['signature_prefix']); ?>" 
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 uppercase"
                           maxlength="<?php echo $max_prefix_len; ?>" minlength="2" required placeholder="DS">
                    </div>
                </div>
                
                <div class="pt-4">
                    <button type="submit" name="update_profile" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg transition-colors font-medium">
                        Simpan Perubahan
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Change Password Form -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 h-fit">
        <h3 class="text-lg font-bold text-slate-800 mb-6 pb-2 border-b border-slate-100">Ganti Password</h3>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Password Saat Ini</label>
                    <input type="password" name="current_password" required
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Password Baru</label>
                    <input type="password" name="new_password" required minlength="6"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Konfirmasi Password Baru</label>
                    <input type="password" name="confirm_password" required minlength="6"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="pt-4">
                    <button type="submit" name="update_password" class="w-full bg-slate-800 hover:bg-slate-900 text-white py-2 px-4 rounded-lg transition-colors font-medium">
                        Update Password
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
