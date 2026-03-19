<?php
require_once __DIR__ . '/../includes/auth_session.php';
require_once __DIR__ . '/../includes/Storage.php';
requireLogin();

$page_title = "Riwayat Tanda Tangan";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

// Handle Delete (Admin Only)
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (isAdmin()) {
        if ($_POST['action'] === 'delete') {
            $id = $_POST['signature_id'];
            
            // Get file path to delete file
            $stmt_get = $conn->prepare("SELECT file_path FROM signatures WHERE id = ?");
            $stmt_get->bind_param("i", $id);
            $stmt_get->execute();
            $res_get = $stmt_get->get_result();
            
            if ($res_get->num_rows > 0) {
                $file_data = $res_get->fetch_assoc();
                
                $stmt = $conn->prepare("DELETE FROM signatures WHERE id = ?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    // Delete physical file via Storage helper
                    Storage::delete($conn, $file_data['file_path']);
                    $msg = "success|Data riwayat berhasil dihapus.";
                } else {
                    $msg = "error|Gagal menghapus data: " . $conn->error;
                }
            }
        } elseif ($_POST['action'] === 'delete_batch') {
            $batchId = $_POST['batch_id'];
            
            // Get all file paths for this batch
            $stmt_get = $conn->prepare("SELECT file_path FROM signatures WHERE batch_id = ?");
            $stmt_get->bind_param("s", $batchId);
            $stmt_get->execute();
            $res_get = $stmt_get->get_result();
            
            while ($row = $res_get->fetch_assoc()) {
                Storage::delete($conn, $row['file_path']);
                $deletedFiles++;
            }
            
            // Delete records
            $stmt = $conn->prepare("DELETE FROM signatures WHERE batch_id = ?");
            $stmt->bind_param("s", $batchId);
            
            if ($stmt->execute()) {
                $msg = "success|Berhasil menghapus batch ($deletedFiles dokumen).";
            } else {
                $msg = "error|Gagal menghapus batch.";
            }
        }
    } else {
        $msg = "error|Akses Ditolak. Anda tidak memiliki izin menghapus.";
    }
}

// Fetch Data
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

if (isAdmin()) {
    $whereClause = "";
    if ($search) {
        $whereClause = "WHERE (s.document_name LIKE '%$search%' OR s.document_subject LIKE '%$search%' OR s.document_number LIKE '%$search%' OR s.verify_code LIKE '%$search%' OR u.name LIKE '%$search%')";
    }
    
    $sql = "SELECT s.*, u.name as user_name, u.position 
            FROM signatures s 
            JOIN users u ON s.user_id = u.id 
            $whereClause
            ORDER BY s.signed_at DESC";
    $result = $conn->query($sql);
} else {
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT s.*, u.name as user_name, u.position 
            FROM signatures s 
            JOIN users u ON s.user_id = u.id 
            WHERE s.user_id = ?";
            
    if ($search) {
        $sql .= " AND (s.document_name LIKE '%$search%' OR s.document_subject LIKE '%$search%' OR s.document_number LIKE '%$search%' OR s.verify_code LIKE '%$search%')";
    }
    
    $sql .= " ORDER BY s.signed_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
}

// Process Data for Grouping
$displayOrder = [];
$historyGroups = [];
$processedBatches = [];

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        if (!empty($row['batch_id'])) {
            $bId = $row['batch_id'];
            if (!in_array($bId, $processedBatches)) {
                $processedBatches[] = $bId;
                $groupEntry = [
                    'type' => 'batch',
                    'id' => $bId,
                    'first_row' => $row,
                    'items' => [$row]
                ];
                $displayOrder[] = &$groupEntry;
                $historyGroups[$bId] = &$groupEntry;
            } else {
                $historyGroups[$bId]['items'][] = $row;
            }
        } else {
            $displayOrder[] = [
                'type' => 'single',
                'data' => $row
            ];
        }
    }
}
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-slate-800">Riwayat Tanda Tangan</h2>
    <p class="text-slate-500">Daftar dokumen yang telah ditandatangani.</p>
</div>

<?php if($msg): 
    $parts = explode('|', $msg);
    $type = $parts[0];
    $text = $parts[1];
?>
    <script>
        Swal.fire({ icon: '<?php echo $type; ?>', title: '<?php echo ucfirst($type); ?>', text: '<?php echo $text; ?>' });
    </script>
<?php endif; ?>

