<?php
require_once __DIR__ . '/../../includes/auth_session.php';
require_once __DIR__ . '/../../includes/Storage.php';
requireLogin();

require_once __DIR__ . '/../../vendor/autoload.php';
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use setasign\Fpdi\Fpdi;
use setasign\FpdiProtection\FpdiProtection;

$page_title = "Buat TTE QR";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];
$error = '';

// Step 1: Handle CreateƒQR
if ($step == 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF Token Validation Failed.");
    }
    $doc_number = trim($_POST['document_number']);
    $subject = trim($_POST['subject']);
    $attachment = trim($_POST['attachment']);
    $signed_date = $_POST['signed_at']; // YYYY-MM-DD
    $pdf_pass = $_POST['pdf_password'] ?? '';
    
    if (empty($doc_number) || empty($subject)) {
        $error = "Nomor Dokumen dan Perihal wajib diisi.";
    } elseif (empty($pdf_pass)) {
        $error = "Password Parafrase wajib diisi.";
    } else {
        // Store password in session for Step 3
        $_SESSION['tte_qr_password'] = $pdf_pass;

        // Append current time to date for signed_at
        $signed_at = $signed_date . ' ' . date('H:i:s');
        
        // Fetch User Prefix
        $prefix_sql = "SELECT signature_prefix FROM users WHERE id = ?";
        $stmt = $conn->prepare($prefix_sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $prefix = $stmt->get_result()->fetch_assoc()['signature_prefix'] ?? 'DS';
        if (empty($prefix)) $prefix = 'DS';
        
        // Generate Unique Verify Code
        $verify_code = $prefix . '-TTE-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
        
        $sql = "INSERT INTO signatures (user_id, document_number, document_subject, document_attachment, verify_code, signed_at, signature_type) VALUES (?, ?, ?, ?, ?, ?, 'qr_manual')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssss", $user_id, $doc_number, $subject, $attachment, $verify_code, $signed_at);
        
        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            // Redirect to Step 2
            echo "<script>window.location.href = 'qr_create?step=2&id=" . $new_id . "';</script>";
            exit;
        } else {
            $error = "Gagal membuat TTE QR: " . $conn->error;
        }
    }
}

// Fetch Data for Step 2 & 3
$data = null;
$qrImage = null;
$verify_code = '';

if ($step >= 2 && $id > 0) {
    $stmt = $conn->prepare("SELECT * FROM signatures WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    
    if (!$data) {
        echo "<script>alert('Data tidak ditemukan.'); window.location.href='qr_list';</script>";
        exit;
    }
    
    $verify_code = $data['verify_code'];
    $verify_url = BASE_URL . "/verify/?token=" . $verify_code;
    
    // Generate QR
    $options = new QROptions([
        'version'    => QRCode::VERSION_AUTO,
        'outputType' => QRCode::OUTPUT_IMAGE_PNG,
        'eccLevel'   => QRCode::ECC_L,
        'scale'      => 10,
    ]);
    $qr = new QRCode($options);
    $qrImage = $qr->render($verify_url);
}

// Step 3: Handle Upload
if ($step == 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF Token Validation Failed.");
    }
    if (isset($_POST['skip'])) {
        echo "<script>window.location.href = '" . BASE_URL . "/sign/qr_list';</script>";
        exit;
    }
    
    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == 0) {
        $file = $_FILES['pdf_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($ext != 'pdf') {
            $error = "Hanya file PDF yang diperbolehkan.";
        } else {
            // Fetch subject from DB if not already available in $data (it is available in $data at step 2, but here we are in POST step 3)
            // We can fetch it again or pass it. 
            // Let's fetch it to be safe and ensure we have the correct subject for this ID.
            $stmt_subj = $conn->prepare("SELECT document_subject FROM signatures WHERE id = ?");
            $stmt_subj->bind_param("i", $id);
            $stmt_subj->execute();
            $subj_res = $stmt_subj->get_result()->fetch_assoc();
            $doc_subject = $subj_res['document_subject'] ?? 'document';
            
            // Sanitize subject for filename
            $safe_subject = preg_replace('/[^A-Za-z0-9_-]/', '_', $doc_subject);
            if (empty($safe_subject)) $safe_subject = 'document';
            
            $filename = $safe_subject . '_' . $verify_code . '_signed.pdf';
            
            $target_dir = __DIR__ . '/../../public/uploads/signatures/';
            if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
            
            $target_file = $target_dir . $filename;
            
            // Get password from session OR from POST (for robustness in production)
            $pdf_pass = $_SESSION['tte_qr_password'] ?? $_POST['pdf_password'] ?? '';
            
            if (empty($pdf_pass)) {
                // If both are empty, then it's a real session loss/missing data
                $error = "Password Parafrase dibutuhkan. Silakan isi kembali di bawah.";
            } else {
                $uploadSuccess = false;

                // Use FPDI to protect
                try {
                    ini_set('display_errors', 0);
                    error_reporting(0);
                    
                    $pdf = new FpdiProtection('P', 'mm', 'A4', true);
                    $pageCount = $pdf->setSourceFile($file['tmp_name']);
                    
                    for ($p = 1; $p <= $pageCount; $p++) {
                        $tplId = $pdf->importPage($p);
                        $size = $pdf->getTemplateSize($tplId);
                        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                        $pdf->useTemplate($tplId);
                    }
                    
                    $permissions = FpdiProtection::PERM_PRINT | FpdiProtection::PERM_COPY | FpdiProtection::PERM_ACCESSIBILITY;
                    $pdf->setProtection($permissions, '', $pdf_pass);
                    
                    $pdf->Output('F', $target_file);
                    $uploadSuccess = true;
                } catch (Throwable $e) {
                    $error = "Gagal memproses proteksi PDF: " . $e->getMessage();
                    $uploadSuccess = false;
                }

                if ($uploadSuccess) {
                    // Start processing for storage (Local, S3 or Both)
                    try {
                        $systemPath = Storage::upload($conn, $target_file, $filename);
                        
                        // Delete the local temp if not in local mode
                        $settings_res = $conn->query("SELECT storage_mode FROM app_settings WHERE id = 1");
                        $settings = $settings_res->fetch_assoc();
                        if (($settings['storage_mode'] ?? 'local') === 's3') {
                            @unlink($target_file);
                        }
                        
                        $stmt = $conn->prepare("UPDATE signatures SET file_path = ?, signed_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $stmt->bind_param("si", $systemPath, $id);
                        $stmt->execute();
                        
                        $success = "Dokumen berhasil diunggah dan disimpan.";
                    } catch (Throwable $e) {
                        $error = "Gagal menyimpan file: " . $e->getMessage();
                    }
                }
                // Clear session
                if (isset($_SESSION['tte_qr_password'])) unset($_SESSION['tte_qr_password']);
                
                echo "<script>window.location.href = 'qr_list';</script>";
                exit;
            }
        }
    } else {
        $error = "Pilih file PDF terlebih dahulu.";
    }
}
?>

