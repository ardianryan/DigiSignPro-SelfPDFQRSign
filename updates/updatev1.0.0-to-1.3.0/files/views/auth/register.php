<?php
require_once __DIR__ . '/../../includes/security_functions.php';
add_security_headers();
session_start();
require_once __DIR__ . '/../../config/database.php';

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/dashboard");
    exit;
}

// Check if settings allow registration
$settings_sql = "SELECT * FROM app_settings WHERE id = 1";
$settings_result = $conn->query($settings_sql);
$settings = $settings_result->fetch_assoc();

if ($settings['registration_open'] == 0) {
    die("Pendaftaran ditutup oleh Administrator.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF Token Validation Failed.");
    }
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $position = trim($_POST['position']);
    
    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($position)) {
        $error = "Semua field wajib diisi.";
    } else {
        // Check Email
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $error = "Email sudah terdaftar.";
        } else {
            // Insert User
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'user'; // Default role
            
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, position) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $email, $hashed_password, $role, $position);
            
            if ($stmt->execute()) {
                header("Location: " . BASE_URL . "?registered=1");
                exit;
            } else {
                $error = "Terjadi kesalahan saat mendaftar: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo $settings['app_name']; ?></title>
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
            <h2 class="text-xl lg:text-3xl font-light mb-0 lg:mb-6">Bergabunglah Bersama Kami</h2>
            
            <div class="hidden lg:block">
                <p class="text-slate-400 text-lg leading-relaxed max-w-md">
                    Buat akun sekarang untuk mulai menandatangani dokumen secara digital, aman, dan efisien.
                </p>
                
                <div class="mt-12 space-y-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-slate-800 flex items-center justify-center text-blue-500 font-bold text-xl">1</div>
                        <div>
                            <h4 class="font-semibold">Daftar Akun</h4>
                            <p class="text-sm text-slate-400">Isi data diri Anda dengan lengkap.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-slate-800 flex items-center justify-center text-blue-500 font-bold text-xl">2</div>
                        <div>
                            <h4 class="font-semibold">Login</h4>
                            <p class="text-sm text-slate-400">Masuk menggunakan kredensial Anda.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-slate-800 flex items-center justify-center text-blue-500 font-bold text-xl">3</div>
                        <div>
                            <h4 class="font-semibold">Mulai Tanda Tangan</h4>
                            <p class="text-sm text-slate-400">Upload dan tanda tangani dokumen.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Side: Register Form -->
    <div class="w-full lg:w-1/2 flex-1 lg:h-full flex items-center justify-center p-8 bg-slate-50 overflow-y-auto">
        <div class="w-full max-w-md">
            <div class="text-center lg:text-left mb-8">
                <h3 class="text-2xl font-bold text-slate-900">Buat Akun Baru</h3>
                <p class="text-slate-500 mt-2">Lengkapi form di bawah ini.</p>
            </div>

            <?php if($error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r" role="alert">
                    <p class="font-bold">Error</p>
                    <p><?php echo $error; ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1" for="name">Nama Lengkap</label>
                    <input class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" id="name" name="name" type="text" autocomplete="off" placeholder="John Doe" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1" for="position">Jabatan</label>
                    <input class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" id="position" name="position" type="text" autocomplete="off" placeholder="Manager" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1" for="email">Email Address</label>
                    <input class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" id="email" name="email" type="email" autocomplete="off" placeholder="nama@perusahaan.com" required>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1" for="password">Password</label>
                    <input class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" id="password" name="password" type="password" autocomplete="off" placeholder="••••••••" required>
                </div>

                <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all transform hover:scale-[1.01] mt-4" type="submit">
                    Daftar Sekarang
                </button>
            </form>
            
            <p class="mt-8 text-center text-slate-600">
                Sudah punya akun? 
                <a href="/" class="font-medium text-blue-600 hover:text-blue-500 hover:underline">Login di sini</a>
            </p>
            
            <div class="mt-12 pt-6 border-t border-slate-200 text-center text-xs text-slate-400">
                &copy; <?php echo date('Y'); ?> <?php echo $settings['app_name']; ?>. All rights reserved.
            </div>
        </div>
    </div>

</body>
</html>