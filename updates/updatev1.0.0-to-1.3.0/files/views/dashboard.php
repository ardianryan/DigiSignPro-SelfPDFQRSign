<?php
require_once __DIR__ . '/../includes/auth_session.php';
requireLogin();

$page_title = "Dashboard";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

// Stats logic
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Handle Prefix Update
$prefix_msg = '';
$prefix_msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_prefix'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF Token Validation Failed.");
    }
    $new_prefix = strtoupper(trim($_POST['signature_prefix']));
    $settings_result = $conn->query("SELECT max_prefix_length FROM app_settings WHERE id = 1");
    $settings = $settings_result->fetch_assoc();
    $max_prefix_len = $settings['max_prefix_length'] ?? 3;
    
    if (strlen($new_prefix) < 2 || strlen($new_prefix) > $max_prefix_len || !preg_match('/^[A-Z]+$/', $new_prefix)) {
        $prefix_msg = "Prefix harus 2-$max_prefix_len huruf kapital (A-Z).";
        $prefix_msg_type = "error";
    } else {
        $stmt = $conn->prepare("UPDATE users SET signature_prefix = ? WHERE id = ?");
        $stmt->bind_param("si", $new_prefix, $user_id);
        
        if ($stmt->execute()) {
            $prefix_msg = "Prefix tanda tangan berhasil diperbarui.";
            $prefix_msg_type = "success";
        } else {
            $prefix_msg = "Gagal memperbarui prefix: " . $conn->error;
            $prefix_msg_type = "error";
        }
    }
}

// Fetch current prefix
$stmt = $conn->prepare("SELECT signature_prefix FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    $current_prefix = $res->fetch_assoc()['signature_prefix'];
} else {
    $current_prefix = 'DS';
}
if (empty($current_prefix)) $current_prefix = 'DS';

$stats = [
    'signed_docs' => 0,
    'total_users' => 0 // Only visible to admin
];

if ($role === 'admin') {
    $res = $conn->query("SELECT COUNT(*) as cnt FROM signatures");
    $stats['signed_docs'] = $res->fetch_assoc()['cnt'];
    
    $res = $conn->query("SELECT COUNT(*) as cnt FROM users");
    $stats['total_users'] = $res->fetch_assoc()['cnt'];
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM signatures WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['signed_docs'] = $stmt->get_result()->fetch_assoc()['cnt'];
}

?>

<div class="mb-8">
    <h2 class="text-3xl font-bold text-slate-800">Selamat Datang, <?php echo $_SESSION['name']; ?> 👋</h2>
    <p class="text-slate-500 mt-1">Anda login sebagai <span class="font-semibold text-blue-600 uppercase"><?php echo $_SESSION['role']; ?></span> - <?php echo $_SESSION['position']; ?></p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </div>
            <div>
                <p class="text-sm text-slate-500 font-medium">Dokumen Ditandatangani</p>
                <p class="text-2xl font-bold text-slate-800"><?php echo $stats['signed_docs']; ?></p>
            </div>
        </div>
    </div>

    <?php if($role === 'admin'): ?>
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            </div>
            <div>
                <p class="text-sm text-slate-500 font-medium">Total Pengguna</p>
                <p class="text-2xl font-bold text-slate-800"><?php echo $stats['total_users']; ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="mt-8 bg-white rounded-xl shadow-sm border border-slate-200 p-6">
    <h3 class="text-lg font-bold text-slate-800 mb-4">Akses Cepat</h3>
    <div class="flex flex-wrap gap-4">
        <?php if($role === 'user'): ?>
            <div class="mt-6 bg-slate-50 p-4 rounded-lg border border-slate-200">
                <h4 class="text-sm font-bold text-slate-700 mb-3">Update Prefix Tanda Tangan</h4>
                <form method="POST" action="" class="flex gap-2">
                    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                    <input type="text" name="signature_prefix" value="<?php echo htmlspecialchars($current_prefix); ?>" placeholder="Prefix (e.g. DS)" maxlength="<?php echo $max_prefix_len ?? 3; ?>" class="border border-slate-300 rounded px-3 py-1 text-sm uppercase">
                    <button type="submit" name="update_prefix" class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700 transition-colors">Update</button>
                </form>
                <?php if($prefix_msg): ?>
                    <p class="text-xs mt-2 <?php echo $prefix_msg_type === 'success' ? 'text-green-600' : 'text-red-600'; ?>"><?php echo $prefix_msg; ?></p>
                <?php endif; ?>
            </div>
            
            <a href="<?php echo BASE_URL; ?>/sign/single" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">Buat Tanda Tangan Baru</a>
            <a href="<?php echo BASE_URL; ?>/history" class="bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 px-4 py-2 rounded-lg transition-colors">Lihat Riwayat</a>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>/admin/users" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">Kelola User</a>
            <a href="<?php echo BASE_URL; ?>/admin/settings" class="bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 px-4 py-2 rounded-lg transition-colors">Pengaturan Sistem</a>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
