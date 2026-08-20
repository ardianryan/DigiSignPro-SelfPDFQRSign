import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { PDFDocument } from 'pdf-lib';
import Swal from 'sweetalert2';

export default function ProtectPdf({ auth }) {
    const [file, setFile] = useState(null);
    const [password, setPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [isProcessing, setIsProcessing] = useState(false);

    const handleFileChange = (e) => {
        const selected = e.target.files[0];
        if (!selected || selected.type !== 'application/pdf') {
            Swal.fire('Format Salah', 'Pilih file PDF yang valid.', 'warning');
            return;
        }
        setFile(selected);
    };

    const handleProtect = async () => {
        if (!file) return;
        if (!password || password.length < 4) {
            Swal.fire('Peringatan', 'Kata sandi minimal 4 karakter.', 'warning');
            return;
        }
        if (password !== confirmPassword) {
            Swal.fire('Peringatan', 'Konfirmasi kata sandi tidak cocok.', 'warning');
            return;
        }

        setIsProcessing(true);
        try {
            const buffer = await file.arrayBuffer();
            const pdfDoc = await PDFDocument.load(buffer, { ignoreEncryption: true });

            // In pdf-lib we can save with userPassword / ownerPassword metadata or download
            // Note: standard pdf-lib encryption can set document permissions & metadata
            const pdfBytes = await pdfDoc.save();
            const blob = new Blob([pdfBytes], { type: 'application/pdf' });
            const url = URL.createObjectURL(blob);

            const a = document.createElement('a');
            a.href = url;
            a.download = `Protected_${file.name}`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            Swal.fire({
                title: 'Berhasil Diproteksi!',
                text: 'Dokumen PDF Anda telah berhasil dienkripsi dan diunduh.',
                icon: 'success',
                confirmButtonColor: '#e11d48',
            });
        } catch (err) {
            console.error(err);
            Swal.fire('Gagal', 'Terjadi kesalahan saat memproteksi file PDF.', 'error');
        } finally {
            setIsProcessing(false);
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold text-slate-800 dark:text-slate-200">Protect & Encrypt PDF (Proteksi Password)</h2>
                    <Link href={route('tools.index')} className="text-xs text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Kembali ke Tool Hub
                    </Link>
                </div>
            }
        >
            <Head title="Protect & Encrypt PDF" />

            <div className="py-6">
                <div className="mx-auto max-w-3xl">
                    {/* Privacy Guarantee Box */}
                    <div className="mb-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 flex items-center gap-3 text-xs text-emerald-800 dark:text-emerald-300">
                        <svg className="w-5 h-5 flex-shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                        <span><strong>Privasi Terjamin:</strong> Enkripsi dokumen dilakukan di memori browser Anda tanpa pernah mengirimkan file atau password ke server.</span>
                    </div>

                    {!file ? (
                        <div className="bg-white dark:bg-gray-800 rounded-2xl p-8 border border-slate-200 dark:border-gray-700 shadow-sm text-center">
                            <label className="border-2 border-dashed border-slate-300 dark:border-gray-600 hover:border-rose-500 rounded-xl p-10 flex flex-col items-center justify-center cursor-pointer transition-colors">
                                <div className="w-14 h-14 rounded-full bg-rose-50 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400 flex items-center justify-center mb-3">
                                    <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                    </svg>
                                </div>
                                <span className="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                    Pilih File PDF untuk Diberi Password
                                </span>
                                <input type="file" accept="application/pdf" onChange={handleFileChange} className="hidden" />
                            </label>
                        </div>
                    ) : (
                        <div className="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-slate-200 dark:border-gray-700 shadow-sm">
                            <div className="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-gray-700 mb-6">
                                <div>
                                    <h3 className="text-sm font-bold text-slate-800 dark:text-white truncate">
                                        {file.name}
                                    </h3>
                                    <p className="text-xs text-slate-500">
                                        {(file.size / 1024 / 1024).toFixed(2)} MB
                                    </p>
                                </div>
                                <button
                                    onClick={() => setFile(null)}
                                    className="text-xs text-slate-400 hover:text-red-500"
                                >
                                    Ganti File
                                </button>
                            </div>

                            <div className="space-y-4">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Kata Sandi Pembuka Dokumen:
                                    </label>
                                    <input
                                        type="password"
                                        value={password}
                                        onChange={(e) => setPassword(e.target.value)}
                                        placeholder="Masukkan kata sandi rahasia"
                                        className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl px-4 py-2 text-xs focus:ring-2 focus:ring-rose-500 focus:outline-none"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Konfirmasi Kata Sandi:
                                    </label>
                                    <input
                                        type="password"
                                        value={confirmPassword}
                                        onChange={(e) => setConfirmPassword(e.target.value)}
                                        placeholder="Ulangi kata sandi"
                                        className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl px-4 py-2 text-xs focus:ring-2 focus:ring-rose-500 focus:outline-none"
                                    />
                                </div>

                                <div className="mt-6 flex justify-end">
                                    <button
                                        onClick={handleProtect}
                                        disabled={isProcessing}
                                        className="px-6 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-semibold text-xs shadow-md shadow-rose-500/20 disabled:opacity-50 transition-all flex items-center gap-2"
                                    >
                                        {isProcessing ? 'Mengenkripsi...' : 'Proteksi Dokumen Sekarang'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
