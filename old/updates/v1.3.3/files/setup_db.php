<?php
// setup_db.php

// 1. Load Credentials
require_once __DIR__ . '/config/credentials.php';

echo "=== DigiSign Database Setup ===\n";
echo "Host: $host\n";
echo "User: $username\n";
echo "DB:   $database\n";
echo "-------------------------------\n";

// 2. Try Connecting directly to the specific database (Best for Shared Hosting)
// This suppresses the warning if DB doesn't exist
$conn = @new mysqli($host, $username, $password, $database);

$db_created = false;

if ($conn->connect_error) {
    // 3. If connection failed, maybe DB doesn't exist. Try connecting to server only.
    echo "Could not connect to database '$database'. Attempting to create it...\n";

    $conn = new mysqli($host, $username, $password);

    if ($conn->connect_error) {
        die("CRITICAL ERROR: Connection to database server failed: " . $conn->connect_error . "\nVerify your credentials in config/credentials.php\n");
    }

    // 4. Create Database
    $sql = "CREATE DATABASE IF NOT EXISTS $database";
    if ($conn->query($sql) === TRUE) {
        echo "Database '$database' created successfully or already exists.\n";
        $conn->select_db($database);
        $db_created = true;
    }
    else {
        die("CRITICAL ERROR: Could not create database '$database'.\nError: " . $conn->error . "\nIf you are on Shared Hosting, please create the database manually via cPanel and update config/credentials.php\n");
    }
}
else {
    echo "Successfully connected to existing database '$database'.\n";
}

// 5. Create Tables

// Table: users
$sql_users = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    position VARCHAR(100) DEFAULT NULL,
    reset_token VARCHAR(255) DEFAULT NULL,
    reset_expires_at DATETIME DEFAULT NULL,
    signature_path VARCHAR(255) DEFAULT NULL,
    signature_prefix VARCHAR(9) DEFAULT 'DS',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Check and add signature_prefix column if it doesn't exist (migration)
$check_col = $conn->query("SHOW COLUMNS FROM users LIKE 'signature_prefix'");
if ($check_col->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN signature_prefix VARCHAR(9) DEFAULT 'DS' AFTER signature_path");
    echo "Added 'signature_prefix' column to 'users' table.\n";
}
else {
    // Widen column if existing length is less than 9
    $colInfo = $conn->query("SHOW COLUMNS FROM users LIKE 'signature_prefix'")->fetch_assoc();
    $type = $colInfo['Type'] ?? 'varchar(3)';
    if (preg_match('/varchar\((\d+)\)/i', $type, $m)) {
        $currentLen = (int)$m[1];
        if ($currentLen < 9) {
            $conn->query("ALTER TABLE users MODIFY signature_prefix VARCHAR(9) DEFAULT 'DS'");
            echo "Widened 'signature_prefix' column to VARCHAR(9).\n";
        }
    }
}
// Added reset_token columns which were missing in original setup but used in forgot-password.php

if ($conn->query($sql_users) === TRUE) {
    echo "Table 'users' checked/created.\n";
}
else {
    echo "Error creating table 'users': " . $conn->error . "\n";
}

// Table: app_settings
$sql_settings = "CREATE TABLE IF NOT EXISTS app_settings (
    id INT(1) PRIMARY KEY DEFAULT 1,
    app_name VARCHAR(100) DEFAULT 'DigiSign Pro',
    app_logo VARCHAR(255) DEFAULT NULL,
    maintenance_mode TINYINT(1) DEFAULT 0,
    registration_open TINYINT(1) DEFAULT 1,
    max_upload_size INT(11) DEFAULT 10485760,
    max_upload_size_bulk INT(11) DEFAULT 52428800,
    max_prefix_length INT(2) DEFAULT 3,
    timezone VARCHAR(64) DEFAULT 'Asia/Jakarta',
    storage_mode ENUM('local', 's3', 'both') DEFAULT 'local',
    s3_endpoint VARCHAR(255) DEFAULT NULL,
    s3_region VARCHAR(50) DEFAULT 'us-east-1',
    s3_bucket VARCHAR(100) DEFAULT NULL,
    s3_access_key VARCHAR(255) DEFAULT NULL,
    s3_secret_key VARCHAR(255) DEFAULT NULL,
    s3_directory VARCHAR(100) DEFAULT 'digisign/',
    s3_public_url VARCHAR(255) DEFAULT NULL
)";
// Added max_upload columns which were used in settings.php

