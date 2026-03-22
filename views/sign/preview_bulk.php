<?php
require_once __DIR__ . '/../../includes/auth_session.php';
requireLogin();

header('Content-Type: application/json');
ob_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (ob_get_level()) { ob_end_clean(); }
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    if (ob_get_level()) { ob_end_clean(); }
    echo json_encode(['status' => 'error', 'message' => 'CSRF Token Validation Failed.']);
    exit;
}

if (!isset($_FILES['zip_file']) || $_FILES['zip_file']['error'] !== 0) {
    if (ob_get_level()) { ob_end_clean(); }
    echo json_encode(['status' => 'error', 'message' => 'Upload File ZIP Gagal']);
    exit;
}

// Check Max Upload Size (Bulk)
require_once __DIR__ . '/../../config/database.php';
$settings_sql = "SELECT max_upload_size_bulk FROM app_settings WHERE id = 1";
$settings_result = $conn->query($settings_sql);
$settings = $settings_result->fetch_assoc();
$max_size = $settings['max_upload_size_bulk'] ?? 52428800; // Default 50MB

if ($_FILES['zip_file']['size'] > $max_size) {
    $mb = round($max_size / 1024 / 1024);
    if (ob_get_level()) { ob_end_clean(); }
    echo json_encode(['status' => 'error', 'message' => "Ukuran file melebihi batas ($mb MB)"]);
    exit;
}

$fileType = mime_content_type($_FILES['zip_file']['tmp_name']);
$allowedTypes = ['application/zip', 'application/x-zip-compressed', 'multipart/x-zip', 'application/x-compressed'];

// Basic validation, though mime_content_type can be tricky with zips
if (!in_array($fileType, $allowedTypes) && pathinfo($_FILES['zip_file']['name'], PATHINFO_EXTENSION) !== 'zip') {
     if (ob_get_level()) { ob_end_clean(); }
     echo json_encode(['status' => 'error', 'message' => 'File harus berupa ZIP']);
     exit;
}

// Setup Temp Dir
$batchId = uniqid('bulk_', true);
$tempDir = __DIR__ . '/../../public/uploads/temp/' . $batchId;

if (!mkdir($tempDir, 0777, true)) {
    if (ob_get_level()) { ob_end_clean(); }
    echo json_encode(['status' => 'error', 'message' => 'Gagal membuat direktori temporary']);
    exit;
}

// Move Uploaded ZIP
$zipPath = $tempDir . '/original.zip';
if (!move_uploaded_file($_FILES['zip_file']['tmp_name'], $zipPath)) {
    if (ob_get_level()) { ob_end_clean(); }
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan file ZIP']);
    exit;
}

// Extract First PDF for Preview
$zip = new ZipArchive;
if ($zip->open($zipPath) === TRUE) {
    $firstPdf = null;
    
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'pdf') {
            // Check if it's not in a __MACOSX folder
            if (strpos($filename, '__MACOSX') === false) {
                $firstPdf = $filename;
                break;
            }
        }
    }

    if ($firstPdf) {
        $zip->extractTo($tempDir, $firstPdf);
        $zip->close();
        
        // Return URL to this PDF
        // Encode path parts to handle spaces and special chars
        $urlParts = explode('/', $firstPdf);
        $encodedParts = array_map('rawurlencode', $urlParts);
        $encodedPath = implode('/', $encodedParts);
        
        $previewUrl = BASE_URL . '/uploads/temp/' . $batchId . '/' . $encodedPath;
        
        if (ob_get_level()) { ob_end_clean(); }
        echo json_encode([
            'status' => 'success',
            'batch_id' => $batchId,
            'preview_url' => $previewUrl,
            'filename' => $firstPdf
        ]);
    } else {
        $zip->close();
        // Cleanup
        // array_map('unlink', glob("$tempDir/*.*"));
        // rmdir($tempDir);
        if (ob_get_level()) { ob_end_clean(); }
        echo json_encode(['status' => 'error', 'message' => 'Tidak ditemukan file PDF dalam ZIP']);
    }
} else {
    if (ob_get_level()) { ob_end_clean(); }
    echo json_encode(['status' => 'error', 'message' => 'Gagal membuka file ZIP']);
}
?>
