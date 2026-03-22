<?php
require_once __DIR__ . '/../../includes/security_functions.php';
add_security_headers();
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/mailer.php';

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/dashboard");
    exit;
}

$settings_sql = "SELECT * FROM app_settings WHERE id = 1";
$settings_result = $conn->query($settings_sql);
$settings = $settings_result->fetch_assoc();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF Token Validation Failed.");
    }
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = "Silakan masukkan email Anda.";
    } else {
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Save token
            $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires_at = ? WHERE id = ?");
            $update_stmt->bind_param("ssi", $token, $expires, $user['id']);
            
            if ($update_stmt->execute()) {
                $reset_link = BASE_URL . "/reset-password.php?token=" . $token;
                $subject = "Reset Password - " . $settings['app_name'];
                $body = "
                    <p>Halo <strong>{$user['name']}</strong>,</p>
                    <p>Kami menerima permintaan untuk mereset password akun Anda. Klik tombol di bawah ini untuk membuat password baru:</p>
                    <p><a href='{$reset_link}' style='background-color: #2563EB; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reset Password</a></p>
                    <p>Atau salin link berikut: <br> {$reset_link}</p>
                    <p>Link ini berlaku selama 1 jam.</p>
                    <p>Jika Anda tidak meminta reset password, abaikan email ini.</p>
                ";
                
                if (sendMail($email, $user['name'], $subject, $body)) {
                    $success = "Link reset password telah dikirim ke email Anda. Silakan cek inbox atau folder spam.";
                } else {
                    $error = "Gagal mengirim email. Silakan coba lagi atau hubungi admin.";
                }
            } else {
                $error = "Terjadi kesalahan sistem. Silakan coba lagi.";
            }
        } else {
            // For security, don't reveal if email exists or not, but for UX in internal apps, maybe okay.
            // Let's be generic but helpful.
            $error = "Email tidak ditemukan.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - <?php echo $settings['app_name']; ?></title>
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
            
            <h1 class="text-4xl font-bold mb-4">Reset Password</h1>
            <p class="text-slate-300 text-lg leading-relaxed max-w-md">
                Kembalikan akses akun Anda dengan mudah dan aman.
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
                <h2 class="text-3xl font-bold text-slate-900 mb-2">Lupa Password?</h2>
                <p class="text-slate-500">Masukkan email Anda untuk menerima instruksi reset password.</p>
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
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1" for="email">Email Address</label>
                    <input class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" id="email" name="email" type="email" placeholder="nama@perusahaan.com" required>
                </div>
                
                <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all transform hover:scale-[1.01]" type="submit">
                    Kirim Link Reset
                </button>
            </form>

            <div class="mt-8 text-center">
                <p class="text-slate-600">
                    Kembali ke <a href="/login" class="text-blue-600 font-semibold hover:text-blue-700 hover:underline">Halaman Login</a>
                </p>
            </div>
        </div>
    </div>

</body>
</html>
