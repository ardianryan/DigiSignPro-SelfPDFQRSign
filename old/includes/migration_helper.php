<?php
// includes/migration_helper.php

function check_database_migration($conn) {
    $missing = [];
    
    // Check connection first
    if (!$conn) {
        return ["Koneksi database tidak aktif."];
    }
    
    // Check table users
    try {
        $check_users = $conn->query("SHOW TABLES LIKE 'users'");
        if ($check_users->num_rows == 0) {
            $missing[] = "Tabel 'users' tidak ditemukan.";
        } else {
            $check_col = $conn->query("SHOW COLUMNS FROM users LIKE 'signature_prefix'");
            if ($check_col->num_rows == 0) {
                $missing[] = "Kolom 'signature_prefix' di tabel 'users' tidak ditemukan.";
            } else {
                $row = $check_col->fetch_assoc();
                if (preg_match('/varchar\((\d+)\)/i', $row['Type'] ?? '', $m)) {
                    if ((int)$m[1] < 9) {
                        $missing[] = "Kolom 'signature_prefix' di tabel 'users' perlu diperlebar menjadi VARCHAR(9).";
                    }
                }
            }
        }
    } catch (Exception $e) {
        $missing[] = "Gagal memproses pengecekan tabel users: " . $e->getMessage();
    }
    
    // Check table app_settings
    try {
        $check_settings = $conn->query("SHOW TABLES LIKE 'app_settings'");
        if ($check_settings->num_rows == 0) {
            $missing[] = "Tabel 'app_settings' tidak ditemukan.";
        } else {
            $cols = [
                'max_upload_size_bulk',
                'max_prefix_length',
                'timezone',
                'storage_mode',
                's3_endpoint',
                's3_region',
                's3_bucket',
                's3_access_key',
                's3_secret_key',
                's3_directory',
                's3_public_url'
            ];
            foreach ($cols as $col) {
                $check = $conn->query("SHOW COLUMNS FROM app_settings LIKE '$col'");
                if ($check->num_rows == 0) {
                    $missing[] = "Kolom '$col' di tabel 'app_settings' tidak ditemukan.";
                }
            }
        }
    } catch (Exception $e) {
        $missing[] = "Gagal memproses pengecekan tabel app_settings: " . $e->getMessage();
    }
    
    // Check table signatures
    try {
        $check_signatures = $conn->query("SHOW TABLES LIKE 'signatures'");
        if ($check_signatures->num_rows == 0) {
            $missing[] = "Tabel 'signatures' tidak ditemukan.";
        } else {
            $cols = [
                'batch_id',
                'document_name',
                'document_number',
                'document_subject',
                'document_attachment',
                'signature_type'
            ];
            foreach ($cols as $col) {
                $check = $conn->query("SHOW COLUMNS FROM signatures LIKE '$col'");
                if ($check->num_rows == 0) {
                    $missing[] = "Kolom '$col' di tabel 'signatures' tidak ditemukan.";
                }
            }
        }
    } catch (Exception $e) {
        $missing[] = "Gagal memproses pengecekan tabel signatures: " . $e->getMessage();
    }
    
    return $missing;
}