<div class="max-w-3xl mx-auto">
    <div class="mb-8">
        <h2 class="text-3xl font-bold text-slate-800">Wizard TTE QR</h2>
        <p class="text-slate-500 mt-1">Generate QR Code untuk tanda tangan manual pada dokumen eksternal (Canva, Word, dll).</p>
    </div>

    <!-- Stepper -->
    <div class="mb-8">
        <div class="flex items-center justify-between relative">
            <div class="absolute left-0 top-1/2 transform -translate-y-1/2 w-full h-1 bg-slate-200 -z-10"></div>
            
            <!-- Step 1 -->
            <div class="flex flex-col items-center bg-slate-50 px-2">
                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold <?php echo $step >= 1 ? 'bg-blue-600 text-white' : 'bg-slate-300 text-slate-600'; ?>">1</div>
                <span class="text-xs font-medium mt-1 <?php echo $step >= 1 ? 'text-blue-600' : 'text-slate-500'; ?>">Input Data</span>
            </div>
            
            <!-- Step 2 -->
            <div class="flex flex-col items-center bg-slate-50 px-2">
                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold <?php echo $step >= 2 ? 'bg-blue-600 text-white' : 'bg-slate-300 text-slate-600'; ?>">2</div>
                <span class="text-xs font-medium mt-1 <?php echo $step >= 2 ? 'text-blue-600' : 'text-slate-500'; ?>">Generate QR</span>
            </div>
            
            <!-- Step 3 -->
            <div class="flex flex-col items-center bg-slate-50 px-2">
                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold <?php echo $step >= 3 ? 'bg-blue-600 text-white' : 'bg-slate-300 text-slate-600'; ?>">3</div>
                <span class="text-xs font-medium mt-1 <?php echo $step >= 3 ? 'text-blue-600' : 'text-slate-500'; ?>">Upload (Opsional)</span>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
            <span class="block sm:inline"><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        
        <!-- STEP 1: INPUT DATA -->
        <?php if ($step == 1): ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
            <h3 class="text-lg font-bold text-slate-800 mb-4">Lengkapi Detail Dokumen</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nomor Dokumen <span class="text-red-500">*</span></label>
                    <input type="text" name="document_number" required class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Contoh: 001/SK/2024">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Perihal <span class="text-red-500">*</span></label>
                    <input type="text" name="subject" required class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Contoh: Surat Keputusan Pengangkatan">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Lampiran (Opsional)</label>
                    <input type="text" name="attachment" class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Contoh: 1 Berkas">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal Tanda Tangan</label>
                    <input type="date" name="signed_at" value="<?php echo date('Y-m-d'); ?>" required class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-slate-500 mt-1">Default adalah hari ini.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Password Parafrase <span class="text-red-500">*</span></label>
                    <input type="text" name="pdf_password" required placeholder="Kunci edit PDF..." class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-slate-500 mt-1">Wajib diisi untuk keamanan dokumen.</p>
                </div>
                
                <div class="pt-4 flex justify-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition-colors">
                        Lanjut: Buat QR &rarr;
                    </button>
                </div>
            </div>
        </form>
        <?php endif; ?>

        <!-- STEP 2: SHOW QR -->
        <?php if ($step == 2 && $qrImage): ?>
        <div class="text-center">
            <h3 class="text-lg font-bold text-slate-800 mb-2">QR Code Berhasil Dibuat!</h3>
            <p class="text-slate-500 mb-6">Silakan download QR Code ini dan tempelkan pada dokumen Anda (Canva, Word, dll).</p>
            
            <div class="inline-block p-4 border-2 border-slate-200 rounded-xl mb-6 bg-white">
                <img src="<?php echo $qrImage; ?>" alt="QR Code" class="w-64 h-64 mx-auto">
                <p class="text-xs text-slate-400 mt-2 font-mono"><?php echo $verify_code; ?></p>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="<?php echo $qrImage; ?>" download="QR_<?php echo $verify_code; ?>.png" class="flex items-center justify-center bg-slate-800 hover:bg-slate-900 text-white font-bold py-2 px-6 rounded-lg transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    Download QR Code
                </a>
                
                <a href="qr_create?step=3&id=<?php echo $id; ?>" class="flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition-colors">
                    Lanjut: Upload Dokumen &rarr;
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- STEP 3: UPLOAD (OPTIONAL) -->
        <?php if ($step == 3): ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
            <div class="text-center mb-6">
                <h3 class="text-lg font-bold text-slate-800 mb-2">Upload Dokumen Final (Opsional)</h3>
                <p class="text-slate-500 text-sm">
                    Jika Anda mengupload dokumen yang sudah ditempel QR Code, tombol "Lihat Dokumen" akan muncul saat QR discan.<br>
                    Jika tidak, halaman verifikasi hanya akan menampilkan validitas data.
                </p>
            </div>

            <div 
                x-data="{ isDragging: false }"
                class="border-2 border-dashed rounded-xl p-8 text-center transition-colors mb-6"
                :class="{'border-blue-500 bg-blue-50': isDragging, 'border-slate-300 hover:bg-slate-50': !isDragging}"
                @dragover.prevent="isDragging = true"
                @dragleave.prevent="isDragging = false"
                @drop.prevent="isDragging = false; $refs.fileInput.files = $event.dataTransfer.files; document.getElementById('file-name').innerText = $event.dataTransfer.files[0].name"
            >
                <input x-ref="fileInput" type="file" name="pdf_file" id="pdf_file" accept=".pdf" class="hidden" onchange="document.getElementById('file-name').innerText = this.files[0].name">
                <label for="pdf_file" class="cursor-pointer flex flex-col items-center justify-center">
                    <svg class="w-12 h-12 text-slate-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                    <span class="text-blue-600 font-medium hover:underline">Pilih File PDF</span>
                    <span class="text-slate-500 text-sm mt-1">atau drag & drop di sini</span>
                    <p id="file-name" class="mt-4 font-semibold text-slate-700"></p>
                </label>
            </div>

            <div class="mb-6 p-4 bg-slate-50 rounded-lg border border-slate-200">
                <label class="block text-sm font-medium text-slate-700 mb-1">Konfirmasi Password Parafrase <span class="text-red-500">*</span></label>
                <input type="text" name="pdf_password" value="<?php echo htmlspecialchars($_SESSION['tte_qr_password'] ?? ''); ?>" required placeholder="Masukkan password yang sama dengan Step 1..." class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                <p class="text-xs text-slate-500 mt-1">Wajib diisi untuk memproteksi PDF yang Anda upload.</p>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-4 justify-between pt-4 border-t border-slate-100">
                <button type="submit" name="skip" value="1" class="text-slate-500 hover:text-slate-700 font-medium py-2 px-4 transition-colors">
                    Lewati, Jangan Upload
                </button>
                
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg transition-colors shadow-lg shadow-green-200">
                    Upload & Selesai
                </button>
            </div>
        </form>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