if ($conn->query($sql_settings) === TRUE) {
    echo "Table 'app_settings' checked/created.\n";

    // Check and add max_prefix_length column if it doesn't exist (migration)
    $check_col = $conn->query("SHOW COLUMNS FROM app_settings LIKE 'max_prefix_length'");
    if ($check_col->num_rows == 0) {
        $conn->query("ALTER TABLE app_settings ADD COLUMN max_prefix_length INT(2) DEFAULT 3 AFTER max_upload_size_bulk");
        echo "Added 'max_prefix_length' column to 'app_settings' table.\n";
    }

    // Check and add timezone column if it doesn't exist (migration)
    $check_tz = $conn->query("SHOW COLUMNS FROM app_settings LIKE 'timezone'");
    if ($check_tz->num_rows == 0) {
        $conn->query("ALTER TABLE app_settings ADD COLUMN timezone VARCHAR(64) DEFAULT 'Asia/Jakarta' AFTER max_prefix_length");
        echo "Added 'timezone' column to 'app_settings' table.\n";
    }

    // Check and add S3 columns (migration)
    $s3_cols = [
        'storage_mode' => "ENUM('local', 's3', 'both') DEFAULT 'local'",
        's3_endpoint' => "VARCHAR(255) DEFAULT NULL",
        's3_region' => "VARCHAR(50) DEFAULT 'us-east-1'",
        's3_bucket' => "VARCHAR(100) DEFAULT NULL",
        's3_access_key' => "VARCHAR(255) DEFAULT NULL",
        's3_secret_key' => "VARCHAR(255) DEFAULT NULL",
        's3_directory' => "VARCHAR(100) DEFAULT 'digisign/'",
        's3_public_url' => "VARCHAR(255) DEFAULT NULL"
    ];
    foreach ($s3_cols as $col => $def) {
        $check = $conn->query("SHOW COLUMNS FROM app_settings LIKE '$col'");
        if ($check->num_rows == 0) {
            $conn->query("ALTER TABLE app_settings ADD COLUMN $col $def");
            echo "Added '$col' column to 'app_settings' table.\n";
        }
    }

    // Insert default settings if not exists
    $conn->query("INSERT IGNORE INTO app_settings (id, app_name, maintenance_mode, registration_open, max_upload_size, max_upload_size_bulk, max_prefix_length, timezone, storage_mode, s3_directory, s3_public_url) VALUES (1, 'DigiSign Pro', 0, 1, 10485760, 52428800, 3, 'Asia/Jakarta', 'local', 'digisign/', NULL)");
    
    // Ensure existing record has default values for new columns if they are NULL
    $conn->query("UPDATE app_settings SET 
        max_upload_size_bulk = COALESCE(max_upload_size_bulk, 52428800),
        max_prefix_length = COALESCE(max_prefix_length, 3),
        timezone = COALESCE(timezone, 'Asia/Jakarta'),
        storage_mode = COALESCE(storage_mode, 'local'),
        s3_directory = COALESCE(s3_directory, 'digisign/')
        WHERE id = 1");
}
else {
    echo "Error creating table 'app_settings': " . $conn->error . "\n";
}

// Table: signatures
$sql_signatures = "CREATE TABLE IF NOT EXISTS signatures (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) UNSIGNED NOT NULL,
    signature_type ENUM('digital', 'qr_manual') DEFAULT 'digital',
    batch_id VARCHAR(50) DEFAULT NULL,
    document_name VARCHAR(255) DEFAULT NULL,
    document_number VARCHAR(100) DEFAULT NULL,
    document_subject TEXT DEFAULT NULL,
    document_attachment VARCHAR(255) DEFAULT NULL,
    file_path VARCHAR(255) DEFAULT NULL,
    verify_code VARCHAR(100) NOT NULL UNIQUE,
    signed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
// Added batch_id, document_number, subject, attachment which were used in other files

if ($conn->query($sql_signatures) === TRUE) {
    echo "Table 'signatures' checked/created.\n";

// Already handled in CREATE TABLE statement

}
else {
    echo "Error creating table 'signatures': " . $conn->error . "\n";
}

$conn->close();
echo "-------------------------------\n";
echo "Setup Completed Successfully.\n";
echo "Now you can run: php database/seeder.php\n";
?>