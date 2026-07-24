import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, Link } from '@inertiajs/react';
import { useState } from 'react';
import Swal from 'sweetalert2';

export default function QrList({ auth, signatures, filters }) {
    const [search, setSearch] = useState(filters.search || '');
    const [showUploadModal, setShowUploadModal] = useState(false);
    const [uploadId, setUploadId] = useState(null);
    const [uploadPassword, setUploadPassword] = useState('');
    const [uploadFile, setUploadFile] = useState(null);
    const [isUploading, setIsUploading] = useState(false);

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(route('sign.qr.index'), { search }, { preserveState: true });
    };

    const openUploadModal = (id) => {
        setUploadId(id);
        setUploadPassword('');
        setUploadFile(null);
        setShowUploadModal(true);
    };

    const handleUploadSubmit = async (e) => {
        e.preventDefault();
        if (!uploadFile) {
            Swal.fire('Peringatan', 'Pilih file PDF terlebih dahulu.', 'warning');
            return;
        }
        if (!uploadPassword) {
            Swal.fire('Peringatan', 'Masukkan password parafrase untuk proteksi PDF.', 'warning');
            return;
        }

        setIsUploading(true);
        Swal.fire({
            title: 'Mengunggah & Mengamankan PDF',
            text: 'Silakan tunggu sebentar...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const uploadData = new FormData();
        uploadData.append('step', '3');
        uploadData.append('id', uploadId);
        uploadData.append('pdf_file', uploadFile);
        uploadData.append('pdf_password', uploadPassword);

        try {
            const response = await fetch(route('sign.qr.store'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: uploadData
            });

            setIsUploading(false);
            setShowUploadModal(false);
            Swal.close();

            // Check if redirect was triggered or returns json
            // Store redirecting back in laravel returns standard session redirect.
            // If the request is ajax, we can catch redirect or refresh the page.
            router.reload({
                onSuccess: () => Swal.fire('Berhasil!', 'Dokumen manual berhasil diunggah.', 'success')
            });
        } catch (err) {
            setIsUploading(false);
            Swal.fire('Error', 'Gagal memproses file: ' + err.message, 'error');
        }
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-slate-800 dark:text-slate-200 font-medium">Layanan TTE QR (Manual)</h2>}
        >
            <Head title="Riwayat TTE QR" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl">
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                        <div>
                            <p className="text-slate-500 dark:text-slate-400">Generate QR Code untuk tanda tangan manual (dokumen fisik / di luar sistem).</p>
                        </div>
                        <Link
                            href={route('sign.qr.create')}
                            className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-5 rounded-lg flex items-center shadow-md transition-colors text-sm"
                        >
                            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Buat TTE QR Baru
                        </Link>
                    </div>

                    {/* Search */}
                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700 p-4 mb-6">
                        <form onSubmit={handleSearch} className="flex gap-2">
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari nomor dokumen, perihal, kode verifikasi..."
                                className="flex-1 border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-4 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                            />
                            <button
                                type="submit"
                                className="bg-slate-800 hover:bg-slate-900 dark:bg-blue-600 dark:hover:bg-blue-700 text-white px-6 py-2 rounded-lg text-sm font-medium transition-colors"
                            >
                                Cari
                            </button>
                        </form>
                    </div>

                    {/* Data List */}
                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700 overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 dark:divide-gray-700">
                                <thead className="bg-slate-50 dark:bg-gray-700 text-xs font-semibold text-slate-500 dark:text-slate-300 uppercase tracking-wider">
                                    <tr>
                                        <th className="px-6 py-4 text-left">Tanggal & ID</th>
                                        <th className="px-6 py-4 text-left">Dokumen</th>
                                        <th className="px-6 py-4 text-left">Status Berkas</th>
                                        <th className="px-6 py-4 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white dark:bg-gray-800 divide-y divide-slate-200 dark:divide-gray-700">
                                    {signatures.data.length === 0 ? (
                                        <tr>
                                            <td colSpan="4" className="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                                Belum ada riwayat TTE QR manual.
                                            </td>
                                        </tr>
                                    ) : (
                                        signatures.data.map((row) => (
                                            <tr key={row.id} className="hover:bg-slate-50 dark:hover:bg-gray-700 transition-colors">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm font-semibold text-slate-900 dark:text-white">{row.signed_at}</div>
                                                    <div className="text-xs font-mono text-slate-500 dark:text-slate-400 mt-1">{row.verify_code}</div>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <div className="text-sm font-semibold text-slate-900 dark:text-white truncate max-w-xs">
                                                        {row.document_number}
                                                    </div>
                                                    <div className="text-sm text-slate-500 dark:text-slate-400 mt-0.5 truncate max-w-xs">{row.document_subject}</div>
                                                    {row.document_attachment && (
                                                        <div className="text-xs text-slate-400 mt-1 flex items-center">
                                                            <svg className="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                                            </svg>
                                                            {row.document_attachment}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    {row.file_path ? (
                                                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                                            <svg className="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path>
                                                            </svg>
                                                            Terupload
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">
                                                            Belum Upload
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <Link
                                                            href={route('sign.qr.create', { step: 2, id: row.id })}
                                                            className="p-2 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                                            title="Lihat/Download QR"
                                                        >
                                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v1m6 11h2m-6 0h-2v4h-4v-2h-2v4h6v-2h2v-2h2v-2h-2v2zM12 2h2v2h-2V2zm4 4v2h2V6h-2zm-4 4v2h2v-2h-2v2zM6 6h4v4H6V6zm14 0h-4v4h4V6zM6 16h4v4H6v-4z"></path>
                                                            </svg>
                                                        </Link>

                                                        {row.file_path ? (
                                                            <a
                                                                href={row.file_url}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="p-2 text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                                                title="Download PDF"
                                                            >
                                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                                                </svg>
                                                            </a>
                                                        ) : (
                                                            <button
                                                                onClick={() => openUploadModal(row.id)}
                                                                className="p-2 text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                                                title="Upload Dokumen PDF"
                                                            >
                                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                                                </svg>
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

                        {/* Pagination Links */}
                        {signatures.links && signatures.links.length > 3 && (
                            <div className="flex items-center justify-between px-6 py-4 bg-slate-50 dark:bg-gray-700 border-t border-slate-200 dark:border-gray-600">
                                <div className="text-xs text-slate-500 dark:text-slate-400">
                                    Menampilkan {signatures.data.length} dari {signatures.total} item
                                </div>
                                <div className="flex gap-1">
                                    {signatures.links.map((link, idx) => (
                                        <Link
                                            key={idx}
                                            href={link.url || '#'}
                                            disabled={!link.url}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                            className={`px-3 py-1.5 rounded-lg border text-xs font-semibold transition-colors ${
                                                link.active
                                                    ? 'bg-blue-600 border-blue-600 text-white'
                                                    : 'bg-white dark:bg-gray-800 border-slate-200 dark:border-gray-600 text-slate-700 dark:text-slate-300 hover:bg-slate-50 disabled:opacity-50'
                                            }`}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Upload PDF Modal */}
            {showUploadModal && (
                <div className="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black bg-opacity-50">
                    <div className="bg-white dark:bg-gray-800 w-full max-w-md rounded-xl shadow-lg border border-slate-200 dark:border-gray-700 p-6 relative">
                        <h3 className="text-lg font-bold text-slate-800 dark:text-white mb-4">Unggah Dokumen TTE QR</h3>
                        
                        <form onSubmit={handleUploadSubmit} className="space-y-4">
                            <div>
                                <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">
                                    File PDF Dokumen
                                </label>
                                <input
                                    type="file"
                                    accept="application/pdf"
                                    onChange={(e) => setUploadFile(e.target.files[0])}
                                    className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:outline-none"
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">
                                    Password Parafrase
                                </label>
                                <input
                                    type="password"
                                    value={uploadPassword}
                                    onChange={(e) => setUploadPassword(e.target.value)}
                                    placeholder="Masukkan password parafrase..."
                                    className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                />
                            </div>

                            <div className="flex justify-end gap-2 mt-6">
                                <button
                                    type="button"
                                    onClick={() => setShowUploadModal(false)}
                                    className="py-2 px-4 border border-slate-200 dark:border-gray-600 text-slate-700 dark:text-slate-200 rounded-lg text-sm"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={isUploading}
                                    className="py-2 px-6 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg text-sm disabled:opacity-50"
                                >
                                    Upload
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
