<?php
require_once __DIR__ . '/../../includes/auth_session.php';
requireAdmin();
require_once __DIR__ . '/../../includes/Storage.php';

$page_title = "Manajemen Storage";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Handle Actions
$msg = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete') {
        $key = $_POST['key'];
        // Use s3:// prefix to force S3 deletion in Storage::delete
        Storage::delete($conn, 's3://' . $key);
        $msg = "File berhasil dihapus dari S3.";
    }
}

// Fetch Stats and Files
$stats = Storage::getStats($conn);
$files = Storage::listFiles($conn);

// Sort files by LastModified descending
usort($files, function($a, $b) {
    return $b['LastModified'] <=> $a['LastModified'];
});

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>

<div class="mb-8 flex justify-between items-center">
    <div>
        <h2 class="text-3xl font-bold text-slate-800">Manajemen Storage</h2>
        <p class="text-slate-500 mt-1">Pantau dan kelola berkas yang tersimpan di Cloudflare R2 / S3.</p>
    </div>
    <div class="flex gap-3">
        <a href="<?php echo BASE_URL; ?>/admin/settings" class="bg-white border border-slate-200 text-slate-700 px-4 py-2 rounded-lg hover:bg-slate-50 transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
            Konfigurasi API
        </a>
        <button onclick="window.location.reload()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            Refresh
        </button>
    </div>
</div>

<?php if ($msg): ?>
    <div class="mb-6 p-4 rounded-lg bg-green-100 text-green-700 flex items-center gap-3">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        <?php echo $msg; ?>
    </div>
<?php endif; ?>

<?php if (!$stats): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-8 text-center">
        <svg class="w-16 h-16 text-amber-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        <h3 class="text-xl font-bold text-amber-800">S3 Tidak Aktif</h3>
        <p class="text-amber-700 mt-2 max-w-md mx-auto">Fitur penyimpanan S3/R2 saat ini dinonaktifkan. Silakan aktifkan di halaman Pengaturan untuk mengelola berkas di cloud.</p>
        <a href="<?php echo BASE_URL; ?>/admin/settings" class="mt-6 inline-block bg-amber-600 text-white px-6 py-2 rounded-lg hover:bg-amber-700 transition-colors">Ke Pengaturan</a>
    </div>
<?php else: ?>
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
            <p class="text-sm font-medium text-slate-500 mb-1">Total Berkas</p>
            <p class="text-2xl font-bold text-slate-800"><?php echo number_format($stats['count']); ?></p>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
            <p class="text-sm font-medium text-slate-500 mb-1">Total Penyimpanan</p>
            <p class="text-2xl font-bold text-slate-800"><?php echo formatBytes($stats['size']); ?></p>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
            <p class="text-sm font-medium text-slate-500 mb-1">Bucket / Region</p>
            <p class="text-lg font-bold text-slate-800 truncate"><?php echo $stats['bucket']; ?> / <?php echo $stats['region']; ?></p>
        </div>
    </div>

    <!-- File List -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-bold text-slate-800">Daftar Berkas di S3</h3>
            <span class="text-xs font-medium px-2 py-1 rounded bg-blue-50 text-blue-600 uppercase tracking-wider">Storage Mode: <?php echo strtoupper($stats['mode']); ?></span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Nama Berkas (Key)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Ukuran</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Terakhir Diubah</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-slate-200">
                    <?php if (empty($files)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-slate-500 italic">Tidak ada berkas ditemukan di bucket ini.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($files as $file): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <svg class="w-6 h-6 text-blue-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                        <div class="text-sm font-medium text-slate-900 truncate max-w-xs xl:max-w-lg" title="<?php echo $file['Key']; ?>">
                                            <?php echo htmlspecialchars($file['Key']); ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                    <?php echo formatBytes($file['Size']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                    <?php echo date('d M Y H:i:s', strtotime($file['LastModified'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-3">
                                        <a href="<?php echo Storage::getFileUrl($conn, 's3://' . $file['Key']); ?>" target="_blank" class="text-blue-600 hover:text-blue-900">Lihat</a>
                                        <button onclick="confirmDelete('<?php echo addslashes($file['Key']); ?>')" class="text-red-600 hover:text-red-900">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="key" id="deleteKey">
</form>

<script>
function confirmDelete(key) {
    Swal.fire({
        title: 'Hapus Berkas?',
        text: "Berkas akan dihapus permanen dari S3: " + key,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('deleteKey').value = key;
            document.getElementById('deleteForm').submit();
        }
    })
}
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