<!-- Search -->
<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 mb-6">
    <form method="GET" class="flex gap-2">
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari nomor dokumen, perihal, kode verifikasi..." class="flex-1 border border-slate-300 rounded-lg px-4 py-2 focus:ring-blue-500 focus:border-blue-500">
        <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white px-6 py-2 rounded-lg transition-colors">Cari</button>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Dokumen</th>
                <?php if(isAdmin()): ?>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Penandatangan</th>
                <?php endif; ?>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Waktu Sign</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Kode Verifikasi</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Aksi</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-slate-200">
            <?php if(count($displayOrder) > 0): ?>
                <?php foreach($displayOrder as $item): ?>
                    <?php if($item['type'] === 'single'): $row = $item['data']; ?>
                        <!-- Single Row -->
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <svg class="w-8 h-8 text-red-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                    <div>
                                        <div class="text-sm font-medium text-slate-900">
                                            <?php echo htmlspecialchars($row['document_subject'] ?: $row['document_name']); ?>
                                        </div>
                                        <?php if($row['document_number']): ?>
                                            <div class="text-xs text-slate-500">No: <?php echo htmlspecialchars($row['document_number']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="ml-11 mt-1">
                                    <a href="<?php echo Storage::getFileUrl($conn, $row['file_path']); ?>" target="_blank" class="text-xs text-blue-500 hover:underline">Lihat File</a>
                                </div>
                            </td>
                            <?php if(isAdmin()): ?>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-slate-900"><?php echo htmlspecialchars($row['user_name']); ?></div>
                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($row['position']); ?></div>
                            </td>
                            <?php endif; ?>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                <?php echo date('d M Y H:i', strtotime($row['signed_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-slate-100 text-slate-800">
                                    <?php echo $row['verify_code']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <?php if(isAdmin()): ?>
                                <button onclick="confirmDeleteHistory(<?php echo $row['id']; ?>)" class="text-red-600 hover:text-red-900 flex items-center justify-end w-full">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    Hapus
                                </button>
                                <?php else: ?>
                                    <span class="text-slate-300 cursor-not-allowed">Hapus</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: // Batch ?>
                        <?php 
                            $batchId = $item['id'];
                            // Sanitize batchId for HTML attributes (replace dots with underscores)
                            $safeBatchId = str_replace('.', '_', $batchId);
                            $firstRow = $item['first_row'];
                            $count = count($item['items']);
                            $subject = "Bulk Sign Action";
                            if (!empty($firstRow['document_subject'])) $subject = $firstRow['document_subject'];
                        ?>
                        <tr class="bg-slate-50 hover:bg-slate-100 cursor-pointer" onclick="toggleBatch('<?php echo $safeBatchId; ?>')">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <svg class="w-8 h-8 text-blue-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                    <div>
                                        <div class="text-sm font-medium text-slate-900">
                                            <?php echo htmlspecialchars($subject); ?>
                                        </div>
                                        <div class="text-xs text-slate-500"><?php echo $count; ?> Dokumen (Bulk Sign)</div>
                                    </div>
                                </div>
                            </td>
                             <?php if(isAdmin()): ?>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-slate-900"><?php echo htmlspecialchars($firstRow['user_name']); ?></div>
                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($firstRow['position']); ?></div>
                            </td>
                            <?php endif; ?>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                <?php echo date('d M Y H:i', strtotime($firstRow['signed_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                    Bulk Group
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex flex-col items-end gap-2">
                                    <button onclick="event.stopPropagation(); toggleBatch('<?php echo $safeBatchId; ?>')" class="text-blue-600 hover:text-blue-900 text-xs flex items-center justify-end">
                                        <svg id="icon-<?php echo $safeBatchId; ?>" class="w-4 h-4 mr-1 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                        Lihat Detail
                                    </button>
                                    <?php if(isAdmin()): ?>
                                    <button onclick="event.stopPropagation(); confirmDeleteBatch('<?php echo $batchId; ?>')" class="text-red-600 hover:text-red-900 text-xs flex items-center justify-end">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        Hapus Semua
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php foreach($item['items'] as $row): ?>
                            <tr class="hover:bg-slate-50 batch-item-<?php echo $safeBatchId; ?>" style="display:none; background-color: #f8fafc;">
                                <td class="px-6 py-4 whitespace-nowrap pl-12 border-l-4 border-blue-200">
                                    <div class="flex items-center">
                                        <svg class="w-6 h-6 text-slate-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                        <div>
                                            <div class="text-sm font-medium text-slate-900">
                                                <?php echo htmlspecialchars($row['document_name']); ?>
                                            </div>
                                            <?php if($row['document_number']): ?>
                                                <div class="text-xs text-slate-500">No: <?php echo htmlspecialchars($row['document_number']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="ml-9 mt-1">
                                        <a href="<?php echo Storage::getFileUrl($conn, $row['file_path']); ?>" target="_blank" class="text-xs text-blue-500 hover:underline">Lihat File</a>
                                    </div>
                                </td>
                                 <?php if(isAdmin()): ?>
                                <td class="px-6 py-4 whitespace-nowrap"></td>
                                <?php endif; ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500"></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-slate-100 text-slate-800">
                                        <?php echo $row['verify_code']; ?>
                                    </span>
                                </td>
                                 <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <?php if(isAdmin()): ?>
                                    <button onclick="confirmDeleteHistory(<?php echo $row['id']; ?>)" class="text-red-600 hover:text-red-900 text-xs">
                                        Hapus
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?php echo isAdmin() ? 5 : 4; ?>" class="px-6 py-10 text-center text-slate-500">
                        Belum ada riwayat tanda tangan.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<form id="deleteHistoryForm" method="POST" action="" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="signature_id" id="deleteSigId">
</form>

<form id="deleteBatchForm" method="POST" action="" style="display:none;">
    <input type="hidden" name="action" value="delete_batch">
    <input type="hidden" name="batch_id" id="deleteBatchId">
</form>

<script>
function confirmDeleteHistory(id) {
    Swal.fire({
        title: 'Hapus Riwayat?',
        text: "Data dan file dokumen akan dihapus permanen!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('deleteSigId').value = id;
            document.getElementById('deleteHistoryForm').submit();
        }
    })
}

function confirmDeleteBatch(batchId) {
    Swal.fire({
        title: 'Hapus Batch?',
        text: "Semua dokumen dalam batch ini akan dihapus permanen!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus Semua!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('deleteBatchId').value = batchId;
            document.getElementById('deleteBatchForm').submit();
        }
    })
}
</script>

<script>
function toggleBatch(batchId) {
    const items = document.querySelectorAll('.batch-item-' + batchId);
    const icon = document.getElementById('icon-' + batchId);
    
    let isHidden = true;
    items.forEach(item => {
        if (item.style.display === 'none') {
            item.style.display = 'table-row';
            isHidden = false;
        } else {
            item.style.display = 'none';
        }
    });
    
    if (icon) {
        if (isHidden) {
            icon.style.transform = 'rotate(0deg)';
        } else {
            icon.style.transform = 'rotate(180deg)';
        }
    }
}
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
