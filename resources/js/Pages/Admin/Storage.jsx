import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, Link } from '@inertiajs/react';
import Swal from 'sweetalert2';

export default function Storage({ auth, stats, files = [], storageMode = 'local' }) {
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
                                Pantau dan kelola berkas tanda tangan (mode:{' '}
                                <span className="font-semibold uppercase text-blue-600">{storageMode || 'local'}</span>
                                {stats?.bucket && stats.bucket !== '-' && stats.bucket !== 'local' ? (
                                    <>
                                        {' '}· bucket:{' '}
                                        <span className="font-mono text-blue-600 dark:text-blue-400 font-bold">{stats.bucket}</span>
                                    </>
                                ) : null}
                                )
                            </p>
                            <p className="text-xs text-slate-400 dark:text-gray-500 mt-1">
                                Direktori aktif: <span className="font-mono">{stats?.directory || '/'}</span>
                            </p>
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

                    {stats?.error ? (
                        <div className="bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900 rounded-xl p-6 text-center text-red-800 dark:text-red-400 mb-6">
                            <svg className="w-12 h-12 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <h4 className="font-bold mb-1">Gagal Memuat Storage</h4>
                            <p className="text-sm">{stats.error}</p>
                        </div>
                    ) : null}

                    <div className="space-y-6">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div className="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700">
                                <p className="text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">Total Berkas</p>
                                <p className="text-2xl font-bold text-slate-800 dark:text-white">{stats?.count || 0}</p>
                            </div>
                            <div className="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700">
                                <p className="text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">Ukuran Kumulatif</p>
                                <p className="text-2xl font-bold text-slate-800 dark:text-white">{formatBytes(stats?.size || 0)}</p>
                            </div>
                            <div className="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700">
                                <p className="text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">Region / Mode</p>
                                <p className="text-2xl font-bold text-slate-800 dark:text-white uppercase">
                                    {stats?.region || '-'} / <span className="text-xs text-blue-500">{stats?.mode || storageMode}</span>
                                </p>
                            </div>
                        </div>

                        {storageMode === 'local' && (
                            <div className="bg-blue-50 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900 rounded-xl px-4 py-3 text-sm text-blue-800 dark:text-blue-300">
                                Mode <strong>local</strong>: menampilkan berkas di <code className="font-mono">storage/app/public/uploads/signatures</code>.
                                Aktifkan S3 di Pengaturan untuk browser bucket cloud.
                            </div>
                        )}

                        <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700 overflow-hidden">
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-200 dark:divide-gray-700 text-sm">
                                    <thead className="bg-slate-50 dark:bg-gray-700 text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">
                                        <tr>
                                            <th className="px-6 py-4 text-left">Nama Berkas</th>
                                            <th className="px-6 py-4 text-left">Sumber</th>
                                            <th className="px-6 py-4 text-left">Ukuran</th>
                                            <th className="px-6 py-4 text-left">Tanggal</th>
                                            <th className="px-6 py-4 text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white dark:bg-gray-800 divide-y divide-slate-200 dark:divide-gray-700">
                                        {files.length === 0 ? (
                                            <tr>
                                                <td colSpan="5" className="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                                    Belum ada berkas di lokasi penyimpanan aktif.
                                                </td>
                                            </tr>
                                        ) : (
                                            files.map((file, idx) => (
                                                <tr key={idx} className="hover:bg-slate-50 dark:hover:bg-gray-700 transition-colors">
                                                    <td className="px-6 py-4 font-mono text-xs text-slate-800 dark:text-slate-200 max-w-md truncate">
                                                        {file.Key}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <span className="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded bg-slate-100 dark:bg-gray-700 text-slate-600 dark:text-slate-300">
                                                            {file.source || (storageMode === 'local' ? 'local' : 's3')}
                                                        </span>
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
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
