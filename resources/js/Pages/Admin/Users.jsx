import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Swal from 'sweetalert2';

export default function Users({ auth, users }) {
    const [search, setSearch] = useState('');
    const [showModal, setShowModal] = useState(false);
    const [editMode, setEditMode] = useState(false);
    const [selectedUser, setSelectedUser] = useState(null);

    const [formData, setFormData] = useState({
        name: '',
        email: '',
        password: '',
        role: 'user',
        position: '',
        signature_prefix: 'DS'
    });

    const filteredUsers = users.filter(u => 
        u.name.toLowerCase().includes(search.toLowerCase()) || 
        u.email.toLowerCase().includes(search.toLowerCase()) || 
        (u.position && u.position.toLowerCase().includes(search.toLowerCase()))
    );

    const openAddModal = () => {
        setFormData({
            name: '',
            email: '',
            password: '',
            role: 'user',
            position: '',
            signature_prefix: 'DS'
        });
        setEditMode(false);
        setShowModal(true);
    };

    const openEditModal = (user) => {
        setSelectedUser(user);
        setFormData({
            name: user.name,
            email: user.email,
            password: '', // blank by default
            role: user.role,
            position: user.position || '',
            signature_prefix: user.signature_prefix || 'DS'
        });
        setEditMode(true);
        setShowModal(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        if (editMode) {
            router.patch(route('admin.users.update', selectedUser.id), formData, {
                onSuccess: () => {
                    setShowModal(false);
                    Swal.fire('Berhasil!', 'Data pengguna berhasil diperbarui.', 'success');
                },
                onError: (err) => {
                    Swal.fire('Gagal!', Object.values(err).join('<br>'), 'error');
                }
            });
        } else {
            if (!formData.password) {
                Swal.fire('Peringatan', 'Password wajib diisi untuk pengguna baru.', 'warning');
                return;
            }
            router.post(route('admin.users.store'), formData, {
                onSuccess: () => {
                    setShowModal(false);
                    Swal.fire('Berhasil!', 'Pengguna baru berhasil ditambahkan.', 'success');
                },
                onError: (err) => {
                    Swal.fire('Gagal!', Object.values(err).join('<br>'), 'error');
                }
            });
        }
    };

    const handleDelete = (id) => {
        if (id === auth.user.id) {
            Swal.fire('Peringatan', 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: 'Akun ini akan dihapus secara permanen beserta semua riwayat tanda tangannya!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                router.delete(route('admin.users.destroy', id), {
                    onSuccess: () => Swal.fire('Terhapus!', 'Pengguna berhasil dihapus.', 'success'),
                    onError: (err) => Swal.fire('Gagal!', err.error || 'Terjadi kesalahan.', 'error')
                });
            }
        });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-slate-800 dark:text-slate-200">Manajemen Pengguna</h2>}
        >
            <Head title="Manajemen Pengguna" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl">
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                        <div>
                            <p className="text-slate-500 dark:text-slate-400">Kelola daftar pengguna aplikasi, atur peran (role), jabatan, dan inisial paraf.</p>
                        </div>
                        <button
                            onClick={openAddModal}
                            className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-5 rounded-lg flex items-center shadow-md transition-colors text-sm"
                        >
                            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m2 4h-4a2 2 0 01-2-2V5a2 2 0 012-2h4a2 2 0 012 2v3m-6 4h10m-5-2v2"></path>
                            </svg>
                            Tambah User Baru
                        </button>
                    </div>

                    {/* Search bar */}
                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700 p-4 mb-6">
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari nama, email, atau jabatan..."
                            className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-4 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                        />
                    </div>

                    {/* Table list */}
                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700 overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 dark:divide-gray-700">
                                <thead className="bg-slate-50 dark:bg-gray-700 text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">
                                    <tr>
                                        <th className="px-6 py-4 text-left">Nama & Email</th>
                                        <th className="px-6 py-4 text-left">Jabatan</th>
                                        <th className="px-6 py-4 text-left">Role</th>
                                        <th className="px-6 py-4 text-left font-mono">Prefix TTE</th>
                                        <th className="px-6 py-4 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white dark:bg-gray-800 divide-y divide-slate-200 dark:divide-gray-700 text-sm">
                                    {filteredUsers.length === 0 ? (
                                        <tr>
                                            <td colSpan="5" className="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                                Tidak ada pengguna yang cocok dengan pencarian Anda.
                                            </td>
                                        </tr>
                                    ) : (
                                        filteredUsers.map((user) => (
                                            <tr key={user.id} className="hover:bg-slate-50 dark:hover:bg-gray-700 transition-colors">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="font-semibold text-slate-900 dark:text-white">{user.name}</div>
                                                    <div className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{user.email}</div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-slate-800 dark:text-slate-200">{user.position || '-'}</div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    {user.role === 'admin' ? (
                                                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
                                                            Administrator
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                                                            User Staff
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap font-mono text-xs text-slate-700 dark:text-slate-300">
                                                    {user.signature_prefix || 'DS'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <div className="flex items-center justify-end gap-3">
                                                        <button
                                                            onClick={() => openEditModal(user)}
                                                            className="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 transition-colors"
                                                        >
                                                            Edit
                                                        </button>
                                                        {user.id !== auth.user.id && (
                                                            <button
                                                                onClick={() => handleDelete(user.id)}
                                                                className="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 transition-colors"
                                                            >
                                                                Hapus
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {/* Add/Edit User Modal */}
            {showModal && (
                <div className="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black bg-opacity-50">
                    <div className="bg-white dark:bg-gray-800 w-full max-w-md rounded-xl shadow-lg border border-slate-200 dark:border-gray-700 p-6 relative">
                        <h3 className="text-lg font-bold text-slate-800 dark:text-white mb-4">
                            {editMode ? 'Edit Profil Pengguna' : 'Tambah Pengguna Baru'}
                        </h3>
                        
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">
                                    Nama Lengkap <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={formData.name}
                                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                    placeholder="Contoh: Budi Santoso"
                                    className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">
                                    Alamat Email <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="email"
                                    value={formData.email}
                                    onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                                    placeholder="Contoh: budi@domain.com"
                                    className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">
                                    Password {editMode && <span className="text-xs font-normal text-slate-400">(Biarkan kosong jika tidak diganti)</span>} {!editMode && <span className="text-red-500">*</span>}
                                </label>
                                <input
                                    type="password"
                                    value={formData.password}
                                    onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                                    placeholder="Masukkan password baru..."
                                    className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                    required={!editMode}
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">
                                    Jabatan
                                </label>
                                <input
                                    type="text"
                                    value={formData.position}
                                    onChange={(e) => setFormData({ ...formData, position: e.target.value })}
                                    placeholder="Contoh: Kepala Bidang Teknis"
                                    className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">
                                    Kode Singkat Inisial (TTE Prefix) <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={formData.signature_prefix}
                                    onChange={(e) => setFormData({ ...formData, signature_prefix: e.target.value.toUpperCase() })}
                                    maxLength="9"
                                    placeholder="Contoh: KBT"
                                    className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm font-mono focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                    required
                                />
                                <p className="text-[10px] text-slate-400 mt-1">Maksimal 9 karakter. Digunakan sebagai awalan kode TTE.</p>
                            </div>

                            <div>
                                <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">
                                    Peran / Role Akses
                                </label>
                                <select
                                    value={formData.role}
                                    onChange={(e) => setFormData({ ...formData, role: e.target.value })}
                                    className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                >
                                    <option value="user">User Staff (Akses Tanda Tangan)</option>
                                    <option value="admin">Administrator (Akses Sistem & User)</option>
                                </select>
                            </div>

                            <div className="flex justify-end gap-2 mt-6">
                                <button
                                    type="button"
                                    onClick={() => setShowModal(false)}
                                    className="py-2 px-4 border border-slate-200 dark:border-gray-600 text-slate-700 dark:text-slate-200 rounded-lg text-sm"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    className="py-2 px-6 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg text-sm shadow-md"
                                >
                                    Simpan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
