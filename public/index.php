<?php
// public/index.php

// Router Script Logic for PHP Built-in Server
if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $file = __DIR__ . urldecode($path);
    if (is_file($file)) {
        return false;
    }
}

// Define ROOT_PATH (One level up from public)
define('ROOT_PATH', dirname(__DIR__));

// Autoload (if applicable)
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
}

// Get the request path
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Remove script name if running in subdirectory without rewrite or direct access
// e.g. /public/index.php/dashboard
$script_name = $_SERVER['SCRIPT_NAME'];
if (strpos($path, $script_name) === 0) {
    $path = substr($path, strlen($script_name));
} elseif (strpos($path, dirname($script_name)) === 0) {
    // e.g. /public/dashboard
    $dir = dirname($script_name);
    if ($dir !== '/' && $dir !== '\\') {
        $path = substr($path, strlen($dir));
    }
}

$path = trim($path, '/');

// Routing Logic

// 1. Root -> Login
// Check Installation Lock
if (!file_exists(ROOT_PATH . '/config/installed.lock')) {
    // Check if we are already in the install folder
    // Since we are rewriting to public/index.php, the URL might be /install/
    
    // If user is accessing /install, allow it (by checking path)
    // But we need to serve the install file.
    
    // Simplest approach: Redirect to /install/index.php if not already there
    // But since /install is a physical directory, Apache might handle it directly if we don't interfere.
    // However, if we are here, it means we hit index.php.
    
    // Let's redirect to the physical install file location relative to web root
    // Assuming /install/index.php is accessible directly
    
    header("Location: /install/index.php");
    exit;
} else {
    // If installed.lock exists, prevent access to /install/
    // This part is tricky because /install/ might be accessed directly bypassing this router
    // So we should also add a check in install/index.php or delete the folder.
    // Ideally, install/index.php should check for lock file too.
}

if ($path === '' || $path === 'index.php') {
    require ROOT_PATH . '/views/auth/login.php';
    exit;
}

// 2. Map specific paths
$routes = [
    'login' => 'auth/login.php',
    'register' => 'auth/register.php',
    'logout' => 'auth/logout.php',
    'forgot-password' => 'auth/forgot-password.php',
    'reset-password' => 'auth/reset-password.php',
    'dashboard' => 'dashboard.php',
    'history' => 'history.php',
    'profile' => 'profile.php',
    
    // Admin
    'admin/users' => 'admin/users.php',
    'admin/settings' => 'admin/settings.php',
    'admin/updater' => 'admin/updater.php',
    'admin/process_update' => 'admin/process_update.php',
    'admin/backup' => 'admin/backup.php',
    'admin/process_backup' => 'admin/process_backup.php',
    'admin/process_restore' => 'admin/process_restore.php',
    
    // Sign
    'sign/single' => 'sign/single.php',
    'sign/bulk' => 'sign/bulk.php',
    'sign/qr_list' => 'sign/qr_list.php',
    'sign/qr_create' => 'sign/qr_create.php',
    'sign/process_single' => 'sign/process_single.php',
    'sign/process_bulk' => 'sign/process_bulk.php',
    
    // Verify
    'verify' => 'verify/index.php',
    'verify/index' => 'verify/index.php',
];

if (isset($routes[$path])) {
    require ROOT_PATH . '/views/' . $routes[$path];
    exit;
}

// 3. Dynamic Fallback (for paths like sign/process_single if not in array)
// Check if matches file in views/
$potential_file = ROOT_PATH . '/views/' . $path . '.php';
if (file_exists($potential_file)) {
    require $potential_file;
    exit;
}

// 4. Handle .php extension in URL (Backward Compatibility)
if (str_ends_with($path, '.php')) {
    $clean_path = substr($path, 0, -4);
    $potential_file = ROOT_PATH . '/views/' . $path;
    
    if (file_exists($potential_file)) {
        require $potential_file;
        exit;
    }
}

// 5. Handle Assets/Uploads if served via PHP built-in server (Fallback)
// If web server is properly configured, it serves static files directly.
// But if using `php -S localhost:8000 public/index.php`, we might need this.
// Actually, `php -S` serves static files if they exist.

// 404
http_response_code(404);
echo "404 Not Found - The requested page ($path) does not exist.";
