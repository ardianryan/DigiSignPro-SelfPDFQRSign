<?php
require_once __DIR__ . '/../../includes/auth_session.php';
require_once __DIR__ . '/../../config/database.php';
requireAdmin();

header('Content-Type: application/json');

// Increase limits for restore process
set_time_limit(600);
ini_set('memory_limit', '512M');

function recursiveCopy($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            if ( is_dir($src . '/' . $file) ) {
                recursiveCopy($src . '/' . $file,$dst . '/' . $file);
            } else {
                copy($src . '/' . $file,$dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

function cleanup($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? cleanup("$dir/$file") : unlink("$dir/$file");
    }
    rmdir($dir);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== 0) {
    echo json_encode(['status' => 'error', 'message' => 'Upload file gagal']);
    exit;
}

$restoreDb = isset($_POST['restore_db']) && $_POST['restore_db'] == '1';
$restoreMedia = isset($_POST['restore_media']) && $_POST['restore_media'] == '1';

if (!$restoreDb && !$restoreMedia) {
    echo json_encode(['status' => 'error', 'message' => 'Pilih minimal satu opsi restore']);
    exit;
}

// Prepare Temp Dir
$tempDir = __DIR__ . '/../../public/uploads/temp/restore_' . time();
if (!mkdir($tempDir, 0777, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Gagal membuat direktori temp']);
    exit;
}

$zip = new ZipArchive;
if ($zip->open($_FILES['backup_file']['tmp_name']) === TRUE) {
    $zip->extractTo($tempDir);
    $zip->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Gagal mengekstrak ZIP']);
    exit;
}

try {
    // 1. Restore Database
    if ($restoreDb && is_dir($tempDir . '/database')) {
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        
        $files = glob($tempDir . '/database/*.json');
        foreach ($files as $file) {
            $tableName = pathinfo($file, PATHINFO_FILENAME);
            $data = json_decode(file_get_contents($file), true);
            
            if ($data) {
                // Truncate table
                $conn->query("TRUNCATE TABLE `$tableName`");
                
                // Insert Data
                foreach ($data as $row) {
                    $columns = [];
                    $values = [];
                    $types = "";
                    $params = [];
                    
                    foreach ($row as $col => $val) {
                        $columns[] = "`$col`";
                        $values[] = "?";
                        $params[] = $val;
                        
                        // Determine type
                        if (is_int($val)) $types .= "i";
                        elseif (is_double($val)) $types .= "d";
                        else $types .= "s";
                    }
                    
                    $sql = "INSERT INTO `$tableName` (" . implode(',', $columns) . ") VALUES (" . implode(',', $values) . ")";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param($types, ...$params);
                        $stmt->execute();
                    }
                }
            }
        }
        
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    }

    // 2. Restore Media
    if ($restoreMedia && is_dir($tempDir . '/uploads')) {
        $targetDir = __DIR__ . '/../../public/uploads';
        recursiveCopy($tempDir . '/uploads', $targetDir);
    }

    $message = "Restore berhasil.";
    if ($restoreDb) $message .= " Database dipulihkan.";
    if ($restoreMedia) $message .= " Media files dipulihkan.";

    echo json_encode(['status' => 'success', 'message' => $message]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}

// Cleanup
cleanup($tempDir);
?>
