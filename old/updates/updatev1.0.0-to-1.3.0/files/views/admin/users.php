<?php
require_once __DIR__ . '/../../includes/auth_session.php';
requireAdmin();

$page_title = "Manajemen User";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Handle Form Submissions
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF Token Validation Failed.");
    }
    // CREATE or UPDATE
    if (isset($_POST['action']) && ($_POST['action'] == 'create' || $_POST['action'] == 'update')) {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $role = $_POST['role'];
        $position = $_POST['position'];
        
        if ($_POST['action'] == 'create') {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, position) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $email, $password, $role, $position);
            
            if ($stmt->execute()) {
                $success_msg = "User berhasil ditambahkan.";
            } else {
                $error_msg = "Gagal menambah user: " . $conn->error;
            }
        } elseif ($_POST['action'] == 'update') {
            $id = $_POST['user_id'];
            
            // Check if password is being reset
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET name=?, email=?, password=?, role=?, position=? WHERE id=?");
                $stmt->bind_param("sssssi", $name, $email, $password, $role, $position, $id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET name=?, email=?, role=?, position=? WHERE id=?");
                $stmt->bind_param("ssssi", $name, $email, $role, $position, $id);
            }
            
            if ($stmt->execute()) {
                $success_msg = "User berhasil diperbarui.";
            } else {
                $error_msg = "Gagal memperbarui user: " . $conn->error;
            }
        }
    }
    
    // DELETE
    if (isset($_POST['action']) && $_POST['action'] == 'delete') {
        $id = $_POST['user_id'];
        // Prevent deleting self
        if ($id == $_SESSION['user_id']) {
            $error_msg = "Anda tidak dapat menghapus akun sendiri.";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success_msg = "User berhasil dihapus.";
            } else {
                $error_msg = "Gagal menghapus user.";
            }
        }
    }
}

// Fetch Users
$result = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
?>

<div x-data="{ 
    showModal: false, 
    editMode: false, 
    formData: { id: '', name: '', email: '', role: 'user', position: '', password: '' } 
}">

    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-slate-800">Manajemen Pengguna</h2>
        <button @click="showModal = true; editMode = false; formData = {role: 'user'}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Tambah User
        </button>
    </div>

    <?php if($success_msg): ?>
        <script>
            Swal.fire({ icon: 'success', title: 'Berhasil', text: '<?php echo $success_msg; ?>', timer: 1500, showConfirmButton: false });
        </script>
    <?php endif; ?>
    <?php if($error_msg): ?>
        <script>
            Swal.fire({ icon: 'error', title: 'Error', text: '<?php echo $error_msg; ?>' });
        </script>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Nama</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Jabatan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Terdaftar</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-slate-200">
                <?php while($row = $result->fetch_assoc()): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10 bg-slate-200 rounded-full flex items-center justify-center text-slate-500 font-bold">
                                <?php echo strtoupper(substr($row['name'], 0, 1)); ?>
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-medium text-slate-900"><?php echo htmlspecialchars($row['name']); ?></div>
                                <div class="text-sm text-slate-500"><?php echo htmlspecialchars($row['email']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $row['role'] == 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800'; ?>">
                            <?php echo ucfirst($row['role']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                        <?php echo htmlspecialchars($row['position']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                        <?php echo date('d M Y', strtotime($row['created_at'])); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button @click="showModal = true; editMode = true; formData = {
                            id: '<?php echo $row['id']; ?>',
                            name: '<?php echo addslashes($row['name']); ?>',
                            email: '<?php echo addslashes($row['email']); ?>',
                            role: '<?php echo $row['role']; ?>',
                            position: '<?php echo addslashes($row['position']); ?>'
                        }" class="text-blue-600 hover:text-blue-900 mr-3">Edit</button>
                        
                        <?php if($row['id'] != $_SESSION['user_id']): ?>
                        <button onclick="confirmDelete(<?php echo $row['id']; ?>)" class="text-red-600 hover:text-red-900">Hapus</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal Form -->
    <div x-show="showModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showModal" class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="showModal" class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-slate-900" x-text="editMode ? 'Edit User' : 'Tambah User Baru'"></h3>
                        
                        <input type="hidden" name="action" :value="editMode ? 'update' : 'create'">
                        <input type="hidden" name="user_id" x-model="formData.id">

                        <div class="mt-4 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Nama Lengkap</label>
                                <input type="text" name="name" x-model="formData.name" required class="mt-1 block w-full border border-slate-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Email</label>
                                <input type="email" name="email" x-model="formData.email" required class="mt-1 block w-full border border-slate-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Password</label>
                                <input type="password" name="password" :required="!editMode" class="mt-1 block w-full border border-slate-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <p x-show="editMode" class="text-xs text-slate-500 mt-1">Kosongkan jika tidak ingin mengubah password.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Role</label>
                                <select name="role" x-model="formData.role" class="mt-1 block w-full border border-slate-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="user">User</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Jabatan</label>
                                <input type="text" name="position" x-model="formData.position" class="mt-1 block w-full border border-slate-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                        </div>
                    </div>
                    <div class="bg-slate-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            Simpan
                        </button>
                        <button type="button" @click="showModal = false" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-slate-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<!-- Hidden form for delete -->
<form id="deleteForm" method="POST" action="" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="user_id" id="deleteUserId">
</form>

<script>
function confirmDelete(id) {
    Swal.fire({
        title: 'Apakah anda yakin?',
        text: "Data user akan dihapus permanen!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('deleteUserId').value = id;
            document.getElementById('deleteForm').submit();
        }
    })
}
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
