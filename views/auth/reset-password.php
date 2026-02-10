<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/dashboard");
    exit;
}

$settings_sql = "SELECT * FROM app_settings WHERE id = 1";
$settings_result = $conn->query($settings_sql);
$settings = $settings_result->fetch_assoc();

$token = $_GET['token'] ?? '';
$error = '';
$success = '';
$valid_token = false;

if (empty($token)) {
    $error = "Token tidak valid atau tidak ditemukan.";
} else {
    // Verify token
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $valid_token = true;
        $user = $result->fetch_assoc();
    } else {
        $error = "Token tidak valid atau sudah kadaluarsa.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (strlen($password) < 6) {
        $error = "Password minimal 6 karakter.";
    } elseif ($password !== $confirm_password) {
        $error = "Konfirmasi password tidak cocok.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires_at = NULL WHERE id = ?");
        $update_stmt->bind_param("si", $hashed_password, $user['id']);
        
        if ($update_stmt->execute()) {
            $success = "Password berhasil diubah. Silakan login dengan password baru.";
            $valid_token = false; // Hide form
        } else {
            $error = "Gagal mengubah password. Silakan coba lagi.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo $settings['app_name']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-white h-screen overflow-hidden flex">

    <!-- Left Side: Branding -->
    <div class="hidden lg:flex w-1/2 bg-slate-900 text-white flex-col justify-center px-16 relative">
        <div class="absolute inset-0 bg-gradient-to-br from-blue-900 to-slate-900 opacity-50"></div>
        <div class="relative z-10">
            <?php if (!empty($settings['app_logo']) && file_exists(__DIR__ . '/' . $settings['app_logo'])): ?>
                <img src="<?php echo BASE_URL . '/' . $settings['app_logo']; ?>" alt="<?php echo $settings['app_name']; ?>" class="h-20 w-auto object-contain mb-6">
            <?php else: ?>
                <div class="w-20 h-20 bg-blue-500 rounded-2xl flex items-center justify-center mb-6 shadow-lg shadow-blue-500/30">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                </div>
            <?php endif; ?>
            
            <h1 class="text-4xl font-bold mb-4">Password Baru</h1>
            <p class="text-slate-300 text-lg leading-relaxed max-w-md">
                Amankan akun Anda dengan password yang kuat dan mudah diingat.
            </p>
        </div>
        <!-- Decorative Elements -->
        <div class="absolute top-0 right-0 -mt-20 -mr-20 w-80 h-80 bg-blue-500 rounded-full blur-3xl opacity-20"></div>
        <div class="absolute bottom-0 left-0 -mb-20 -ml-20 w-80 h-80 bg-purple-500 rounded-full blur-3xl opacity-20"></div>
    </div>

    <!-- Right Side: Form -->
    <div class="w-full lg:w-1/2 flex flex-col justify-center px-8 lg:px-24 relative bg-white">
        <div class="max-w-md w-full mx-auto">
            <div class="text-center lg:text-left mb-10">
                <h2 class="text-3xl font-bold text-slate-900 mb-2">Buat Password Baru</h2>
                <p class="text-slate-500">Masukkan password baru untuk akun Anda.</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r-lg" role="alert">
                    <p class="font-bold">Error</p>
                    <p><?php echo $error; ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-r-lg" role="alert">
                    <p class="font-bold">Berhasil</p>
                    <p><?php echo $success; ?></p>
                    <div class="mt-4">
                        <a href="login" class="inline-block bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">Login Sekarang</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($valid_token && empty($success)): ?>
            <form method="POST" action="" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1" for="password">Password Baru</label>
                    <input class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" id="password" name="password" type="password" placeholder="••••••••" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1" for="confirm_password">Konfirmasi Password</label>
                    <input class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" id="confirm_password" name="confirm_password" type="password" placeholder="••••••••" required>
                </div>
                
                <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all transform hover:scale-[1.01]" type="submit">
                    Simpan Password
                </button>
            </form>
            <?php endif; ?>

            <?php if (!$valid_token && empty($success)): ?>
            <div class="mt-8 text-center">
                <p class="text-slate-600">
                    <a href="/forgot-password" class="text-blue-600 font-semibold hover:text-blue-700 hover:underline">Kirim ulang link reset password</a>
                </p>
                <p class="text-slate-600 mt-2">
                    Kembali ke <a href="/login" class="text-blue-600 font-semibold hover:text-blue-700 hover:underline">Halaman Login</a>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
