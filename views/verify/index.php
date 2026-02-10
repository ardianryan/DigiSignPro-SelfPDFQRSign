<?php
require_once __DIR__ . '/../../config/database.php';

$token = $_GET['token'] ?? $_GET['code'] ?? '';
$isValid = false;
$data = null;
$tzLabel = '';
$tz = date_default_timezone_get();
if ($tz === 'Asia/Jakarta') $tzLabel = 'WIB';
elseif ($tz === 'Asia/Makassar') $tzLabel = 'WITA';
elseif ($tz === 'Asia/Jayapura') $tzLabel = 'WIT';

if ($token) {
    // Query to get signature details along with signer info
    // We select s.* and user details
    $stmt = $conn->prepare("SELECT s.*, u.name as signer_name, u.position as signer_position 
                           FROM signatures s 
                           JOIN users u ON s.user_id = u.id 
                           WHERE s.verify_code = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $isValid = true;
        $data = $result->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="<?php echo defined('BASE_URL') ? BASE_URL : '..'; ?>/favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo defined('BASE_URL') ? BASE_URL : '..'; ?>/favicon.ico">
    <title>Verifikasi Dokumen - DigiSign</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-xl shadow-lg overflow-hidden border-t-4 <?php echo $isValid ? 'border-green-500' : 'border-red-500'; ?>">
        
        <?php if ($isValid && $data): ?>
            <!-- Header Valid -->
            <div class="bg-green-50 p-6 text-center border-b border-green-100">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 text-green-600 mb-3 shadow-sm">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <h1 class="text-xl font-bold text-green-800">Dokumen Valid</h1>
                <p class="text-green-600 text-xs mt-1">Terverifikasi oleh Sistem DigiSign</p>
            </div>

            <div class="p-6 space-y-5">
                <!-- Signer Info -->
                <div class="flex items-start space-x-3 bg-slate-50 p-4 rounded-lg border border-slate-100">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold">
                            <?php echo strtoupper(substr($data['signer_name'], 0, 1)); ?>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 uppercase tracking-wider font-semibold">Ditandatangani secara elektronik oleh</p>
                        <p class="font-bold text-slate-800"><?php echo htmlspecialchars($data['signer_name']); ?></p>
                        <p class="text-sm text-slate-600"><?php echo htmlspecialchars($data['signer_position']); ?></p>
                    </div>
                </div>

                <!-- Document Details -->
                <div class="space-y-3">
                    <div class="pb-3 border-b border-slate-100">
                        <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Nomor Dokumen</p>
                        <p class="font-medium text-slate-800"><?php echo htmlspecialchars($data['document_number']); ?></p>
                    </div>
                    
                    <div class="pb-3 border-b border-slate-100">
                        <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Perihal</p>
                        <p class="font-medium text-slate-800"><?php echo htmlspecialchars($data['document_subject']); ?></p>
                    </div>

                    <?php if(!empty($data['document_attachment'])): ?>
                    <div class="pb-3 border-b border-slate-100">
                        <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Lampiran</p>
                        <p class="font-medium text-slate-800"><?php echo htmlspecialchars($data['document_attachment']); ?></p>
                    </div>
                    <?php endif; ?>

                    <div>
                        <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Waktu Penandatanganan</p>
                        <p class="font-medium text-slate-800"><?php echo date('d F Y, H:i', strtotime($data['signed_at'])); ?> <?php echo $tzLabel; ?></p>
                    </div>
                    
                    <div class="pt-2">
                         <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Kode Verifikasi</p>
                         <code class="bg-slate-100 px-2 py-1 rounded text-sm font-mono text-slate-600 block mt-1 w-fit"><?php echo htmlspecialchars($token); ?></code>
                    </div>
                </div>
                
                <?php 
                $filePath = $data['file_path'];
                // Button visibility logic:
                // 1. Mandatory for Single/Bulk (Check if file_path is set)
                // 2. Optional for TTE QR (Only if file_path is set)
                // We trust file_path from DB. file_exists check might hide button if file server is separate or path issue.
                if(!empty($filePath)): 
                ?>
                <div class="mt-6 pt-4 border-t border-slate-100">
                    <a href="<?php echo BASE_URL . '/' . $filePath; ?>" target="_blank" class="flex items-center justify-center w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors shadow-md hover:shadow-lg">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        Lihat Dokumen Asli
                    </a>
                </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Header Invalid -->
            <div class="bg-red-50 p-6 text-center border-b border-red-100">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-100 text-red-600 mb-3 shadow-sm">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </div>
                <h1 class="text-xl font-bold text-red-800">Dokumen Tidak Valid</h1>
                <p class="text-red-600 text-xs mt-1">Data tidak ditemukan di sistem kami</p>
            </div>
            
            <div class="p-8 text-center">
                <p class="text-slate-600 mb-6">Kode verifikasi yang Anda masukkan tidak valid atau dokumen telah dihapus dari database kami.</p>
                <p class="text-xs text-slate-400 mb-6">Kode: <?php echo htmlspecialchars($token); ?></p>
                
                <a href="<?php echo BASE_URL; ?>/" class="text-blue-600 hover:text-blue-800 font-medium text-sm hover:underline">
                    &larr; Kembali ke Beranda
                </a>
            </div>
        <?php endif; ?>
        
        <div class="bg-slate-50 p-4 text-center border-t border-slate-100">
            <p class="text-xs text-slate-400">&copy; <?php echo date('Y'); ?> DigiSign. Verifikasi Dokumen Elektronik.</p>
        </div>
    </div>

</body>
</html>