function run_database_migration($conn) {
    $results = [];
    
    if (!$conn) {
        throw new Exception("Koneksi database tidak tersedia.");
    }
    
    // Disable exceptions temporarily for mysqli to handle errors manually
    $driver = new mysqli_driver();
    $old_report_mode = $driver->report_mode;
    $driver->report_mode = MYSQLI_REPORT_OFF;
    
    try {
        // 1. Table users
        $check_users = $conn->query("SHOW TABLES LIKE 'users'");
        if ($check_users->num_rows == 0) {
            $sql = "CREATE TABLE IF NOT EXISTS users (
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
            if ($conn->query($sql)) {
                $results[] = "Berhasil membuat tabel 'users'.";
            } else {
                throw new Exception("Gagal membuat tabel 'users': " . $conn->error);
            }
        } else {
            $check_col = $conn->query("SHOW COLUMNS FROM users LIKE 'signature_prefix'");
            if ($check_col->num_rows == 0) {
                if ($conn->query("ALTER TABLE users ADD COLUMN signature_prefix VARCHAR(9) DEFAULT 'DS' AFTER signature_path")) {
                    $results[] = "Berhasil menambah kolom 'signature_prefix' ke tabel 'users'.";
                } else {
                    throw new Exception("Gagal menambah kolom 'signature_prefix': " . $conn->error);
                }
            } else {
                $row = $check_col->fetch_assoc();
                if (preg_match('/varchar\((\d+)\)/i', $row['Type'] ?? '', $m)) {
                    if ((int)$m[1] < 9) {
                        if ($conn->query("ALTER TABLE users MODIFY signature_prefix VARCHAR(9) DEFAULT 'DS'")) {
                            $results[] = "Berhasil memperlebar kolom 'signature_prefix' menjadi VARCHAR(9).";
                        } else {
                            throw new Exception("Gagal mengubah tipe kolom 'signature_prefix': " . $conn->error);
                        }
                    }
                }
            }
        }
        
        // 2. Table app_settings
        $check_settings = $conn->query("SHOW TABLES LIKE 'app_settings'");
        if ($check_settings->num_rows == 0) {
            $sql = "CREATE TABLE IF NOT EXISTS app_settings (
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
            if ($conn->query($sql)) {
                $results[] = "Berhasil membuat tabel 'app_settings'.";
                $conn->query("INSERT IGNORE INTO app_settings (id, app_name, maintenance_mode, registration_open, max_upload_size, max_upload_size_bulk, max_prefix_length, timezone, storage_mode, s3_directory) VALUES (1, 'DigiSign Pro', 0, 1, 10485760, 52428800, 3, 'Asia/Jakarta', 'local', 'digisign/')");
            } else {
                throw new Exception("Gagal membuat tabel 'app_settings': " . $conn->error);
            }
        } else {
            $cols = [
                'max_upload_size_bulk' => "INT(11) DEFAULT 52428800 AFTER max_upload_size",
                'max_prefix_length' => "INT(2) DEFAULT 3 AFTER max_upload_size_bulk",
                'timezone' => "VARCHAR(64) DEFAULT 'Asia/Jakarta' AFTER max_prefix_length",
                'storage_mode' => "ENUM('local', 's3', 'both') DEFAULT 'local'",
                's3_endpoint' => "VARCHAR(255) DEFAULT NULL",
                's3_region' => "VARCHAR(50) DEFAULT 'us-east-1'",
                's3_bucket' => "VARCHAR(100) DEFAULT NULL",
                's3_access_key' => "VARCHAR(255) DEFAULT NULL",
                's3_secret_key' => "VARCHAR(255) DEFAULT NULL",
                's3_directory' => "VARCHAR(100) DEFAULT 'digisign/'",
                's3_public_url' => "VARCHAR(255) DEFAULT NULL"
            ];
            foreach ($cols as $col => $def) {
                $check = $conn->query("SHOW COLUMNS FROM app_settings LIKE '$col'");
                if ($check->num_rows == 0) {
                    if ($conn->query("ALTER TABLE app_settings ADD COLUMN $col $def")) {
                        $results[] = "Berhasil menambah kolom '$col' ke tabel 'app_settings'.";
                    } else {
                        throw new Exception("Gagal menambah kolom '$col': " . $conn->error);
                    }
                }
            }
            
            // Sync default row data
            $conn->query("INSERT IGNORE INTO app_settings (id, app_name) VALUES (1, 'DigiSign Pro')");
            $conn->query("UPDATE app_settings SET 
                max_upload_size_bulk = COALESCE(max_upload_size_bulk, 52428800),
                max_prefix_length = COALESCE(max_prefix_length, 3),
                timezone = COALESCE(timezone, 'Asia/Jakarta'),
                storage_mode = COALESCE(storage_mode, 'local'),
                s3_directory = COALESCE(s3_directory, 'digisign/')
                WHERE id = 1");
        }
        
        // 3. Table signatures
        $check_signatures = $conn->query("SHOW TABLES LIKE 'signatures'");
        if ($check_signatures->num_rows == 0) {
            $sql = "CREATE TABLE IF NOT EXISTS signatures (
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
            if ($conn->query($sql)) {
                $results[] = "Berhasil membuat tabel 'signatures'.";
            } else {
                throw new Exception("Gagal membuat tabel 'signatures': " . $conn->error);
            }
        } else {
            $cols = [
                'batch_id' => "VARCHAR(50) DEFAULT NULL AFTER signature_type",
                'document_name' => "VARCHAR(255) DEFAULT NULL AFTER batch_id",
                'document_number' => "VARCHAR(100) DEFAULT NULL AFTER document_name",
                'document_subject' => "TEXT DEFAULT NULL AFTER document_number",
                'document_attachment' => "VARCHAR(255) DEFAULT NULL AFTER document_subject",
                'signature_type' => "ENUM('digital', 'qr_manual') DEFAULT 'digital'"
            ];
            foreach ($cols as $col => $def) {
                $check = $conn->query("SHOW COLUMNS FROM signatures LIKE '$col'");
                if ($check->num_rows == 0) {
                    if ($conn->query("ALTER TABLE signatures ADD COLUMN $col $def")) {
                        $results[] = "Berhasil menambah kolom '$col' ke tabel 'signatures'.";
                    } else {
                        throw new Exception("Gagal menambah kolom '$col': " . $conn->error);
                    }
                }
            }
        }
        
        $driver->report_mode = $old_report_mode;
        return $results;
        
    } catch (Exception $e) {
        $driver->report_mode = $old_report_mode;
        throw $e;
    }
}
