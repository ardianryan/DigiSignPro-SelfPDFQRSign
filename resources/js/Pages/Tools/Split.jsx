import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { PDFDocument } from 'pdf-lib';
import Swal from 'sweetalert2';

export default function SplitPdf({ auth }) {
    const [file, setFile] = useState(null);
    const [pageCount, setPageCount] = useState(0);
    const [splitMode, setSplitMode] = useState('range'); // 'range' or 'single'
    const [pageRange, setPageRange] = useState('');
    const [isProcessing, setIsProcessing] = useState(false);

    const handleFileChange = async (e) => {
        const selected = e.target.files[0];
        if (!selected || selected.type !== 'application/pdf') {
            Swal.fire('Format Salah', 'Silakan pilih file PDF yang valid.', 'warning');
            return;
        }

        try {
            const buffer = await selected.arrayBuffer();
            const pdf = await PDFDocument.load(buffer, { ignoreEncryption: true });
            setFile(selected);
            setPageCount(pdf.getPageCount());
            setPageRange(`1-${pdf.getPageCount()}`);
        } catch (err) {
            Swal.fire('Error', 'Gagal membaca informasi halaman PDF.', 'error');
        }
    };

    const parseRange = (str, max) => {
        const pages = new Set();
        const parts = str.split(',');
        for (const part of parts) {
            const trimmed = part.trim();
            if (trimmed.includes('-')) {
                const [start, end] = trimmed.split('-').map(Number);
                if (start && end && start <= end) {
                    for (let i = start; i <= end; i++) {
                        if (i >= 1 && i <= max) pages.add(i - 1);
                    }
                }
            } else {
                const num = Number(trimmed);
                if (num >= 1 && num <= max) pages.add(num - 1);
            }
        }
        return Array.from(pages).sort((a, b) => a - b);
    };

    const handleSplit = async () => {
        if (!file) return;

        setIsProcessing(true);
        try {
            const buffer = await file.arrayBuffer();
            const srcPdf = await PDFDocument.load(buffer, { ignoreEncryption: true });

            if (splitMode === 'range') {
                const selectedIndices = parseRange(pageRange, pageCount);
                if (selectedIndices.length === 0) {
                    Swal.fire('Rentang Tidak Valid', 'Silakan masukkan nomor halaman yang benar (contoh: 1-3, 5).', 'warning');
                    setIsProcessing(false);
                    return;
                }

                const newPdf = await PDFDocument.create();
                const copiedPages = await newPdf.copyPages(srcPdf, selectedIndices);
                copiedPages.forEach(p => newPdf.addPage(p));

                const pdfBytes = await newPdf.save();
                const blob = new Blob([pdfBytes], { type: 'application/pdf' });
                const url = URL.createObjectURL(blob);

                const a = document.createElement('a');
                a.href = url;
                a.download = `Split_${file.name.replace('.pdf', '')}_Pages_${pageRange}.pdf`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                fetch(route('tools.track_usage'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ tool: 'split', files_count: 1 }),
                }).catch(() => {});

                Swal.fire('Berhasil!', 'Halaman yang diekstrak telah terunduh langsung ke perangkat Anda.', 'success');
            } else {
                // Split each page into individual PDFs (download sequentially)
                for (let i = 0; i < pageCount; i++) {
                    const singlePdf = await PDFDocument.create();
                    const [page] = await singlePdf.copyPages(srcPdf, [i]);
                    singlePdf.addPage(page);

                    const bytes = await singlePdf.save();
                    const blob = new Blob([bytes], { type: 'application/pdf' });
                    const url = URL.createObjectURL(blob);

                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `${file.name.replace('.pdf', '')}_Page_${i + 1}.pdf`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                }

                fetch(route('tools.track_usage'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ tool: 'split', files_count: pageCount }),
                }).catch(() => {});

                Swal.fire('Selesai!', `${pageCount} file PDF terpisah telah berhasil diunduh.`, 'success');
            }
        } catch (err) {
            console.error('Split error:', err);
            Swal.fire('Gagal', 'Terjadi kesalahan saat memisahkan halaman PDF.', 'error');
        } finally {
            setIsProcessing(false);
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold text-slate-800 dark:text-slate-200">Split PDF (Pisahkan Dokumen)</h2>
                    <Link href={route('tools.index')} className="text-xs text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Kembali ke Tool Hub
                    </Link>
                </div>
            }
        >
            <Head title="Split PDF" />

            <div className="py-6">
                <div className="mx-auto max-w-3xl">
                    {/* Privacy Guarantee Box */}
                    <div className="mb-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 flex items-center gap-3 text-xs text-emerald-800 dark:text-emerald-300">
                        <svg className="w-5 h-5 flex-shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                        <span><strong>Privasi Terjamin:</strong> File diproses 100% di memori browser Anda tanpa pernah diunggah ke server.</span>
                    </div>

                    {!file ? (
                        <div className="bg-white dark:bg-gray-800 rounded-2xl p-8 border border-slate-200 dark:border-gray-700 shadow-sm text-center">
                            <label className="border-2 border-dashed border-slate-300 dark:border-gray-600 hover:border-purple-500 rounded-xl p-10 flex flex-col items-center justify-center cursor-pointer transition-colors">
                                <div className="w-14 h-14 rounded-full bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 flex items-center justify-center mb-3">
                                    <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                    </svg>
                                </div>
                                <span className="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                    Pilih File PDF untuk Dipisahkan
                                </span>
                                <span className="text-xs text-slate-400 mt-1">Mendukung file single atau multi-halaman</span>
                                <input type="file" accept="application/pdf" onChange={handleFileChange} className="hidden" />
                            </label>
                        </div>
                    ) : (
                        <div className="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-slate-200 dark:border-gray-700 shadow-sm">
                            <div className="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-gray-700">
                                <div>
                                    <h3 className="text-sm font-bold text-slate-800 dark:text-white truncate">
                                        {file.name}
                                    </h3>
                                    <p className="text-xs text-slate-500 mt-0.5">
                                        Total: <strong className="text-purple-600 dark:text-purple-400">{pageCount} Halaman</strong> • {(file.size / 1024 / 1024).toFixed(2)} MB
                                    </p>
                                </div>
                                <button
                                    onClick={() => setFile(null)}
                                    className="text-xs text-slate-400 hover:text-red-500"
                                >
                                    Ganti File
                                </button>
                            </div>

                            <div className="mt-6 space-y-4">
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Mode Pemisahan:
                                </label>
                                <div className="grid grid-cols-2 gap-4">
                                    <button
                                        type="button"
                                        onClick={() => setSplitMode('range')}
                                        className={`p-4 rounded-xl border text-left transition-all ${
                                            splitMode === 'range'
                                                ? 'border-purple-600 bg-purple-50 dark:bg-purple-950/30 text-purple-900 dark:text-purple-200 ring-2 ring-purple-500/20'
                                                : 'border-slate-200 dark:border-gray-700 hover:bg-slate-50 dark:hover:bg-gray-700/50'
                                        }`}
                                    >
                                        <p className="text-xs font-bold">Ekstrak Rentang Halaman</p>
                                        <p className="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                            Pilih halaman tertentu (contoh: 1-3, 5) menjadi 1 PDF baru.
                                        </p>
                                    </button>

                                    <button
                                        type="button"
                                        onClick={() => setSplitMode('single')}
                                        className={`p-4 rounded-xl border text-left transition-all ${
                                            splitMode === 'single'
                                                ? 'border-purple-600 bg-purple-50 dark:bg-purple-950/30 text-purple-900 dark:text-purple-200 ring-2 ring-purple-500/20'
                                                : 'border-slate-200 dark:border-gray-700 hover:bg-slate-50 dark:hover:bg-gray-700/50'
                                        }`}
                                    >
                                        <p className="text-xs font-bold">Pisah Semua Halaman</p>
                                        <p className="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                            Ubah setiap 1 halaman menjadi 1 file PDF individual.
                                        </p>
                                    </button>
                                </div>

                                {splitMode === 'range' && (
                                    <div className="mt-4">
                                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            Rentang Halaman:
                                        </label>
                                        <input
                                            type="text"
                                            value={pageRange}
                                            onChange={(e) => setPageRange(e.target.value)}
                                            placeholder="Contoh: 1-3, 5, 8-10"
                                            className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl px-4 py-2 text-xs focus:ring-2 focus:ring-purple-500 focus:outline-none"
                                        />
                                        <p className="text-[11px] text-slate-400 mt-1">
                                            Gunakan tanda koma (,) untuk memisahkan dan tanda minus (-) untuk rentang. Max: {pageCount}.
                                        </p>
                                    </div>
                                )}

                                <div className="mt-6 flex justify-end">
                                    <button
                                        onClick={handleSplit}
                                        disabled={isProcessing}
                                        className="px-6 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-semibold text-xs shadow-md shadow-purple-500/20 disabled:opacity-50 transition-all flex items-center gap-2"
                                    >
                                        {isProcessing ? 'Memproses Pemisahan...' : 'Pisahkan PDF Sekarang'}
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
