<?php
// config/database.php

require_once __DIR__ . '/credentials.php';

// Define BASE URL dynamically
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$server_host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';

// We assume the application is served via the public directory
// So BASE_URL is just protocol + host + optional subdirectory
// If served via Apache Rewrite, SCRIPT_NAME is usually /index.php
$script_name = $_SERVER['SCRIPT_NAME'];
$dir_name = dirname($script_name);
$dir_name = str_replace('\\', '/', $dir_name);
$dir_name = trim($dir_name, '/');

$base_url = $protocol . "://" . $server_host;
if (!empty($dir_name)) {
    $base_url .= '/' . $dir_name;
}

define('BASE_URL', $base_url);

try {
    $conn = new mysqli($host, $username, $password, $database);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    // Set default timezone to Asia/Jakarta; override from app_settings if available
    $defaultTz = 'Asia/Jakarta';
    $allowedTz = ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'];
    $tz = $defaultTz;
    if ($conn) {
        if ($result = @$conn->query("SELECT timezone FROM app_settings WHERE id = 1")) {
            $row = $result->fetch_assoc();
            if (!empty($row['timezone']) && in_array($row['timezone'], $allowedTz, true)) {
                $tz = $row['timezone'];
            }
        }
    }
    date_default_timezone_set($tz);
} catch (Exception $e) {
    // Database connection error
}
