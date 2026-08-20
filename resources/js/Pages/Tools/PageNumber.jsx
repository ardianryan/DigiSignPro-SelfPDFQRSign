import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { PDFDocument, StandardFonts, rgb } from 'pdf-lib';
import Swal from 'sweetalert2';

export default function PageNumberPdf({ auth }) {
    const [file, setFile] = useState(null);
    const [position, setPosition] = useState('bottom-center'); // 'bottom-center', 'bottom-right', 'bottom-left', 'top-center'
    const [format, setFormat] = useState('page_of_total'); // 'page_of_total', 'page_only', 'page_dash'
    const [fontSize, setFontSize] = useState(10);
    const [isProcessing, setIsProcessing] = useState(false);

    const handleFileChange = (e) => {
        const selected = e.target.files[0];
        if (!selected || selected.type !== 'application/pdf') {
            Swal.fire('Format Salah', 'Pilih file PDF yang valid.', 'warning');
            return;
        }
        setFile(selected);
    };

    const handleAddNumbers = async () => {
        if (!file) return;

        setIsProcessing(true);
        try {
            const buffer = await file.arrayBuffer();
            const pdfDoc = await PDFDocument.load(buffer, { ignoreEncryption: true });
            const helveticaFont = await pdfDoc.embedFont(StandardFonts.Helvetica);
            const pages = pdfDoc.getPages();
            const total = pages.length;

            pages.forEach((page, index) => {
                const { width, height } = page.getSize();
                const pageNum = index + 1;

                let text = `${pageNum} / ${total}`;
                if (format === 'page_of_total') text = `Halaman ${pageNum} dari ${total}`;
                if (format === 'page_only') text = `${pageNum}`;
                if (format === 'page_dash') text = `- ${pageNum} -`;

                const textWidth = helveticaFont.widthOfTextAtSize(text, fontSize);
                let x = (width - textWidth) / 2; // default center
                let y = 30; // default bottom

                if (position === 'bottom-left') x = 40;
                if (position === 'bottom-right') x = width - textWidth - 40;
                if (position === 'top-center') y = height - 30;

                page.drawText(text, {
                    x,
                    y,
                    size: fontSize,
                    font: helveticaFont,
                    color: rgb(0.3, 0.3, 0.3),
                });
            });

            const pdfBytes = await pdfDoc.save();
            const blob = new Blob([pdfBytes], { type: 'application/pdf' });
            const url = URL.createObjectURL(blob);

            const a = document.createElement('a');
            a.href = url;
            a.download = `Numbered_${file.name}`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            Swal.fire('Berhasil!', 'Nomor halaman telah berhasil disisipkan.', 'success');
        } catch (err) {
            console.error(err);
            Swal.fire('Gagal', 'Terjadi kesalahan saat menyematkan nomor halaman.', 'error');
        } finally {
            setIsProcessing(false);
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold text-slate-800 dark:text-slate-200">Page Numbering (Penomoran Halaman)</h2>
                    <Link href={route('tools.index')} className="text-xs text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Kembali ke Tool Hub
                    </Link>
                </div>
            }
        >
            <Head title="Page Numbering" />

            <div className="py-6">
                <div className="mx-auto max-w-3xl">
                    {/* Privacy Guarantee Box */}
                    <div className="mb-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 flex items-center gap-3 text-xs text-emerald-800 dark:text-emerald-300">
                        <svg className="w-5 h-5 flex-shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                        <span><strong>Privasi Terjamin:</strong> Penomoran halaman diolah langsung di memori browser Anda tanpa upload ke server.</span>
                    </div>

                    {!file ? (
                        <div className="bg-white dark:bg-gray-800 rounded-2xl p-8 border border-slate-200 dark:border-gray-700 shadow-sm text-center">
                            <label className="border-2 border-dashed border-slate-300 dark:border-gray-600 hover:border-violet-500 rounded-xl p-10 flex flex-col items-center justify-center cursor-pointer transition-colors">
                                <div className="w-14 h-14 rounded-full bg-violet-50 dark:bg-violet-900/30 text-violet-600 dark:text-violet-400 flex items-center justify-center mb-3">
                                    <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                    </svg>
                                </div>
                                <span className="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                    Pilih File PDF untuk Diberi Nomor Halaman
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
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            Format Penomoran:
                                        </label>
                                        <select
                                            value={format}
                                            onChange={(e) => setFormat(e.target.value)}
                                            className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-violet-500 focus:outline-none"
                                        >
                                            <option value="page_of_total">Halaman 1 dari 10</option>
                                            <option value="page_only">1</option>
                                            <option value="page_dash">- 1 -</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            Posisi Nomor:
                                        </label>
                                        <select
                                            value={position}
                                            onChange={(e) => setPosition(e.target.value)}
                                            className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-violet-500 focus:outline-none"
                                        >
                                            <option value="bottom-center">Bawah Tengah (Rekomendasi)</option>
                                            <option value="bottom-right">Bawah Kanan</option>
                                            <option value="bottom-left">Bawah Kiri</option>
                                            <option value="top-center">Atas Tengah (Header)</option>
                                        </select>
                                    </div>
                                </div>

                                <div className="mt-6 flex justify-end">
                                    <button
                                        onClick={handleAddNumbers}
                                        disabled={isProcessing}
                                        className="px-6 py-2.5 rounded-xl bg-violet-600 hover:bg-violet-700 text-white font-semibold text-xs shadow-md shadow-violet-500/20 disabled:opacity-50 transition-all flex items-center gap-2"
                                    >
                                        {isProcessing ? 'Menyisipkan Nomor...' : 'Sisipkan Nomor Halaman'}
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
