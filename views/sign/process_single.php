<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/auth_session.php';
require_once __DIR__ . '/../../includes/Storage.php';
requireLogin();

use setasign\Fpdi\Fpdi;
use setasign\FpdiProtection\FpdiProtection;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['status' => 'error', 'message' => 'CSRF Token Validation Failed.']);
    exit;
}

if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== 0) {
    echo json_encode(['status' => 'error', 'message' => 'Upload File Gagal']);
    exit;
}

try {
    // 1. Setup Data
    $user_id = $_SESSION['user_id'];

    // Check Max Upload Size
    $settings_sql = "SELECT max_upload_size FROM app_settings WHERE id = 1";
    $settings_result = $conn->query($settings_sql);
    $settings = $settings_result->fetch_assoc();
    $max_size = $settings['max_upload_size'] ?? 10485760; // Default 10MB

    if ($_FILES['pdf_file']['size'] > $max_size) {
        $mb = $max_size / 1024 / 1024;
        echo json_encode(['status' => 'error', 'message' => "Ukuran file melebihi batas ($mb MB)"]);
        exit;
    }

    $x = floatval($_POST['x']);
    $y = floatval($_POST['y']);
    $pageToSign = intval($_POST['page']);
    
    // Additional Fields
    $docNumber = $_POST['document_number'] ?? '';
    $docSubject = $_POST['document_subject'] ?? '';
    $docAttachment = $_POST['document_attachment'] ?? '';
    $signedDate = $_POST['signed_date'] ?? date('Y-m-d');
    $showQrCaption = isset($_POST['show_qr_caption']) && $_POST['show_qr_caption'] == 1;
    $qrCaptionPosition = $_POST['qr_caption_position'] ?? 'bottom';

    $signerName = '';
    $signerPosition = '';
    $userPrefix = 'DS';

    // Fetch User Info (Name, Position, Prefix) regardless of showQrCaption because we need prefix
    $userSql = "SELECT name, position, signature_prefix FROM users WHERE id = ?";
    $stmtUser = $conn->prepare($userSql);
    $stmtUser->bind_param("i", $user_id);
    $stmtUser->execute();
    $userRes = $stmtUser->get_result();
    if ($userData = $userRes->fetch_assoc()) {
        $signerName = $userData['name'];
        $signerPosition = $userData['position'] ?? '';
        $userPrefix = $userData['signature_prefix'] ?? 'DS';
        if (empty($userPrefix)) $userPrefix = 'DS';
    }
    
    // Generate Verify Code
    $code = $userPrefix . '-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    // Use dynamic BASE_URL
    $verifyUrl = BASE_URL . "/verify/?token=" . $code;

    // 2. Generate QR Code
    $options = new QROptions([
        'version'    => QRCode::VERSION_AUTO,
        'outputType' => QRCode::OUTPUT_IMAGE_PNG,
        'eccLevel'   => QRCode::ECC_L,
        'scale'      => 5,
    ]);
    
    $qrOutputInterface = new QRCode($options);
    $qrImage = $qrOutputInterface->render($verifyUrl);
    
    // Save QR to temp file (FPDF needs a file path or data stream, simpler with file)
    // Remove "data:image/png;base64," header
    $base64 = explode(',', $qrImage)[1];
    $qrTempFile = __DIR__ . '/../../public/uploads/temp/qr_' . uniqid() . '.png';
    file_put_contents($qrTempFile, base64_decode($base64));

    // 3. Process PDF
    // Enable ArcFour fallback for OpenSSL 3 compatibility
    $pdf = new FpdiProtection('P', 'mm', 'A4', true);
    
    $uploadFile = $_FILES['pdf_file']['tmp_name'];
    
    try {
        $pageCount = $pdf->setSourceFile($uploadFile);
    } catch (Exception $e) {
        // Handle PDF Compression Error
        if (strpos($e->getMessage(), 'compression technique') !== false) {
             echo json_encode(['status' => 'error', 'message' => 'PDF menggunakan kompresi yang tidak didukung. Silakan "Print to PDF" atau simpan ulang sebagai PDF versi 1.4 agar kompatibel.']);
             exit;
        }
        echo json_encode(['status' => 'error', 'message' => 'Gagal membaca PDF: ' . $e->getMessage()]);
        exit;
    }

    // Loop through all pages
    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
        $templateId = $pdf->importPage($pageNo);
        $size = $pdf->getTemplateSize($templateId);
        
        // Add Page with same orientation/size
        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->useTemplate($templateId);

        // Stamp on the specific page
        if ($pageNo === $pageToSign) {
            // QR Size in mm (approx 24mm ~ 1 inch)
            $qrSize = 25; 
            $pdf->Image($qrTempFile, $x, $y, $qrSize, $qrSize);
            
            // Add Text below QR if requested
            if ($showQrCaption) {
                // Line heights
                $lh_small = 3;
                $lh_large = 4; // For font size 10
                
                // Calculate Total Height
                // ID (3) + Ditandatangani (3) + Name (4) + Position (3 if exists)
                $totalHeight = $lh_small + $lh_small + $lh_large;
                if (!empty($signerPosition)) {
                    $totalHeight += $lh_small;
                }
                
                if ($qrCaptionPosition === 'right') {
                    // Position to the right of QR (Vertically Centered)
                    // Center Y = y + (qrSize - totalHeight) / 2
                    $startY = $y + ($qrSize - $totalHeight) / 2;
                    $startX = $x + $qrSize + 1;
                    
                    $pdf->SetFont('Arial', '', 6);
                    $pdf->SetXY($startX, $startY);
                    $pdf->Cell(50, $lh_small, "ID : " . $code, 0, 1, 'L');
                    
                    $pdf->SetX($startX);
                    $pdf->Cell(50, $lh_small, "Ditandatangani secara elektronik oleh", 0, 1, 'L');
                    
                    // Name: Bold, Size 10
                    $pdf->SetFont('Arial', 'B', 10);
                    $pdf->SetX($startX);
                    $pdf->Cell(50, $lh_large, $signerName, 0, 1, 'L');
                    
                    // Position: Bold, Normal (Size 6)
                    if (!empty($signerPosition)) {
                        $pdf->SetFont('Arial', 'B', 6);
                        $pdf->SetX($startX);
                        $pdf->Cell(50, $lh_small, $signerPosition, 0, 1, 'L');
                    }
                    
                } else {
                    // Default: Bottom (Horizontally Centered)
                    // Center X relative to QR center
                    // QR Center = x + qrSize/2
                    // We define a text box width, say 60mm
                    $textBoxWidth = 60;
                    $startX = $x + ($qrSize - $textBoxWidth) / 2;
                    $startY = $y + $qrSize + 0.5;
                    
                    $pdf->SetFont('Arial', '', 6);
                    $pdf->SetXY($startX, $startY);
                    $pdf->Cell($textBoxWidth, $lh_small, "ID : " . $code, 0, 1, 'C');
                    
                    $pdf->SetX($startX);
                    $pdf->Cell($textBoxWidth, $lh_small, "Ditandatangani secara elektronik oleh", 0, 1, 'C');
                    
                    // Name: Bold, Size 10
                    $pdf->SetFont('Arial', 'B', 10);
                    $pdf->SetX($startX);
                    $pdf->Cell($textBoxWidth, $lh_large, $signerName, 0, 1, 'C');
                    
                    // Position: Bold, Normal (Size 6)
                    if (!empty($signerPosition)) {
                        $pdf->SetFont('Arial', 'B', 6);
                        $pdf->SetX($startX);
                        $pdf->Cell($textBoxWidth, $lh_small, $signerPosition, 0, 1, 'C');
                    }
                }
            }
        }
    }

    $permissions = FpdiProtection::PERM_PRINT | FpdiProtection::PERM_COPY | FpdiProtection::PERM_ACCESSIBILITY;
    $pdf->setProtection($permissions, '', $pdf_pass);

    // Save to temp first for further storage processing
    $tempSigned = __DIR__ . '/../../public/uploads/temp/signed_' . uniqid() . '.pdf';
    if (!file_exists(dirname($tempSigned))) mkdir(dirname($tempSigned), 0777, true);
    $pdf->Output('F', $tempSigned);

    // 4. Upload to Storage (Local, S3, or Both)
    $safe_subject = preg_replace('/[^A-Za-z0-9_-]/', '_', $docSubject);
    if (empty($safe_subject)) $safe_subject = 'document';
    $filename = $safe_subject . '_' . $code . '_signed.pdf';
    
    $outputPath = Storage::upload($conn, $tempSigned, $filename);
    
    // Clean up signed temp
    if (file_exists($tempSigned)) unlink($tempSigned);

    // 5. Clean up temp
    unlink($qrTempFile);

    // 6. Save to DB
    $docName = $_FILES['pdf_file']['name'];
    $stmt = $conn->prepare("INSERT INTO signatures (user_id, document_name, document_number, document_subject, document_attachment, file_path, verify_code, signed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    // Append Time to signedDate if it's just a date
    $signedTimestamp = $signedDate . ' ' . date('H:i:s');
    
    $stmt->bind_param("isssssss", $user_id, $docName, $docNumber, $docSubject, $docAttachment, $outputPath, $code, $signedTimestamp);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'file_path' => $outputPath,
            'verify_code' => $code
        ]);
    } else {
        throw new Exception("Database Error: " . $stmt->error);
    }

} catch (Throwable $e) {
    if (ob_get_level()) ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
