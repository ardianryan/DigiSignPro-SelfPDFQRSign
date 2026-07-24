import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, Link } from '@inertiajs/react';
import Swal from 'sweetalert2';

export default function Storage({ auth, stats, files, storageMode }) {
    const isS3Enabled = storageMode !== 'local';

    const formatBytes = (bytes, decimals = 2) => {
        if (!bytes || bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    };

    const handleDelete = (key) => {
        Swal.fire({
            title: 'Hapus Berkas?',
            text: 'Berkas ini akan dihapus secara permanen dari bucket S3 Cloud Anda!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                router.delete(route('admin.storage.destroy'), {
                    data: { key },
                    onSuccess: () => Swal.fire('Terhapus!', 'Berkas berhasil dihapus.', 'success'),
                    onError: (err) => Swal.fire('Gagal!', err.error || 'Gagal menghapus berkas.', 'error')
                });
            }
        });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-slate-800 dark:text-slate-200">Manajemen Storage</h2>}
        >
            <Head title="Manajemen Storage" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl">
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                        <div>
                            <p className="text-slate-500 dark:text-slate-400">
                                Pantau dan kelola seluruh berkas tanda tangan yang tersimpan di Cloud Object Storage bucket: {' '}
                                <span className="font-mono text-blue-600 dark:text-blue-400 font-bold">{stats?.bucket || '-'}</span>
                            </p>
                            <p className="text-xs text-slate-400 dark:text-gray-500 mt-1">Direktori aktif: <span className="font-mono">{stats?.directory || '/'}</span></p>
                        </div>
                        <div className="flex gap-2">
                            <Link
                                href={route('admin.settings.edit')}
                                className="bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-600 text-slate-700 dark:text-slate-200 px-4 py-2 rounded-lg hover:bg-slate-50 dark:hover:bg-gray-700 transition-colors text-sm font-semibold flex items-center gap-1.5"
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                </svg>
                                Konfigurasi API S3
                            </Link>
                            <button
                                onClick={() => router.reload()}
                                className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors text-sm font-semibold flex items-center gap-1.5 shadow-sm"
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                Refresh
                            </button>
                        </div>
                    </div>

                    {/* Check if S3 is active */}
                    {!isS3Enabled ? (
                        <div className="bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900 rounded-xl p-10 text-center">
                            <svg className="w-16 h-16 text-amber-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <h3 className="text-xl font-bold text-amber-800 dark:text-amber-300">Penyimpanan S3 Cloud Dinonaktifkan</h3>
                            <p className="text-amber-700 dark:text-amber-400 mt-2 max-w-md mx-auto text-sm">
                                Aplikasi saat ini disetel untuk hanya menggunakan penyimpanan server lokal. Silakan aktifkan metode S3/Both di halaman Pengaturan untuk mengelola berkas di Cloud.
                            </p>
                            <Link
                                href={route('admin.settings.edit')}
                                className="mt-6 inline-block bg-amber-600 hover:bg-amber-700 text-white font-bold px-6 py-2 rounded-lg text-sm transition-colors shadow-sm"
                            >
                                Ke Pengaturan
                            </Link>
                        </div>
                    ) : stats?.error ? (
                        // Connection or fetch error
                        <div className="bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900 rounded-xl p-6 text-center text-red-800 dark:text-red-400">
                            <svg className="w-12 h-12 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <h4 className="font-bold mb-1">Gagal Terhubung ke Object Storage</h4>
                            <p className="text-sm">{stats.error}</p>
                        </div>
                    ) : (
                        // Standard S3 files view
                        <div className="space-y-6">
                            {/* Stat cards */}
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div className="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700">
                                    <p className="text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">Total Berkas di Bucket</p>
                                    <p className="text-2xl font-bold text-slate-800 dark:text-white">{stats?.count || 0}</p>
                                </div>
                                <div className="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700">
                                    <p className="text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">Ukuran Kumulatif Penyimpanan</p>
                                    <p className="text-2xl font-bold text-slate-800 dark:text-white">{formatBytes(stats?.size || 0)}</p>
                                </div>
                                <div className="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700">
                                    <p className="text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">Region Aktif / Mode</p>
                                    <p className="text-2xl font-bold text-slate-800 dark:text-white uppercase">
                                        {stats?.region || '-'} / <span className="text-xs text-blue-500">{stats?.mode}</span>
                                    </p>
                                </div>
                            </div>

                            {/* Files Table list */}
                            <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700 overflow-hidden">
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-slate-200 dark:divide-gray-700 text-sm">
                                        <thead className="bg-slate-50 dark:bg-gray-700 text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">
                                            <tr>
                                                <th className="px-6 py-4 text-left">Nama Berkas (Object Key)</th>
                                                <th className="px-6 py-4 text-left">Ukuran</th>
                                                <th className="px-6 py-4 text-left">Tanggal Modifikasi</th>
                                                <th className="px-6 py-4 text-right">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white dark:bg-gray-800 divide-y divide-slate-200 dark:divide-gray-700">
                                            {files.length === 0 ? (
                                                <tr>
                                                    <td colSpan="4" className="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                                        Bucket kosong atau tidak ada berkas di direktori aktif.
                                                    </td>
                                                </tr>
                                            ) : (
                                                files.map((file, idx) => (
                                                    <tr key={idx} className="hover:bg-slate-50 dark:hover:bg-gray-700 transition-colors">
                                                        <td className="px-6 py-4 font-mono text-xs text-slate-800 dark:text-slate-200 max-w-md truncate">
                                                            {file.Key}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-slate-700 dark:text-slate-300">
                                                            {formatBytes(file.Size)}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-slate-500 dark:text-slate-400">
                                                            {file.LastModified}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                            <div className="flex items-center justify-end gap-3">
                                                                <a
                                                                    href={file.PublicUrl}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    className="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                                                >
                                                                    Lihat
                                                                </a>
                                                                <button
                                                                    onClick={() => handleDelete(file.Key)}
                                                                    className="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                                                >
                                                                    Hapus
                                                                </button>
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
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
