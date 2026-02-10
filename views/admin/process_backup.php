<?php
require_once __DIR__ . '/../../includes/auth_session.php';
require_once __DIR__ . '/../../config/database.php';
requireAdmin();

// Increase limits for backup process
set_time_limit(600);
ini_set('memory_limit', '512M');

$backupDb = isset($_POST['backup_db']) && $_POST['backup_db'] == '1';
$backupMedia = isset($_POST['backup_media']) && $_POST['backup_media'] == '1';

if (!$backupDb && !$backupMedia) {
    die("Pilih minimal satu opsi backup.");
}

$zip = new ZipArchive();
$filename = 'backup_digisign_' . date('Y-m-d_H-i-s') . '.zip';
$tempFile = sys_get_temp_dir() . '/' . $filename;

if ($zip->open($tempFile, ZipArchive::CREATE) !== TRUE) {
    die("Gagal membuat file ZIP.");
}

// 1. Backup Database
if ($backupDb) {
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    foreach ($tables as $table) {
        $query = "SELECT * FROM `$table`";
        $result = $conn->query($query);
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        
        // Add to ZIP
        $jsonContent = json_encode($rows, JSON_PRETTY_PRINT);
        $zip->addFromString("database/$table.json", $jsonContent);
    }
}

// 2. Backup Media
if ($backupMedia) {
    $uploadsDir = __DIR__ . '/../../public/uploads';
    if (is_dir($uploadsDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            // Skip temp files
            if (strpos($file->getFilename(), 'temp') !== false) continue;
            
            // Get real and relative path for current file
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen(realpath($uploadsDir)) + 1);

            // Add current file to archive
            $zip->addFile($filePath, "uploads/" . $relativePath);
        }
    }
}

$zip->close();

// 3. Force Download
header('Content-Type: application/zip');
header('Content-disposition: attachment; filename='.$filename);
header('Content-Length: ' . filesize($tempFile));
readfile($tempFile);

// Cleanup
unlink($tempFile);
exit;
?>
