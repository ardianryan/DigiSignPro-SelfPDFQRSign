import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState, useMemo, Fragment } from 'react';
import Swal from 'sweetalert2';

export default function History({ auth, signatures, filters }) {
    const [search, setSearch] = useState(filters.search || '');
    const [expandedBatches, setExpandedBatches] = useState({});

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(route('history.index'), { search }, { preserveState: true });
    };

    const toggleBatch = (batchId) => {
        setExpandedBatches(prev => ({
            ...prev,
            [batchId]: !prev[batchId]
        }));
    };

    const handleDelete = (id) => {
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: 'Data riwayat tanda tangan ini akan dihapus secara permanen beserta file fisiknya!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                router.delete(route('history.destroy', id), {
                    onSuccess: () => Swal.fire('Terhapus!', 'Riwayat berhasil dihapus.', 'success'),
                    onError: (err) => Swal.fire('Gagal!', err.error || 'Terjadi kesalahan.', 'error')
                });
            }
        });
    };

    const handleDeleteBatch = (batchId, totalItems) => {
        Swal.fire({
            title: 'Hapus seluruh batch?',
            text: `Semua ${totalItems} dokumen dalam batch ini beserta file fisiknya akan dihapus permanen.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus batch!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                router.delete(route('history.destroy_batch', batchId), {
                    onSuccess: () => Swal.fire('Terhapus!', 'Batch berhasil dihapus.', 'success'),
                    onError: (err) => Swal.fire('Gagal!', err.error || 'Terjadi kesalahan.', 'error')
                });
            }
        });
    };

    // Grouping logic in frontend
    const groupedItems = useMemo(() => {
        const groups = [];
        const batchMap = {};

        signatures.forEach(item => {
            if (item.batch_id) {
                if (!batchMap[item.batch_id]) {
                    const group = {
                        type: 'batch',
                        id: item.batch_id,
                        first_row: item,
                        items: [item]
                    };
                    batchMap[item.batch_id] = group;
                    groups.push(group);
                } else {
                    batchMap[item.batch_id].items.push(item);
                }
            } else {
                groups.push({
                    type: 'single',
                    data: item
                });
            }
        });

        return groups;
    }, [signatures]);

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-slate-800 dark:text-slate-200">Riwayat Tanda Tangan</h2>}
        >
            <Head title="Riwayat Tanda Tangan" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl">
                    <div className="mb-6">
                        <p className="text-slate-500 dark:text-slate-400">Daftar dokumen yang telah ditandatangani.</p>
                    </div>

                    {/* Search Bar */}
                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700 p-4 mb-6">
                        <form onSubmit={handleSearch} className="flex gap-2">
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari nomor dokumen, perihal, kode verifikasi..."
                                className="flex-1 border border-slate-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg px-4 py-2 text-slate-800 dark:text-slate-100 focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                            />
                            <button
                                type="submit"
                                className="bg-slate-800 hover:bg-slate-900 dark:bg-blue-600 dark:hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors font-medium"
                            >
                                Cari
                            </button>
                        </form>
                    </div>

                    {/* Table List */}
                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700 overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 dark:divide-gray-700">
                                <thead className="bg-slate-50 dark:bg-gray-700">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">Dokumen</th>
                                        {auth.user.role === 'admin' && (
                                            <th className="px-6 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">Penandatangan</th>
                                        )}
                                        <th className="px-6 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">Waktu Sign</th>
                                        <th className="px-6 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">Kode Verifikasi</th>
                                        <th className="px-6 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white dark:bg-gray-800 divide-y divide-slate-200 dark:divide-gray-700">
                                    {groupedItems.length === 0 ? (
                                        <tr>
                                            <td colSpan={auth.user.role === 'admin' ? 5 : 4} className="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                                Tidak ada riwayat tanda tangan ditemukan.
                                            </td>
                                        </tr>
                                    ) : (
                                        groupedItems.map((group, gIdx) => {
                                            if (group.type === 'single') {
                                                const row = group.data;
                                                return (
                                                    <tr key={`single-${row.id}`} className="hover:bg-slate-50 dark:hover:bg-gray-700 transition-colors">
                                                        <td className="px-6 py-4 whitespace-nowrap">
                                                            <div className="flex items-center">
                                                                <svg className="w-8 h-8 text-red-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                                                </svg>
                                                                <div>
                                                                    <div className="text-sm font-medium text-slate-900 dark:text-white truncate max-w-xs">
                                                                        {row.document_subject || row.document_name}
                                                                    </div>
                                                                    {row.document_number && (
                                                                        <div className="text-xs text-slate-500 dark:text-slate-400">No: {row.document_number}</div>
                                                                    )}
                                                                </div>
                                                            </div>
                                                            <div className="ml-11 mt-1">
                                                                {row.file_url ? (
                                                                    <a href={row.file_url} target="_blank" rel="noopener noreferrer" className="text-xs text-blue-500 hover:underline">
                                                                        Lihat/Download File
                                                                    </a>
                                                                ) : (
                                                                    <span className="text-xs text-slate-400">Tanpa PDF (QR Manual)</span>
                                                                )}
                                                            </div>
                                                        </td>
                                                        {auth.user.role === 'admin' && (
                                                            <td className="px-6 py-4 whitespace-nowrap">
                                                                <div className="text-sm text-slate-900 dark:text-white font-medium">{row.user_name}</div>
                                                                <div className="text-xs text-slate-500 dark:text-slate-400">{row.user_position}</div>
                                                            </td>
                                                        )}
                                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                                                            {row.created_at}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap font-mono text-xs text-slate-600 dark:text-slate-300">
                                                            {row.verification_code}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                            {(auth.user.role === 'admin' || auth.user.id === row.user_id) && (
                                                                <button
                                                                    onClick={() => handleDelete(row.id)}
                                                                    className="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 transition-colors"
                                                                >
                                                                    Hapus
                                                                </button>
                                                            )}
                                                        </td>
                                                    </tr>
                                                );
                                            } else {
                                                // Collapsible Batch Group Row
                                                const batchId = group.id;
                                                const firstRow = group.first_row;
                                                const isExpanded = !!expandedBatches[batchId];
                                                const totalItems = group.items.length;

                                                return (
                                                    <Fragment key={`batch-group-${batchId}`}>
                                                        {/* Batch Header Row */}
                                                        <tr className="bg-slate-50 dark:bg-gray-700 hover:bg-slate-100 dark:hover:bg-gray-600 transition-colors">
                                                            <td
                                                                className="px-6 py-4 whitespace-nowrap cursor-pointer"
                                                                colSpan={auth.user.role === 'admin' ? 3 : 2}
                                                                onClick={() => toggleBatch(batchId)}
                                                            >
                                                                <div className="flex items-center">
                                                                    <svg className={`w-5 h-5 text-slate-500 mr-3 transition-transform duration-200 ${isExpanded ? 'rotate-90' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7"></path>
                                                                    </svg>
                                                                    <svg className="w-8 h-8 text-blue-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                                                    </svg>
                                                                    <div>
                                                                        <div className="text-sm font-semibold text-slate-800 dark:text-white">
                                                                            Dokumen Massal (Bulk Sign) - {totalItems} Dokumen
                                                                        </div>
                                                                        <div className="text-xs text-slate-500 dark:text-slate-400">
                                                                            Perihal: {firstRow.document_subject || 'Dokumen Massal'} | ID Batch: <span className="font-mono">{batchId}</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td
                                                                className="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400 cursor-pointer"
                                                                onClick={() => toggleBatch(batchId)}
                                                            >
                                                                {firstRow.created_at}
                                                            </td>
                                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                                                                <button
                                                                    type="button"
                                                                    onClick={() => toggleBatch(batchId)}
                                                                    className="text-blue-600 dark:text-blue-400 font-semibold hover:underline"
                                                                >
                                                                    {isExpanded ? 'Sembunyikan' : 'Tampilkan'}
                                                                </button>
                                                                {(auth.user.role === 'admin' || auth.user.id === firstRow.user_id) && (
                                                                    <button
                                                                        type="button"
                                                                        onClick={(e) => {
                                                                            e.stopPropagation();
                                                                            handleDeleteBatch(batchId, totalItems);
                                                                        }}
                                                                        className="text-red-600 hover:text-red-800 dark:text-red-400 font-semibold"
                                                                    >
                                                                        Hapus Batch
                                                                    </button>
                                                                )}
                                                            </td>
                                                        </tr>

                                                        {/* Batch Children Rows */}
                                                        {isExpanded && group.items.map((row) => (
                                                            <tr key={`batch-child-${row.id}`} className="bg-slate-50/30 dark:bg-gray-800/50 hover:bg-slate-100/50 dark:hover:bg-gray-700/50 transition-colors">
                                                                <td className="px-6 py-3 pl-16 whitespace-nowrap">
                                                                    <div className="flex items-center">
                                                                        <svg className="w-6 h-6 text-red-400 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                                                        </svg>
                                                                        <div>
                                                                            <div className="text-sm text-slate-700 dark:text-slate-200 font-medium">
                                                                                {row.document_name}
                                                                            </div>
                                                                            {row.document_number && (
                                                                                <div className="text-xs text-slate-500 dark:text-slate-400">No: {row.document_number}</div>
                                                                            )}
                                                                        </div>
                                                                    </div>
                                                                    <div className="ml-8 mt-1">
                                                                        <a href={row.file_url} target="_blank" rel="noopener noreferrer" className="text-xs text-blue-500 hover:underline">
                                                                            Download File
                                                                        </a>
                                                                    </div>
                                                                </td>
                                                                {auth.user.role === 'admin' && (
                                                                    <td className="px-6 py-3 whitespace-nowrap">
                                                                        <div className="text-sm text-slate-700 dark:text-slate-200">{row.user_name}</div>
                                                                        <div className="text-xs text-slate-500 dark:text-slate-400">{row.user_position}</div>
                                                                    </td>
                                                                )}
                                                                <td className="px-6 py-3 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                                                                    {row.created_at}
                                                                </td>
                                                                <td className="px-6 py-3 whitespace-nowrap font-mono text-xs text-slate-600 dark:text-slate-300">
                                                                    {row.verification_code}
                                                                </td>
                                                                <td className="px-6 py-3 whitespace-nowrap text-right text-sm font-medium">
                                                                    {(auth.user.role === 'admin' || auth.user.id === row.user_id) && (
                                                                        <button
                                                                            onClick={() => handleDelete(row.id)}
                                                                            className="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 transition-colors"
                                                                        >
                                                                            Hapus
                                                                        </button>
                                                                    )}
                                                                </td>
                                                            </tr>
                                                        ))}
                                                    </Fragment>
                                                );
                                            }
                                        })
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
