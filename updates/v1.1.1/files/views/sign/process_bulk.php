<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/auth_session.php';
require_once __DIR__ . '/../../config/database.php';

requireLogin();

// Fetch Settings for PDF Protection
$settings_result = $conn->query("SELECT max_upload_size_bulk FROM app_settings WHERE id = 1");
$settings = $settings_result->fetch_assoc();

use setasign\Fpdi\Fpdi;
use setasign\FpdiProtection\FpdiProtection;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

// Increase time limit for bulk processing
set_time_limit(300); // 5 minutes

header('Content-Type: application/json');
ob_start();

// --- Auto-Cleanup Temp Files (Older than 1 Hour) ---
$cleanupDir = __DIR__ . '/../../public/uploads/temp/';
if (is_dir($cleanupDir)) {
    $now = time();
    $files = scandir($cleanupDir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $filePath = $cleanupDir . $file;
        
        // Check if file/dir is older than 1 hour (3600 seconds)
        if (file_exists($filePath) && filemtime($filePath) < ($now - 3600)) {
            if (is_dir($filePath)) {
                // Recursive delete for batch folders
                $batchFiles = scandir($filePath);
                foreach ($batchFiles as $batchFile) {
                    if ($batchFile === '.' || $batchFile === '..') continue;
                    @unlink($filePath . '/' . $batchFile);
                }
                @rmdir($filePath);
            } else {
                @unlink($filePath);
            }
        }
    }
}
// ----------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (ob_get_level()) { ob_end_clean(); }
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$batchId = $_POST['batch_id'] ?? '';
$x = floatval($_POST['x'] ?? 0);
$y = floatval($_POST['y'] ?? 0);
$pageToSign = intval($_POST['page'] ?? 1);
$baseNumber = $_POST['base_number'] ?? '';
$subject = $_POST['subject'] ?? '';
$userId = $_SESSION['user_id'];
$showQrCaption = isset($_POST['show_qr_caption']) && $_POST['show_qr_caption'] == 1;

// Validate PDF Password (Mandatory)
$pdf_pass = $_POST['pdf_password'] ?? '';
if (empty($pdf_pass)) {
    if (ob_get_level()) { ob_end_clean(); }
    echo json_encode(['status' => 'error', 'message' => 'Password Parafrase wajib diisi']);
    exit;
}

$signerName = '';
$signerPosition = '';
$userPrefix = 'DS';

// Fetch User Info (Name, Position, Prefix)
$userSql = "SELECT name, position, signature_prefix FROM users WHERE id = ?";
$stmtUser = $conn->prepare($userSql);
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$userRes = $stmtUser->get_result();
if ($userData = $userRes->fetch_assoc()) {
    $signerName = $userData['name'];
    $signerPosition = $userData['position'] ?? '';
    $userPrefix = $userData['signature_prefix'] ?? 'DS';
    if (empty($userPrefix)) $userPrefix = 'DS';
}

if (empty($batchId) || !preg_match('/^[a-zA-Z0-9_.]+$/', $batchId)) {
    if (ob_get_level()) { ob_end_clean(); }
    echo json_encode(['status' => 'error', 'message' => 'Invalid Batch ID']);
    exit;
}

$tempDir = __DIR__ . '/../../public/uploads/temp/' . $batchId;
$originalZipPath = $tempDir . '/original.zip';

if (!file_exists($originalZipPath)) {
    if (ob_get_level()) { ob_end_clean(); }
    echo json_encode(['status' => 'error', 'message' => 'Batch file not found. Please re-upload.']);
    exit;
}

// Prepare Output ZIP
$outputZipName = 'Bulk_Signed_' . date('Ymd_Hi') . '.zip';
// Store in temp first, will be moved or served
$outputZipPath = $tempDir . '/' . $outputZipName;

$outputZip = new ZipArchive();
if ($outputZip->open($outputZipPath, ZipArchive::CREATE) !== TRUE) {
    if (ob_get_level()) { ob_end_clean(); }
    echo json_encode(['status' => 'error', 'message' => 'Cannot create output zip']);
    exit;
}

// Open Input ZIP
$inputZip = new ZipArchive();
if ($inputZip->open($originalZipPath) !== TRUE) {
    if (ob_get_level()) { ob_end_clean(); }
    echo json_encode(['status' => 'error', 'message' => 'Cannot open input zip']);
    exit;
}

// QR Configuration
$qrOptions = new QROptions([
    'version'    => QRCode::VERSION_AUTO,
    'outputType' => QRCode::OUTPUT_IMAGE_PNG,
    'eccLevel'   => QRCode::ECC_L,
    'scale'      => 5,
]);
// Generator will be instantiated inside the loop

// Processing Statistics
$processedCount = 0;
$errorCount = 0;
$errors = [];

