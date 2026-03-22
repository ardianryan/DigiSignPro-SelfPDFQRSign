<?php
require_once __DIR__ . '/../../includes/auth_session.php';
requireLogin();

$page_title = "Riwayat TTE QR";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$user_id = $_SESSION['user_id'];
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where = "user_id = $user_id AND signature_type = 'qr_manual'";
if ($search) {
    $where .= " AND (document_number LIKE '%$search%' OR document_subject LIKE '%$search%' OR verify_code LIKE '%$search%')";
}

// Count
$total_result = $conn->query("SELECT COUNT(*) as count FROM signatures WHERE $where");
$total_rows = $total_result->fetch_assoc()['count'];
$total_pages = ceil($total_rows / $limit);

// Data
$sql = "SELECT * FROM signatures WHERE $where ORDER BY signed_at DESC LIMIT $offset, $limit";
$result = $conn->query($sql);
?>

<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Layanan TTE QR</h2>
        <p class="text-slate-500">Generate QR Code untuk tanda tangan manual (dokumen fisik/luar sistem).</p>
    </div>
    <a href="qr_create" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg flex items-center shadow-lg transition-colors">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        Buat TTE QR Baru
    </a>
</div>

<!-- Search -->
<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 mb-6">
    <form method="GET" class="flex gap-2">
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari nomor dokumen, perihal..." class="flex-1 border border-slate-300 rounded-lg px-4 py-2 focus:ring-blue-500 focus:border-blue-500">
        <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white px-6 py-2 rounded-lg transition-colors">Cari</button>
    </form>
</div>

<!-- List -->
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4">Tanggal & ID</th>
                    <th class="px-6 py-4">Dokumen</th>
                    <th class="px-6 py-4">Status Dokumen</th>
                    <th class="px-6 py-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="font-medium text-slate-800"><?php echo date('d M Y H:i', strtotime($row['signed_at'])); ?></div>
                            <div class="text-xs text-slate-500 font-mono mt-1"><?php echo $row['verify_code']; ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-slate-800"><?php echo htmlspecialchars($row['document_number']); ?></div>
                            <div class="text-slate-500 mt-1"><?php echo htmlspecialchars($row['document_subject']); ?></div>
                            <?php if($row['document_attachment']): ?>
                                <div class="text-xs text-slate-400 mt-1 flex items-center">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                    <?php echo htmlspecialchars($row['document_attachment']); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <?php if (!empty($row['file_path'])): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    Terupload
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    Belum Upload
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <!-- Get QR -->
                                <a href="qr_create?step=2&id=<?php echo $row['id']; ?>" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg tooltip" title="Lihat/Download QR">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4h-4v-2h-2v4h6v-2h2v-2h2v-2h-2v2zM12 2h2v2h-2V2zm4 4v2h2V6h-2zm-4 4v2h2v-2h-2v2zM6 6h4v4H6V6zm14 0h-4v4h4V6zM6 16h4v4H6v-4z"></path></svg>
                                </a>
                                
                                <?php if (empty($row['file_path'])): ?>
                                    <!-- Upload Action -->
                                    <a href="qr_create?step=3&id=<?php echo $row['id']; ?>" class="p-2 text-green-600 hover:bg-green-50 rounded-lg tooltip" title="Upload Dokumen">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                    </a>
                                <?php else: ?>
                                    <!-- View Document -->
                                    <a href="<?php echo BASE_URL . '/' . $row['file_path']; ?>" target="_blank" class="p-2 text-purple-600 hover:bg-purple-50 rounded-lg tooltip" title="Lihat Dokumen">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="px-6 py-10 text-center text-slate-500">
                            Belum ada riwayat TTE QR. <a href="qr_create" class="text-blue-600 hover:underline">Buat baru sekarang</a>.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="px-6 py-4 border-t border-slate-200 flex justify-center">
        <div class="flex gap-2">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                   class="px-3 py-1 rounded-md text-sm <?php echo $page == $i ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
