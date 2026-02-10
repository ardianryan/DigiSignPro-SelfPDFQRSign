<?php
require_once __DIR__ . '/../../includes/auth_session.php';
require_once __DIR__ . '/../../config/database.php';
requireAdmin();

header('Content-Type: application/json');

// Increase limits for update process
set_time_limit(300);
ini_set('memory_limit', '256M');

// Helper functions
function cleanup($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? cleanup("$dir/$file") : unlink("$dir/$file");
    }
    rmdir($dir);
}

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

if (!isset($_FILES['update_file']) || $_FILES['update_file']['error'] !== 0) {
    echo json_encode(['status' => 'error', 'message' => 'Upload file gagal']);
    exit;
}

$file = $_FILES['update_file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'zip') {
    echo json_encode(['status' => 'error', 'message' => 'Hanya file .zip yang diperbolehkan']);
    exit;
}

// Prepare Temp Dir
$tempDir = __DIR__ . '/../../public/uploads/temp/update_' . time();
if (!file_exists($tempDir)) {
    if (!mkdir($tempDir, 0777, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal membuat direktori temp. Cek permission.']);
        exit;
    }
}

$zip = new ZipArchive;
if ($zip->open($file['tmp_name']) === TRUE) {
    $zip->extractTo($tempDir);
    $zip->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Gagal mengekstrak ZIP']);
    exit;
}

// 1. Check Manifest
$manifestPath = $tempDir . '/manifest.json';
if (!file_exists($manifestPath)) {
    cleanup($tempDir);
    echo json_encode(['status' => 'error', 'message' => 'manifest.json tidak ditemukan dalam ZIP']);
    exit;
}

$manifest = json_decode(file_get_contents($manifestPath), true);
if (!$manifest || !isset($manifest['version'])) {
    cleanup($tempDir);
    echo json_encode(['status' => 'error', 'message' => 'Format manifest.json tidak valid (wajib ada key "version")']);
    exit;
}

$newVersion = $manifest['version'];

// 2. Run SQL if exists
$sqlFiles = $manifest['sql_files'] ?? [];
// Also check for standalone update.sql
if (file_exists($tempDir . '/update.sql')) {
    $sqlFiles[] = 'update.sql';
}

if (!empty($sqlFiles)) {
    // Check connection
    if ($conn->connect_error) {
        cleanup($tempDir);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
        exit;
    }

    foreach ($sqlFiles as $sqlFile) {
        $sqlPath = $tempDir . '/' . $sqlFile;
        if (file_exists($sqlPath)) {
            $sqlContent = file_get_contents($sqlPath);
            if (trim($sqlContent) === '') continue;

            // Execute Multi Query
            if ($conn->multi_query($sqlContent)) {
                do {
                    // Consume results to be ready for next query
                    if ($res = $conn->store_result()) $res->free();
                } while ($conn->more_results() && $conn->next_result());
                
                if ($conn->errno) {
                     cleanup($tempDir);
                     echo json_encode(['status' => 'error', 'message' => 'SQL Error: ' . $conn->error]);
                     exit;
                }
            } else {
                 cleanup($tempDir);
                 echo json_encode(['status' => 'error', 'message' => 'SQL Error: ' . $conn->error]);
                 exit;
            }
        }
    }
}

// 3. Copy Files (if 'files' folder exists)
$sourceFiles = $tempDir . '/files';
$projectRoot = realpath(__DIR__ . '/../../');

if (is_dir($sourceFiles)) {
    try {
        recursiveCopy($sourceFiles, $projectRoot);
    } catch (Exception $e) {
        cleanup($tempDir);
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyalin file: ' . $e->getMessage()]);
        exit;
    }
}

// 4. Update Version File
$versionFile = __DIR__ . '/../../config/version.lock';
file_put_contents($versionFile, $newVersion);

// 5. Cleanup
cleanup($tempDir);

echo json_encode([
    'status' => 'success', 
    'message' => "Aplikasi berhasil diupdate ke versi $newVersion",
    'version' => $newVersion
]);
?>