// Iterate through files in ZIP
for ($i = 0; $i < $inputZip->numFiles; $i++) {
    $filename = $inputZip->getNameIndex($i);
    $fileInfo = pathinfo($filename);
    
    // Skip directories and non-PDFs (and macOS metadata)
    if (substr($filename, -1) === '/' || strtolower($fileInfo['extension'] ?? '') !== 'pdf' || strpos($filename, '__MACOSX') !== false) {
        continue;
    }

    // Extract current PDF to temp
    $tempPdfPath = $tempDir . '/temp_' . $i . '.pdf';
    copy("zip://" . $originalZipPath . "#" . $filename, $tempPdfPath);

    try {
        // Generate Token
        $verifyCode = $userPrefix . '-BLK-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
        $verifyUrl = BASE_URL . "/verify/?token=" . $verifyCode;
        
        // Generate QR (New instance to avoid data accumulation)
        $qrGenerator = new QRCode($qrOptions);
        $qrImage = $qrGenerator->render($verifyUrl);
        $qrBase64 = explode(',', $qrImage)[1];
        $qrTempFile = $tempDir . '/qr_' . $i . '.png';
        file_put_contents($qrTempFile, base64_decode($qrBase64));

        // Process PDF with FPDI
        // Enable ArcFour fallback for OpenSSL 3 compatibility
        $pdf = new FpdiProtection('P', 'mm', 'A4', true);
        
        try {
            $pageCount = $pdf->setSourceFile($tempPdfPath);
        } catch (Exception $e) {
            throw new Exception("PDF Compression/Version not supported: " . $e->getMessage());
        }

        // Determine actual page to sign
        $targetPage = ($pageToSign > $pageCount) ? $pageCount : $pageToSign;

        for ($p = 1; $p <= $pageCount; $p++) {
            $tplId = $pdf->importPage($p);
            $size = $pdf->getTemplateSize($tplId);
            
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId);

            if ($p === $targetPage) {
                $qrSize = 25; // 25mm
                $pdf->Image($qrTempFile, $x, $y, $qrSize, $qrSize);
                
                // Add Text below QR if requested
                if ($showQrCaption) {
                    $pdf->SetFont('Arial', '', 6);
                    $pdf->SetXY($x, $y + $qrSize + 1);
                    
                    $text = "ID : " . $verifyCode . "\nDitandatangani secara elektronik oleh " . $signerName;
                    if (!empty($signerPosition)) {
                        $text .= " - " . $signerPosition;
                    }
                    
                    $pdf->MultiCell($qrSize + 15, 3, $text, 0, 'L');
                }
            }
        }

        // Save Signed PDF to System Uploads
        $safe_subject = preg_replace('/[^A-Za-z0-9_-]/', '_', $subject);
        if (empty($safe_subject)) $safe_subject = 'document';
        $signedFilename = $safe_subject . '_' . $verifyCode . '_signed.pdf';
        $systemPath = 'uploads/signatures/' . $signedFilename;
        $fullSystemPath = __DIR__ . '/../../public/' . $systemPath;
        
        // Apply PDF Protection (Mandatory)
        $permissions = FpdiProtection::PERM_PRINT | FpdiProtection::PERM_COPY | FpdiProtection::PERM_ACCESSIBILITY;
        $pdf->setProtection($permissions, '', $pdf_pass);

        $pdf->Output('F', $fullSystemPath);

        // Add to Output ZIP
        $outputZip->addFile($fullSystemPath, 'Signed_' . $fileInfo['basename']);

        // Database Insert
        $docNumber = $baseNumber . ' - ' . ($processedCount + 1);
        $docName = $fileInfo['basename']; // Use original filename
        $signedAt = date('Y-m-d H:i:s');
        
        // Check columns again to be safe: 
        // user_id, batch_id, document_name, document_number, document_subject, file_path, verify_code, signed_at
        // document_attachment is not mandatory
        $stmt = $conn->prepare("INSERT INTO signatures (user_id, batch_id, document_name, document_number, document_subject, file_path, verify_code, signed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) {
             throw new Exception("DB Prepare Error: " . $conn->error);
        }
        
        $stmt->bind_param("isssssss", $userId, $batchId, $docName, $docNumber, $subject, $systemPath, $verifyCode, $signedAt);
        
        if (!$stmt->execute()) {
             throw new Exception("DB Execute Error: " . $stmt->error);
        }

        $processedCount++;

        // Cleanup QR temp
        if (file_exists($qrTempFile)) unlink($qrTempFile);

    } catch (Exception $e) {
        $errorMsg = "Failed to process " . $filename . ": " . $e->getMessage();
        $outputZip->addFromString('ERROR_' . $fileInfo['basename'] . '.txt', $errorMsg);
        $errors[] = $errorMsg;
        $errorCount++;
    }

    // Cleanup extracted PDF
    if (file_exists($tempPdfPath)) unlink($tempPdfPath);
}

$inputZip->close();
if (!$outputZip->close()) {
    if (ob_get_level()) { ob_end_clean(); }
    echo json_encode(['status' => 'error', 'message' => 'Failed to finalize output ZIP']);
    exit;
}

// Return Success JSON with ZIP URL
// The ZIP is currently in uploads/temp/BATCHID/filename.zip
// We can expose it via direct link if uploads/ is public.
// Or we can use a download wrapper. For simplicity, direct link.
$zipUrl = BASE_URL . '/uploads/temp/' . $batchId . '/' . $outputZipName;

// Don't delete yet. Frontend will request download, or we assume user downloads it.
// We should perhaps implement a cleanup mechanism or auto-delete after some time.
// For now, let's keep it. The user said "aman" (secure/safe), meaning stored.
// We can delete the original input ZIP to save space.
if (file_exists($originalZipPath)) unlink($originalZipPath);

if (ob_get_level()) { ob_end_clean(); }
echo json_encode([
    'status' => 'success',
    'processed' => $processedCount,
    'failed' => $errorCount,
    'errors' => $errors,
    'zip_url' => $zipUrl
]);
?>
