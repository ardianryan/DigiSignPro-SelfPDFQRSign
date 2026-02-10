<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Check Maintenance Mode (unless admin logging in)
$m_sql = "SELECT maintenance_mode FROM app_settings WHERE id = 1";
$m_result = $conn->query($m_sql);
$app_settings = $m_result->fetch_assoc();

if ($app_settings['maintenance_mode'] == 1) {
    // If maintenance mode is on
    // Allow login process to proceed so we can check if it's admin later
    // But if already logged in as regular user, force logout
    if (isset($_SESSION['user_id']) && $_SESSION['role'] !== 'admin') {
        header("Location: " . BASE_URL . "/logout");
        exit;
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . "/");
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        echo "Access Denied. Admin only.";
        exit;
    }
}

