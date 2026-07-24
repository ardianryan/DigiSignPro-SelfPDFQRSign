<?php
/**
 * Security Functions for DigiSign Pro
 * Includes CSRF protection and security headers
 * Includes CSRF protection, security headers, and cookie hardening
 */

/**
 * Configure secure session cookies
 */
function harden_session_cookies() {
    if (session_status() === PHP_SESSION_NONE) {
        $is_localhost = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) || ($_SERVER['SERVER_NAME'] ?? '') === 'localhost';
        $secure = !$is_localhost && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        
        ini_set('session.use_only_cookies', 1);
        ini_set('session.use_strict_mode', 1);
        
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}

/**
 * Generate a CSRF token and store it in the session
 */
function get_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        harden_session_cookies();
        session_start();
    }
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify if the provided CSRF token matches the session token
 */
function verify_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Add security headers to the HTTP response
 */
function add_security_headers() {
    if (headers_sent()) return;
    
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://unpkg.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self';");
    header("Permissions-Policy: geolocation=(), midi=(), sync-xhr=(), microphone=(), camera=(), magnetometer=(), gyroscope=(), fullscreen=(self), payment=()");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
}
?>
