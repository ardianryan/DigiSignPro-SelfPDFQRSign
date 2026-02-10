<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Check if settings allow registration/login or if maintenance is on
$settings_sql = "SELECT * FROM app_settings WHERE id = 1";
$settings_result = $conn->query($settings_sql);
$settings = $settings_result->fetch_assoc();

if ($settings['maintenance_mode'] == 1) {
    // But if already logged in as regular user, force logout
    if (isset($_SESSION['user_id']) && $_SESSION['role'] !== 'admin') {
        header("Location: " . BASE_URL . "/logout");
        exit;
    }
}

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/dashboard");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT id, name, email, password, role, position FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Maintenance Check: If maintenance is ON, only Admin can login
        if ($settings['maintenance_mode'] == 1 && $user['role'] !== 'admin') {
            $error = "Aplikasi sedang dalam Mode Maintenance. Silakan coba lagi nanti.";
        } else {
            if (password_verify($password, $user['password'])) {
                // Set Session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['position'] = $user['position'];
                
                header("Location: " . BASE_URL . "/dashboard");
                exit;
            } else {
                $error = "Password salah.";
            }
        }
    } else {
        $error = "Email tidak terdaftar.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/favicon.ico">
    <title>Login - <?php echo $settings['app_name']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-white h-screen overflow-hidden flex flex-col lg:flex-row">

    <!-- Left Side: Branding -->
    <div class="flex w-full lg:w-1/2 h-1/4 lg:h-full bg-slate-900 text-white flex-col justify-center px-8 lg:px-16 relative shrink-0">
        <div class="absolute inset-0 bg-gradient-to-br from-blue-900 to-slate-900 opacity-50"></div>
        <div class="relative z-10 flex flex-col items-center lg:items-start text-center lg:text-left">
            <?php if (!empty($settings['app_logo']) && file_exists(__DIR__ . '/../../public/' . $settings['app_logo'])): ?>
                <img src="<?php echo BASE_URL . '/' . $settings['app_logo']; ?>" alt="<?php echo $settings['app_name']; ?>" class="h-12 lg:h-20 w-auto object-contain mb-2 lg:mb-6">
            <?php else: ?>
                <h1 class="text-3xl lg:text-5xl font-bold tracking-tight mb-2 lg:mb-4">DIGI<span class="text-blue-500">SIGN</span></h1>
            <?php endif; ?>
            <h2 class="text-xl lg:text-3xl font-light mb-0 lg:mb-6"><?php echo $settings['app_name']; ?></h2>
            
            <div class="hidden lg:block">
                <p class="text-slate-400 text-lg leading-relaxed max-w-md">
                    Solusi aman dan terpercaya untuk manajemen dokumen digital Anda. 
                    Tanda tangani dokumen penting di mana saja, kapan saja, dengan validitas tinggi.
                </p>
                
                <div class="mt-12 flex gap-4">
                    <div class="flex items-center gap-2 text-slate-400">
                        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Secure</span>
                    </div>
                    <div class="flex items-center gap-2 text-slate-400">
                        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        <span>Fast</span>
                    </div>
                    <div class="flex items-center gap-2 text-slate-400">
                        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                        <span>Mobile Friendly</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Side: Login Form -->
    <div class="w-full lg:w-1/2 flex-1 lg:h-full flex items-center justify-center p-8 bg-slate-50 overflow-y-auto">
        <div class="w-full max-w-md">
            <div class="text-center lg:text-left mb-8">
                <h3 class="text-2xl font-bold text-slate-900">Selamat Datang Kembali</h3>
                <p class="text-slate-500 mt-2">Masuk ke akun Anda untuk melanjutkan.</p>
            </div>

            <?php if($settings['maintenance_mode'] == 1): ?>
                <div class="bg-yellow-50 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 rounded-r flex items-start" role="alert">
                    <svg class="w-6 h-6 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <div>
                        <p class="font-bold">Mode Maintenance Aktif</p>
                        <p class="text-sm mt-1">Sistem sedang dalam perbaikan. Hanya Administrator yang dapat login saat ini.</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r" role="alert">
                    <p class="font-bold">Error</p>
                    <p><?php echo $error; ?></p>
                </div>
            <?php endif; ?>

            <?php if(isset($_GET['registered'])): ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-r" role="alert">
                    <p class="font-bold">Sukses</p>
                    <p>Pendaftaran berhasil. Silakan login.</p>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1" for="email">Email Address</label>
                    <input class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" id="email" name="email" type="email" placeholder="nama@perusahaan.com" autocomplete="off" required>
                </div>
                
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-sm font-medium text-slate-700" for="password">Password</label>
                        <a href="/forgot-password" class="text-sm text-blue-600 hover:text-blue-500">Lupa password?</a>
                    </div>
                    <input class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" id="password" name="password" type="password" placeholder="••••••••" autocomplete="off" required>
                </div>

                <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all transform hover:scale-[1.01]" type="submit">
                    Masuk ke Dashboard
                </button>
            </form>
            
            <?php if($settings['registration_open'] == 1): ?>
                <p class="mt-8 text-center text-slate-600">
                    Belum punya akun? 
                    <a href="register" class="font-medium text-blue-600 hover:text-blue-500 hover:underline">Daftar Sekarang</a>
                </p>
            <?php else: ?>
                <p class="mt-8 text-center text-xs text-slate-400">
                    Pendaftaran User Baru Ditutup oleh Admin.
                </p>
            <?php endif; ?>
            
            <div class="mt-12 pt-6 border-t border-slate-200 text-center text-xs text-slate-400">
                &copy; <?php echo date('Y'); ?> <?php echo $settings['app_name']; ?>. All rights reserved.
            </div>
        </div>
    </div>

</body>
</html>
