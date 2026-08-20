import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Swal from 'sweetalert2';

export default function Backup({ auth }) {
    const [activeTab, setActiveTab] = useState('backup');

    // Backup state
    const [backupDb, setBackupDb] = useState(true);
    const [backupMedia, setBackupMedia] = useState(true);

    // Restore state
    const [restoreFile, setRestoreFile] = useState(null);
    const [restoreFileName, setRestoreFileName] = useState('');
    const [restoreDbCheck, setRestoreDbCheck] = useState(true);
    const [restoreMediaCheck, setRestoreMediaCheck] = useState(true);
    const [isDraggingFile, setIsDraggingFile] = useState(false);

    const handleBackupSubmit = (e) => {
        if (!backupDb && !backupMedia) {
            e.preventDefault();
            Swal.fire('Peringatan', 'Pilih minimal satu opsi cadangan database atau media.', 'warning');
        }
    };

    const processFile = (file) => {
        if (file && (file.name.toLowerCase().endsWith('.zip') || file.type === 'application/zip')) {
            setRestoreFile(file);
            setRestoreFileName(file.name);
        } else {
            Swal.fire('Format Salah', 'Pilih file backup dengan format .zip.', 'warning');
        }
    };

    const handleFileChange = (e) => {
        if (e.target.files && e.target.files[0]) {
            processFile(e.target.files[0]);
        }
    };

    const handleDrop = (e) => {
        e.preventDefault();
        setIsDraggingFile(false);
        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            processFile(e.dataTransfer.files[0]);
        }
    };

    const handleRestoreSubmit = (e) => {
        e.preventDefault();
        if (!restoreFile) {
            Swal.fire('Peringatan', 'Pilih file backup ZIP terlebih dahulu.', 'warning');
            return;
        }

        if (!restoreDbCheck && !restoreMediaCheck) {
            Swal.fire('Peringatan', 'Pilih minimal satu komponen data (Database/Media) yang ingin dipulihkan.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Mulai Restore Data?',
            text: 'Tindakan ini akan MENIMPA database dan file media yang aktif sekarang! Pastikan Anda memiliki salinan cadangan yang aman.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, timpa dan restore!',
            cancelButtonText: 'Batal'
        }).then(async (result) => {
            if (result.isConfirmed) {
                setIsRestoring(true);
                Swal.fire({
                    title: 'Memulihkan Data Sistem',
                    text: 'Mengekstrak file, memperbarui tabel database, dan menyalin berkas media...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const restoreData = new FormData();
                restoreData.append('backup_file', restoreFile);
                restoreData.append('restore_db', restoreDbCheck ? '1' : '0');
                restoreData.append('restore_media', restoreMediaCheck ? '1' : '0');

                try {
                    const response = await fetch(route('admin.backup.restore'), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: restoreData
                    });

                    const resJson = await response.json();
                    setIsRestoring(false);
                    Swal.close();

                    if (resJson.status === 'success') {
                        Swal.fire({
                            title: 'Restorasi Berhasil!',
                            text: resJson.message,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            // Reload back to dashboard or current page to refresh state
                            router.get(route('dashboard'));
                        });
                    } else {
                        Swal.fire('Restorasi Gagal', resJson.message || 'Terjadi kesalahan saat memulihkan data.', 'error');
                    }

                } catch (err) {
                    setIsRestoring(false);
                    Swal.close();
                    Swal.fire('Error', 'Terjadi kesalahan fatal: ' + err.message, 'error');
                }
            }
        });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-slate-800 dark:text-slate-200">Backup & Restore</h2>}
        >
            <Head title="Backup & Restore" />

            <div className="py-6">
                <div className="mx-auto max-w-2xl">
                    <div className="mb-6">
                        <p className="text-slate-500 dark:text-slate-400">Ekspor seluruh database dan berkas media menjadi file ZIP cadangan, atau unggah kembali untuk memulihkan data.</p>
                    </div>

                    {/* Tabs */}
                    <div className="flex border-b border-slate-200 dark:border-gray-700 mb-6 gap-6">
                        <button
                            onClick={() => setActiveTab('backup')}
                            className={`pb-3 font-semibold text-sm transition-colors border-b-2 ${
                                activeTab === 'backup'
                                    ? 'border-blue-600 text-blue-600 dark:text-blue-400'
                                    : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'
                            }`}
                        >
                            Cadangkan Data (Backup)
                        </button>
                        <button
                            onClick={() => setActiveTab('restore')}
                            className={`pb-3 font-semibold text-sm transition-colors border-b-2 ${
                                activeTab === 'restore'
                                    ? 'border-blue-600 text-blue-600 dark:text-blue-400'
                                    : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'
                            }`}
                        >
                            Pulihkan Data (Restore)
                        </button>
                    </div>

                    {/* Backup Section */}
                    {activeTab === 'backup' && (
                        <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700 p-6 space-y-6">
                            <div>
                                <h3 className="text-lg font-bold text-slate-800 dark:text-white">Ekspor Data Cadangan</h3>
                                <p className="text-xs text-slate-400 dark:text-gray-400 mt-1">Pilih data yang ingin Anda unduh dalam bentuk arsip ZIP.</p>
                            </div>

                            <form action={route('admin.backup.run')} method="POST" onSubmit={handleBackupSubmit} className="space-y-4">
                                <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]').getAttribute('content')} />
                                
                                <label className="flex items-center space-x-3 p-4 border border-slate-200 dark:border-gray-700 rounded-lg cursor-pointer hover:bg-slate-50 dark:hover:bg-gray-700 transition-colors">
                                    <input
                                        type="checkbox"
                                        name="backup_db"
                                        value="1"
                                        checked={backupDb}
                                        onChange={(e) => setBackupDb(e.target.checked)}
                                        className="w-5 h-5 text-blue-600 rounded focus:ring-blue-500 border-slate-300 dark:border-gray-600 dark:bg-gray-700"
                                    />
                                    <div>
                                        <span className="block font-semibold text-slate-700 dark:text-slate-200 text-sm">Database Aplikasi (JSON)</span>
                                        <span className="text-xs text-slate-400 dark:text-gray-400">Mencadangkan tabel pengguna, data tanda tangan, dan pengaturan.</span>
                                    </div>
                                </label>

                                <label className="flex items-center space-x-3 p-4 border border-slate-200 dark:border-gray-700 rounded-lg cursor-pointer hover:bg-slate-50 dark:hover:bg-gray-700 transition-colors">
                                    <input
                                        type="checkbox"
                                        name="backup_media"
                                        value="1"
                                        checked={backupMedia}
                                        onChange={(e) => setBackupMedia(e.target.checked)}
                                        className="w-5 h-5 text-blue-600 rounded focus:ring-blue-500 border-slate-300 dark:border-gray-600 dark:bg-gray-700"
                                    />
                                    <div>
                                        <span className="block font-semibold text-slate-700 dark:text-slate-200 text-sm">Media File Uploads (PDF & Paraf)</span>
                                        <span className="text-xs text-slate-400 dark:text-gray-400">Mencadangkan semua file dokumen PDF yang telah ditandatangani serta spesimen tanda tangan user.</span>
                                    </div>
                                </label>

                                <button
                                    type="submit"
                                    className="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-6 rounded-lg transition-colors text-sm shadow-md flex items-center justify-center gap-2"
                                >
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                    </svg>
                                    Unduh Cadangan (.zip)
                                </button>
                            </form>
                        </div>
                    )}

                    {/* Restore Section */}
                    {activeTab === 'restore' && (
                        <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700 p-6 space-y-6">
                            <div>
                                <h3 className="text-lg font-bold text-slate-800 dark:text-white">Pulihkan Data (Restore)</h3>
                                <p className="text-xs text-slate-400 dark:text-gray-400 mt-1">Unggah berkas ZIP cadangan Anda untuk mengembalikan keadaan sistem.</p>
                            </div>

                            <div className="bg-yellow-50 dark:bg-yellow-950/20 border-l-4 border-yellow-400 p-4 rounded-r-lg">
                                <div className="flex">
                                    <svg className="h-5 w-5 text-yellow-400 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                    <p className="ml-3 text-xs text-yellow-700 dark:text-yellow-400 font-medium">
                                        Perhatian: Proses restore akan menghapus data yang ada sekarang dan menimpanya dengan isi dari file backup. Lakukan dengan hati-hati.
                                    </p>
                                </div>
                            </div>

                            <form onSubmit={handleRestoreSubmit} className="space-y-4">
                                <label
                                    htmlFor="restore_file_input"
                                    onDragOver={(e) => {
                                        e.preventDefault();
                                        setIsDraggingFile(true);
                                    }}
                                    onDragEnter={(e) => {
                                        e.preventDefault();
                                        setIsDraggingFile(true);
                                    }}
                                    onDragLeave={(e) => {
                                        e.preventDefault();
                                        setIsDraggingFile(false);
                                    }}
                                    onDrop={handleDrop}
                                    className={`border-2 border-dashed rounded-lg p-6 text-center cursor-pointer flex flex-col items-center transition-colors ${
                                        isDraggingFile
                                            ? 'border-blue-500 bg-blue-50/50 dark:bg-blue-900/20'
                                            : 'border-slate-300 dark:border-gray-600 hover:bg-slate-50 dark:hover:bg-gray-700 hover:border-blue-500'
                                    }`}
                                >
                                    <input
                                        type="file"
                                        id="restore_file_input"
                                        accept=".zip"
                                        className="hidden"
                                        onChange={handleFileChange}
                                    />
                                    <svg className="w-12 h-12 text-slate-400 dark:text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                    </svg>
                                    <span className="text-blue-600 dark:text-blue-400 font-semibold hover:underline text-sm">
                                        {isDraggingFile ? 'Lepaskan Berkas Backup di Sini' : (restoreFileName || 'Pilih atau Tarik Berkas Backup (.zip) ke Sini')}
                                    </span>
                                    <span className="text-xs text-slate-400 dark:text-gray-500 mt-1">Hanya file berekstensi .zip hasil ekspor sistem</span>
                                </label>

                                {restoreFile && (
                                    <div className="space-y-3 p-4 bg-slate-50 dark:bg-gray-700/50 rounded-lg border border-slate-200 dark:border-gray-600">
                                        <p className="text-xs font-bold text-slate-700 dark:text-slate-200 mb-2">Komponen Restore:</p>
                                        
                                        <label className="flex items-center space-x-3 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={restoreDbCheck}
                                                onChange={(e) => setRestoreDbCheck(e.target.checked)}
                                                className="w-4 h-4 text-blue-600 rounded border-slate-300"
                                            />
                                            <span className="text-xs text-slate-700 dark:text-slate-300">Pulihkan Database (Menimpa tabel)</span>
                                        </label>

                                        <label className="flex items-center space-x-3 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={restoreMediaCheck}
                                                onChange={(e) => setRestoreMediaCheck(e.target.checked)}
                                                className="w-4 h-4 text-blue-600 rounded border-slate-300"
                                            />
                                            <span className="text-xs text-slate-700 dark:text-slate-300">Pulihkan Media Files (Menimpa folder uploads)</span>
                                        </label>
                                    </div>
                                )}

                                <button
                                    type="submit"
                                    disabled={!restoreFile || isRestoring}
                                    className="w-full bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white font-bold py-2.5 px-4 rounded-lg transition-colors text-sm shadow-md"
                                >
                                    Mulai Restore Data
                                </button>
                            </form>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
