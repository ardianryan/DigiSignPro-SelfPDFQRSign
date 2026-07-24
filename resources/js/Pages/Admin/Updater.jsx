import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import Swal from 'sweetalert2';

export default function Updater({ current_version, migration }) {
    const [step, setStep] = useState('upload'); // upload | preview | success
    const [file, setFile] = useState(null);
    const [fileName, setFileName] = useState('');
    const [isProcessing, setIsProcessing] = useState(false);
    const [isMigrating, setIsMigrating] = useState(false);
    const [tempId, setTempId] = useState('');
    const [manifest, setManifest] = useState({
        version: '',
        release_date: '',
        description: '',
        files: [],
    });
    const [successMessage, setSuccessMessage] = useState('');
    const [version, setVersion] = useState(current_version || '2.0.0');
    const [migrationState, setMigrationState] = useState(migration || { has_pending: false, pending: [] });

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const fileSelected = (e) => {
        const f = e.target.files?.[0] || null;
        setFile(f);
        setFileName(f ? f.name : '');
    };

    const runDbMigration = async () => {
        setIsMigrating(true);
        Swal.fire({
            title: 'Menjalankan Migrasi',
            text: 'Mohon tunggu...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
        });

        try {
            const res = await fetch(route('admin.database.migrate'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    Accept: 'application/json',
                },
            });
            const data = await res.json();
            Swal.close();
            setIsMigrating(false);

            if (data.status === 'success') {
                setMigrationState({ has_pending: false, pending: [] });
                const msg = (data.message || 'Migrasi selesai.').replace(/</g, '&lt;');
                Swal.fire({
                    title: 'Migrasi Berhasil',
                    html: `<pre class="text-left text-xs whitespace-pre-wrap max-h-80 overflow-auto bg-slate-50 p-3 rounded border border-slate-200">${msg}</pre>`,
                    icon: 'success',
                    width: 560,
                });
            } else {
                Swal.fire('Gagal', data.message || 'Migrasi gagal.', 'error');
            }
        } catch (err) {
            setIsMigrating(false);
            Swal.close();
            Swal.fire('Error', err.message, 'error');
        }
    };

    const analyzeUpdate = async () => {
        if (!file) return;
        setIsProcessing(true);

        const formData = new FormData();
        formData.append('update_file', file);

        try {
            const res = await fetch(route('admin.updater.analyze'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    Accept: 'application/json',
                },
                body: formData,
            });
            const data = await res.json();
            setIsProcessing(false);

            if (data.status === 'success') {
                setTempId(data.temp_id);
                setManifest({
                    version: data.manifest?.version || '',
                    release_date: data.manifest?.release_date || '',
                    description: data.manifest?.description || '',
                    files: data.manifest?.files || [],
                });
                setStep('preview');
            } else {
                Swal.fire('Gagal', data.message || 'Analisis paket gagal.', 'error');
            }
        } catch (err) {
            setIsProcessing(false);
            Swal.fire('Error', err.message, 'error');
        }
    };

    const executeUpdate = async () => {
        const confirm = await Swal.fire({
            title: 'Pasang Update?',
            text: 'Pastikan Anda sudah backup database & file. Proses ini akan menimpa file aplikasi.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, pasang sekarang',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#dc2626',
        });
        if (!confirm.isConfirmed) return;

        setIsProcessing(true);
        setStep('processing');

        try {
            const formData = new FormData();
            formData.append('temp_id', tempId);

            const res = await fetch(route('admin.updater.execute'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    Accept: 'application/json',
                },
                body: formData,
            });
            const data = await res.json();
            setIsProcessing(false);

            if (data.status === 'success') {
                setSuccessMessage(data.message || 'Update berhasil.');
                if (data.version) setVersion(data.version);
                setStep('success');
            } else {
                setStep('preview');
                Swal.fire('Gagal', data.message || 'Update gagal.', 'error');
            }
        } catch (err) {
            setIsProcessing(false);
            setStep('preview');
            Swal.fire('Error', err.message, 'error');
        }
    };

    const resetUpload = () => {
        setStep('upload');
        setFile(null);
        setFileName('');
        setTempId('');
        setManifest({ version: '', release_date: '', description: '', files: [] });
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="text-2xl font-bold text-slate-800 dark:text-white">Update System</h2>
                    <p className="text-slate-500 dark:text-slate-400">
                        Upgrade aplikasi ke versi terbaru melalui paket update (ZIP).
                    </p>
                </div>
            }
        >
            <Head title="Update App" />

            <div className="max-w-4xl mx-auto space-y-6">
                {migrationState?.has_pending ? (
                    <div className="bg-red-50 border-l-4 border-red-500 p-6 rounded-r-xl shadow-sm">
                        <div className="flex items-start gap-4">
                            <div className="p-2 bg-red-100 rounded-lg text-red-600">
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                            </div>
                            <div className="flex-1">
                                <h3 className="text-lg font-bold text-red-800">Database Belum Sinkron!</h3>
                                <p className="text-sm text-red-600 mt-1">
                                    Ada migrasi pending. Tombol di bawah juga menyesuaikan DB DigiSign lama
                                    (menambah kolom Laravel yang kurang, aman dijalankan berulang).
                                </p>
                                {migrationState.pending?.length > 0 && (
                                    <details className="mt-3 text-xs text-red-700">
                                        <summary className="font-semibold cursor-pointer hover:underline">
                                            Lihat detail ({migrationState.pending.length} item)
                                        </summary>
                                        <ul className="list-disc list-inside mt-2 space-y-1 bg-red-100/50 p-2.5 rounded-lg border border-red-200">
                                            {migrationState.pending.map((item, i) => (
                                                <li key={i}>{item}</li>
                                            ))}
                                        </ul>
                                    </details>
                                )}
                                <button
                                    onClick={runDbMigration}
                                    disabled={isMigrating}
                                    className="mt-4 bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 px-5 rounded-lg text-sm shadow-md disabled:opacity-50"
                                >
                                    {isMigrating ? 'Menjalankan Migrasi...' : 'Jalankan Migrasi Database Sekarang'}
                                </button>
                            </div>
                        </div>
                    </div>
                ) : (
                    <div className="bg-green-50 border-l-4 border-green-500 p-4 rounded-r-xl shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div className="flex items-start gap-3">
                            <svg className="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <span className="text-sm font-semibold text-green-800 block">
                                    Struktur Database Sinkron & Up-to-Date!
                                </span>
                                <p className="text-xs text-green-700 mt-1">
                                    Cocok untuk DB baru maupun DB DigiSign lama. Jalankan ulang migrasi kapan saja
                                    setelah import data lama — kolom yang kurang akan ditambahkan otomatis.
                                </p>
                            </div>
                        </div>
                        <button
                            onClick={runDbMigration}
                            disabled={isMigrating}
                            className="text-xs text-green-800 hover:bg-green-100 font-semibold disabled:opacity-50 px-3 py-2 rounded-lg border border-green-200 whitespace-nowrap"
                        >
                            Jalankan Ulang Migrasi
                        </button>
                    </div>
                )}

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700 h-fit">
                        <h3 className="text-lg font-semibold text-slate-700 dark:text-slate-200 mb-2">Versi Saat Ini</h3>
                        <div className="flex items-center">
                            <div className="text-4xl font-bold text-blue-600">{version}</div>
                            <div className="ml-4 px-3 py-1 bg-green-100 text-green-700 text-xs rounded-full">Production</div>
                        </div>
                        <div className="mt-6 p-4 bg-amber-50 border-l-4 border-amber-400 text-amber-800 rounded-r-lg">
                            <p className="text-sm font-bold">Peringatan Penting!</p>
                            <p className="text-xs mt-1 leading-relaxed">
                                Selalu backup database dan file aplikasi sebelum update. File sensitif (.env, vendor,
                                node_modules) tidak akan ditimpa.
                            </p>
                        </div>
                        <div className="mt-4 p-3 bg-slate-50 dark:bg-gray-900 rounded-lg text-xs text-slate-500 dark:text-slate-400 space-y-1">
                            <p className="font-semibold text-slate-700 dark:text-slate-300">Format paket ZIP:</p>
                            <ul className="list-disc list-inside">
                                <li>
                                    <code>manifest.json</code> (wajib)
                                </li>
                                <li>
                                    <code>files/</code> (opsional) struktur file yang ditimpa
                                </li>
                                <li>
                                    <code>update.sql</code> (opsional)
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div className="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700">
                        <h3 className="text-lg font-semibold text-slate-700 dark:text-slate-200 mb-4">Proses Update</h3>

                        {step === 'upload' && (
                            <div>
                                <div className="border-2 border-dashed border-slate-300 dark:border-gray-600 rounded-lg p-8 text-center hover:bg-slate-50 dark:hover:bg-gray-700/40 transition-colors">
                                    <input type="file" id="update_file" accept=".zip" className="hidden" onChange={fileSelected} />
                                    <label htmlFor="update_file" className="cursor-pointer flex flex-col items-center">
                                        <svg className="w-16 h-16 text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                        </svg>
                                        <span className="text-blue-600 font-bold hover:underline">
                                            Pilih File Paket Update (.zip)
                                        </span>
                                        <span className="text-xs text-slate-400 mt-2">
                                            {fileName || 'Upload file zip yang berisi manifest.json'}
                                        </span>
                                    </label>
                                </div>
                                <button
                                    onClick={analyzeUpdate}
                                    disabled={!file || isProcessing}
                                    className="w-full mt-6 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-xl disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-blue-200/50"
                                >
                                    {isProcessing ? 'Menganalisis...' : 'Mulai Analisis Paket'}
                                </button>
                            </div>
                        )}

                        {step === 'preview' && (
                            <div className="space-y-4">
                                <div className="bg-blue-50 p-4 rounded-lg flex items-center justify-between border border-blue-100">
                                    <div>
                                        <p className="text-xs text-blue-600 font-bold uppercase">Versi Baru</p>
                                        <p className="text-xl font-bold text-blue-800">{manifest.version}</p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-xs text-blue-600 font-bold uppercase">Tanggal Rilis</p>
                                        <p className="text-sm font-semibold text-blue-800">
                                            {manifest.release_date || '—'}
                                        </p>
                                    </div>
                                </div>

                                <div className="p-4 bg-slate-50 dark:bg-gray-900 rounded-lg border border-slate-200 dark:border-gray-700">
                                    <p className="text-sm font-bold text-slate-700 dark:text-slate-200 mb-2">
                                        Deskripsi Update:
                                    </p>
                                    <p className="text-sm text-slate-600 dark:text-slate-400">
                                        {manifest.description || 'Tidak ada deskripsi.'}
                                    </p>
                                </div>

                                <div>
                                    <p className="text-sm font-bold text-slate-700 dark:text-slate-200 mb-2">
                                        File yang akan diperbarui ({manifest.files?.length || 0}):
                                    </p>
                                    <div className="max-h-48 overflow-y-auto bg-slate-50 dark:bg-gray-900 border border-slate-200 dark:border-gray-700 rounded-lg p-2">
                                        {(manifest.files || []).length === 0 ? (
                                            <p className="text-xs text-slate-400 p-2">Tidak ada file (hanya SQL/versi).</p>
                                        ) : (
                                            manifest.files.map((f, i) => (
                                                <div
                                                    key={i}
                                                    className="flex items-center gap-2 p-1.5 text-xs text-slate-600 dark:text-slate-300 border-b border-slate-100 dark:border-gray-800 last:border-0"
                                                >
                                                    <span className="font-mono truncate">{f}</span>
                                                </div>
                                            ))
                                        )}
                                    </div>
                                </div>

                                <div className="pt-4 flex gap-3">
                                    <button
                                        onClick={resetUpload}
                                        className="flex-1 border border-slate-300 dark:border-gray-600 text-slate-700 dark:text-slate-200 font-bold py-3 px-4 rounded-xl hover:bg-slate-50 dark:hover:bg-gray-700 transition-colors"
                                    >
                                        Batal
                                    </button>
                                    <button
                                        onClick={executeUpdate}
                                        disabled={isProcessing}
                                        className="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-4 rounded-xl shadow-lg shadow-red-200/40 transition-all disabled:opacity-50"
                                    >
                                        {isProcessing ? 'Memproses...' : 'Pasang Sekarang'}
                                    </button>
                                </div>
                            </div>
                        )}

                        {step === 'processing' && (
                            <div className="text-center py-12">
                                <div className="relative w-20 h-20 mx-auto mb-6">
                                    <div className="absolute inset-0 border-4 border-blue-100 rounded-full"></div>
                                    <div className="absolute inset-0 border-4 border-blue-600 rounded-full border-t-transparent animate-spin"></div>
                                </div>
                                <p className="text-lg font-bold text-slate-800 dark:text-white">Memasang update...</p>
                                <p className="text-sm text-slate-500 mt-2">
                                    Mohon jangan tinggalkan atau muat ulang halaman ini.
                                </p>
                            </div>
                        )}

                        {step === 'success' && (
                            <div className="text-center py-8">
                                <div className="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                    <svg className="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                                <h4 className="text-2xl font-bold text-slate-800 dark:text-white mb-2">Update Berhasil!</h4>
                                <p className="text-slate-600 dark:text-slate-400 mb-8">{successMessage}</p>
                                <button
                                    onClick={() => window.location.reload()}
                                    className="w-full bg-slate-900 hover:bg-black text-white font-bold py-3 px-4 rounded-xl transition-all shadow-xl"
                                >
                                    Selesai & Muat Ulang
                                </button>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
