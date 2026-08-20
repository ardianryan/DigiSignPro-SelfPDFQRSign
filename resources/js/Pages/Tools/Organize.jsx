import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { PDFDocument, degrees } from 'pdf-lib';
import Swal from 'sweetalert2';

export default function OrganizePdf({ auth }) {
    const [file, setFile] = useState(null);
    const [pages, setPages] = useState([]); // [{ originalIndex: 0, rotation: 0, id: '1' }]
    const [isProcessing, setIsProcessing] = useState(false);

    const handleFileChange = async (e) => {
        const selected = e.target.files[0];
        if (!selected || selected.type !== 'application/pdf') {
            Swal.fire('Format Salah', 'Pilih file PDF yang valid.', 'warning');
            return;
        }

        try {
            const buffer = await selected.arrayBuffer();
            const pdf = await PDFDocument.load(buffer, { ignoreEncryption: true });
            const count = pdf.getPageCount();

            const initialPages = [];
            for (let i = 0; i < count; i++) {
                initialPages.push({
                    originalIndex: i,
                    rotation: 0,
                    id: `page_${i}_${Date.now()}`
                });
            }

            setFile(selected);
            setPages(initialPages);
        } catch (err) {
            Swal.fire('Error', 'Gagal memuat struktur halaman PDF.', 'error');
        }
    };

    const rotatePage = (index) => {
        setPages(prev => {
            const copy = [...prev];
            copy[index].rotation = (copy[index].rotation + 90) % 360;
            return copy;
        });
    };

    const rotateAll = (deg) => {
        setPages(prev => prev.map(p => ({ ...p, rotation: (p.rotation + deg) % 360 })));
    };

    const deletePage = (index) => {
        if (pages.length <= 1) {
            Swal.fire('Peringatan', 'Dokumen harus memiliki minimal 1 halaman tersisa.', 'warning');
            return;
        }
        setPages(prev => prev.filter((_, i) => i !== index));
    };

    const movePage = (fromIndex, toIndex) => {
        if (toIndex < 0 || toIndex >= pages.length) return;
        setPages(prev => {
            const copy = [...prev];
            const [moved] = copy.splice(fromIndex, 1);
            copy.splice(toIndex, 0, moved);
            return copy;
        });
    };

    const handleSave = async () => {
        if (!file || pages.length === 0) return;

        setIsProcessing(true);
        try {
            const buffer = await file.arrayBuffer();
            const srcPdf = await PDFDocument.load(buffer, { ignoreEncryption: true });
            const newPdf = await PDFDocument.create();

            for (const item of pages) {
                const [copiedPage] = await newPdf.copyPages(srcPdf, [item.originalIndex]);
                if (item.rotation !== 0) {
                    const currentRotation = copiedPage.getRotation().angle || 0;
                    copiedPage.setRotation(degrees((currentRotation + item.rotation) % 360));
                }
                newPdf.addPage(copiedPage);
            }

            const pdfBytes = await newPdf.save();
            const blob = new Blob([pdfBytes], { type: 'application/pdf' });
            const url = URL.createObjectURL(blob);

            const a = document.createElement('a');
            a.href = url;
            a.download = `Organized_${file.name}`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            Swal.fire('Berhasil!', 'Susunan halaman PDF baru telah diunduh.', 'success');
        } catch (err) {
            console.error(err);
            Swal.fire('Gagal', 'Terjadi kesalahan saat menyimpan susunan PDF.', 'error');
        } finally {
            setIsProcessing(false);
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold text-slate-800 dark:text-slate-200">Organize & Rotate (Susun Halaman)</h2>
                    <Link href={route('tools.index')} className="text-xs text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Kembali ke Tool Hub
                    </Link>
                </div>
            }
        >
            <Head title="Organize & Rotate PDF" />

            <div className="py-6">
                <div className="mx-auto max-w-6xl">
                    {/* Privacy Guarantee Box */}
                    <div className="mb-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 flex items-center gap-3 text-xs text-emerald-800 dark:text-emerald-300">
                        <svg className="w-5 h-5 flex-shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                        <span><strong>Privasi Terjamin:</strong> Penyusunan dan rotasi halaman dilakukan 100% di memori browser Anda tanpa upload ke server.</span>
                    </div>

                    {!file ? (
                        <div className="bg-white dark:bg-gray-800 rounded-2xl p-8 border border-slate-200 dark:border-gray-700 shadow-sm text-center">
                            <label className="border-2 border-dashed border-slate-300 dark:border-gray-600 hover:border-emerald-500 rounded-xl p-10 flex flex-col items-center justify-center cursor-pointer transition-colors">
                                <div className="w-14 h-14 rounded-full bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-3">
                                    <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                </div>
                                <span className="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                    Pilih File PDF untuk Diatur Ulang
                                </span>
                                <span className="text-xs text-slate-400 mt-1">Dapat memutar sudut, menghapus, atau memindahkan urutan halaman</span>
                                <input type="file" accept="application/pdf" onChange={handleFileChange} className="hidden" />
                            </label>
                        </div>
                    ) : (
                        <div>
                            {/* Toolbar Controls */}
                            <div className="bg-white dark:bg-gray-800 rounded-2xl p-4 border border-slate-200 dark:border-gray-700 shadow-sm mb-6 flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <h3 className="text-sm font-bold text-slate-800 dark:text-white truncate">
                                        {file.name}
                                    </h3>
                                    <p className="text-xs text-slate-500">
                                        {pages.length} Halaman Aktif
                                    </p>
                                </div>

                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={() => rotateAll(90)}
                                        className="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-gray-600 hover:bg-slate-50 dark:hover:bg-gray-700 text-xs font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-1.5"
                                    >
                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                        Putar Semua +90°
                                    </button>

                                    <button
                                        type="button"
                                        onClick={handleSave}
                                        disabled={isProcessing}
                                        className="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs shadow-md shadow-emerald-500/20 disabled:opacity-50 transition-all flex items-center gap-2"
                                    >
                                        {isProcessing ? 'Menyimpan...' : 'Simpan & Download PDF'}
                                    </button>
                                </div>
                            </div>

                            {/* Page Grid */}
                            <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                                {pages.map((item, idx) => (
                                    <div
                                        key={item.id}
                                        className="bg-white dark:bg-gray-800 rounded-2xl p-4 border border-slate-200 dark:border-gray-700 shadow-sm flex flex-col items-center relative group"
                                    >
                                        {/* Page Number Badge */}
                                        <span className="absolute top-2 left-2 w-6 h-6 rounded-full bg-slate-900/80 text-white text-[10px] font-bold flex items-center justify-center z-10 backdrop-blur-xs">
                                            {idx + 1}
                                        </span>

                                        {/* Mock Page Thumbnail Preview */}
                                        <div
                                            className="w-28 h-36 bg-slate-100 dark:bg-gray-700/70 border border-slate-300 dark:border-gray-600 rounded-lg flex flex-col items-center justify-center p-2 shadow-inner transition-transform duration-200 mb-3"
                                            style={{ transform: `rotate(${item.rotation}deg)` }}
                                        >
                                            <svg className="w-8 h-8 text-slate-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            <span className="text-[10px] text-slate-500 font-semibold text-center">
                                                Hal. Asli #{item.originalIndex + 1}
                                            </span>
                                            {item.rotation !== 0 && (
                                                <span className="text-[8px] text-emerald-600 font-bold mt-1">
                                                    {item.rotation}°
                                                </span>
                                            )}
                                        </div>

                                        {/* Action Buttons */}
                                        <div className="flex items-center gap-1 w-full justify-between pt-2 border-t border-slate-100 dark:border-gray-700">
                                            <button
                                                type="button"
                                                onClick={() => movePage(idx, idx - 1)}
                                                disabled={idx === 0}
                                                className="p-1 text-slate-400 hover:text-blue-500 disabled:opacity-20"
                                                title="Pindah Kiri"
                                            >
                                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7"></path>
                                                </svg>
                                            </button>

                                            <button
                                                type="button"
                                                onClick={() => rotatePage(idx)}
                                                className="p-1 text-slate-600 dark:text-slate-300 hover:text-emerald-500"
                                                title="Putar 90°"
                                            >
                                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                </svg>
                                            </button>

                                            <button
                                                type="button"
                                                onClick={() => deletePage(idx)}
                                                className="p-1 text-slate-400 hover:text-red-500"
                                                title="Hapus Halaman Ini"
                                            >
                                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>

                                            <button
                                                type="button"
                                                onClick={() => movePage(idx, idx + 1)}
                                                disabled={idx === pages.length - 1}
                                                className="p-1 text-slate-400 hover:text-blue-500 disabled:opacity-20"
                                                title="Pindah Kanan"
                                            >
                                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
