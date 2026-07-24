<?php
require_once __DIR__ . '/../../includes/auth_session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/migration_helper.php';
requireAdmin();

header('Content-Type: application/json');

// Increase limits for update process
set_time_limit(600);
ini_set('memory_limit', '512M');

// Helper functions
function cleanup($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? cleanup("$dir/$file") : unlink("$dir/$file");
    }
    @rmdir($dir);
}

function recursiveCopy($src, $dst) {
    if (!is_dir($src)) return;
    $dir = opendir($src);
    if (!is_dir($dst)) @mkdir($dst, 0777, true);
    
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

$action = $_POST['action'] ?? 'execute';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['status' => 'error', 'message' => 'CSRF Token Validation Failed.']);
    exit;
}

// --- ACTION: MIGRATE ---
if ($action === 'migrate') {
    try {
        $results = run_database_migration($conn);
        $message = "Migrasi database berhasil dijalankan.";
        if (!empty($results)) {
            $message .= "\nDetail:\n- " . implode("\n- ", $results);
        } else {
            $message .= " Tidak ada pembaruan struktur database baru yang diperlukan.";
        }
        echo json_encode([
            'status' => 'success',
            'message' => $message,
            'details' => $results
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal menjalankan migrasi database: ' . $e->getMessage()
        ]);
    }
    exit;
}

// --- ACTION: ANALYZE ---
if ($action === 'analyze') {
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

    $tempId = 'upd_' . bin2hex(random_bytes(8));
    $tempDir = __DIR__ . '/../../public/uploads/temp/' . $tempId;
    
    if (!mkdir($tempDir, 0777, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal membuat direktori temp.']);
        exit;
    }

    $zip = new ZipArchive;
    if ($zip->open($file['tmp_name']) === TRUE) {
        // Security Check: Prevent Zip Slip (path traversal in ZIP entry names)
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            if (strpos($entryName, '..') !== false || strpos($entryName, '/') === 0 || strpos($entryName, '\\') === 0) {
                $zip->close();
                cleanup($tempDir);
                echo json_encode(['status' => 'error', 'message' => 'Keamanan: Nama file di dalam ZIP tidak valid (mengandung path traversal).']);
                exit;
            }
        }
        $zip->extractTo($tempDir);
        $zip->close();
    } else {
        cleanup($tempDir);
        echo json_encode(['status' => 'error', 'message' => 'Gagal mengekstrak ZIP']);
        exit;
    }

    $manifestPath = $tempDir . '/manifest.json';
    if (!file_exists($manifestPath)) {
        cleanup($tempDir);
        echo json_encode(['status' => 'error', 'message' => 'manifest.json tidak ditemukan dalam paket update.']);
        exit;
    }

    $manifest = json_decode(file_get_contents($manifestPath), true);
    if (!$manifest) {
        cleanup($tempDir);
        echo json_encode(['status' => 'error', 'message' => 'Format manifest.json tidak valid.']);
        exit;
    }

    // Return manifest and tempId for next step
    echo json_encode([
        'status' => 'success',
        'temp_id' => $tempId,
        'manifest' => $manifest
    ]);
    exit;
}

// --- ACTION: EXECUTE ---
if ($action === 'execute') {
    $tempId = $_POST['temp_id'] ?? '';
    if (empty($tempId) || !preg_match('/^upd_[a-f0-9]+$/', $tempId)) {
        echo json_encode(['status' => 'error', 'message' => 'ID Update tidak valid.']);
        exit;
    }

    $tempDir = __DIR__ . '/../../public/uploads/temp/' . $tempId;
    if (!is_dir($tempDir)) {
        echo json_encode(['status' => 'error', 'message' => 'Sesi update kadaluarsa atau tidak ditemukan. Silakan upload ulang.']);
        exit;
    }

    $manifestPath = $tempDir . '/manifest.json';
    $manifest = json_decode(file_get_contents($manifestPath), true);
    $newVersion = $manifest['version'] ?? 'Unknown';

    try {
        // 1. Run SQL
        $sqlFiles = $manifest['sql_files'] ?? [];
        if (file_exists($tempDir . '/update.sql')) {
            $sqlFiles[] = 'update.sql';
        }

        foreach (array_unique($sqlFiles) as $sqlFile) {
            // Security Check: Prevent directory traversal in SQL filename
            if (strpos($sqlFile, '..') !== false || strpos($sqlFile, '/') === 0 || strpos($sqlFile, '\\') === 0) {
                throw new Exception("Security Error: Path file SQL tidak valid.");
            }
            $sqlPath = $tempDir . '/' . $sqlFile;
            if (file_exists($sqlPath)) {
                $sqlContent = file_get_contents($sqlPath);
                if (trim($sqlContent) === '') continue;

                if ($conn->multi_query($sqlContent)) {
                    do {
                        if ($res = $conn->store_result()) $res->free();
                    } while ($conn->more_results() && $conn->next_result());
                    
                    if ($conn->errno) {
                         throw new Exception("SQL Error: " . $conn->error);
                    }
                } else {
                     throw new Exception("SQL Error: " . $conn->error);
                }
            }
        }

        // 2. Copy Files
        $sourceFiles = $tempDir . '/files';
        $projectRoot = realpath(__DIR__ . '/../../');

        if (is_dir($sourceFiles)) {
            recursiveCopy($sourceFiles, $projectRoot);
        }

        // 3. Update Version File
        $versionFile = __DIR__ . '/../../config/version.lock';
        file_put_contents($versionFile, $newVersion);

        // 4. Cleanup
        cleanup($tempDir);

        echo json_encode([
            'status' => 'success', 
            'message' => "Aplikasi berhasil diupdate ke versi $newVersion",
            'version' => $newVersion
        ]);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Aksi tidak dikenal.']);
