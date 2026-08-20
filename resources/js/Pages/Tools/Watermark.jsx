import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { PDFDocument, StandardFonts, rgb, degrees } from 'pdf-lib';
import Swal from 'sweetalert2';

export default function WatermarkPdf({ auth }) {
    const [file, setFile] = useState(null);
    const [watermarkText, setWatermarkText] = useState('CONFIDENTIAL');
    const [fontSize, setFontSize] = useState(50);
    const [opacity, setOpacity] = useState(0.25);
    const [rotation, setRotation] = useState(45);
    const [color, setColor] = useState('gray'); // 'gray', 'red', 'blue'
    const [isProcessing, setIsProcessing] = useState(false);

    const handleFileChange = (e) => {
        const selected = e.target.files[0];
        if (!selected || selected.type !== 'application/pdf') {
            Swal.fire('Format Salah', 'Pilih file PDF yang valid.', 'warning');
            return;
        }
        setFile(selected);
    };

    const handleAddWatermark = async () => {
        if (!file || !watermarkText.trim()) {
            Swal.fire('Peringatan', 'Masukkan teks watermark terlebih dahulu.', 'warning');
            return;
        }

        setIsProcessing(true);
        try {
            const buffer = await file.arrayBuffer();
            const pdfDoc = await PDFDocument.load(buffer, { ignoreEncryption: true });
            const helveticaFont = await pdfDoc.embedFont(StandardFonts.HelveticaBold);
            const pages = pdfDoc.getPages();

            let fontColor = rgb(0.5, 0.5, 0.5);
            if (color === 'red') fontColor = rgb(0.85, 0.1, 0.1);
            if (color === 'blue') fontColor = rgb(0.1, 0.3, 0.85);

            for (const page of pages) {
                const { width, height } = page.getSize();
                const textWidth = helveticaFont.widthOfTextAtSize(watermarkText, fontSize);
                const textHeight = helveticaFont.heightAtSize(fontSize);

                page.drawText(watermarkText, {
                    x: (width - textWidth) / 2,
                    y: (height - textHeight) / 2,
                    size: fontSize,
                    font: helveticaFont,
                    color: fontColor,
                    opacity: parseFloat(opacity),
                    rotate: degrees(parseFloat(rotation)),
                });
            }

            const pdfBytes = await pdfDoc.save();
            const blob = new Blob([pdfBytes], { type: 'application/pdf' });
            const url = URL.createObjectURL(blob);

            const a = document.createElement('a');
            a.href = url;
            a.download = `Watermarked_${file.name}`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            Swal.fire('Berhasil!', 'File PDF dengan watermark telah terunduh.', 'success');
        } catch (err) {
            console.error(err);
            Swal.fire('Gagal', 'Terjadi kesalahan saat menyematkan watermark.', 'error');
        } finally {
            setIsProcessing(false);
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold text-slate-800 dark:text-slate-200">Watermark PDF (Stempel Dokumen)</h2>
                    <Link href={route('tools.index')} className="text-xs text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Kembali ke Tool Hub
                    </Link>
                </div>
            }
        >
            <Head title="Watermark PDF" />

            <div className="py-6">
                <div className="mx-auto max-w-3xl">
                    {/* Privacy Guarantee Box */}
                    <div className="mb-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 flex items-center gap-3 text-xs text-emerald-800 dark:text-emerald-300">
                        <svg className="w-5 h-5 flex-shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                        <span><strong>Privasi Terjamin:</strong> Watermark disisipkan 100% di memori browser Anda tanpa pernah diunggah ke server.</span>
                    </div>

                    {!file ? (
                        <div className="bg-white dark:bg-gray-800 rounded-2xl p-8 border border-slate-200 dark:border-gray-700 shadow-sm text-center">
                            <label className="border-2 border-dashed border-slate-300 dark:border-gray-600 hover:border-cyan-500 rounded-xl p-10 flex flex-col items-center justify-center cursor-pointer transition-colors">
                                <div className="w-14 h-14 rounded-full bg-cyan-50 dark:bg-cyan-900/30 text-cyan-600 dark:text-cyan-400 flex items-center justify-center mb-3">
                                    <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path>
                                    </svg>
                                </div>
                                <span className="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                    Pilih File PDF untuk Diberi Watermark
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
                                        Teks Watermark:
                                    </label>
                                    <input
                                        type="text"
                                        value={watermarkText}
                                        onChange={(e) => setWatermarkText(e.target.value)}
                                        placeholder="Contoh: CONFIDENTIAL / DRAFT / SALINAN"
                                        className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl px-4 py-2 text-xs focus:ring-2 focus:ring-cyan-500 focus:outline-none"
                                    />
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            Ukuran Font: {fontSize}px
                                        </label>
                                        <input
                                            type="range"
                                            min="20"
                                            max="90"
                                            value={fontSize}
                                            onChange={(e) => setFontSize(Number(e.target.value))}
                                            className="w-full accent-cyan-600"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            Opasitas: {Math.round(opacity * 100)}%
                                        </label>
                                        <input
                                            type="range"
                                            min="0.05"
                                            max="0.9"
                                            step="0.05"
                                            value={opacity}
                                            onChange={(e) => setOpacity(Number(e.target.value))}
                                            className="w-full accent-cyan-600"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            Sudut Rotasi: {rotation}°
                                        </label>
                                        <input
                                            type="range"
                                            min="0"
                                            max="90"
                                            value={rotation}
                                            onChange={(e) => setRotation(Number(e.target.value))}
                                            className="w-full accent-cyan-600"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Warna Stempel:
                                    </label>
                                    <div className="flex gap-2">
                                        {[
                                            { id: 'gray', label: 'Abu-Abu Elegan' },
                                            { id: 'red', label: 'Merah (Urgent/Rahasia)' },
                                            { id: 'blue', label: 'Biru Resmi' },
                                        ].map((c) => (
                                            <button
                                                key={c.id}
                                                type="button"
                                                onClick={() => setColor(c.id)}
                                                className={`px-3 py-1.5 rounded-xl text-xs font-medium border transition-all ${
                                                    color === c.id
                                                        ? 'border-cyan-600 bg-cyan-50 dark:bg-cyan-950/40 text-cyan-700 dark:text-cyan-300'
                                                        : 'border-slate-200 dark:border-gray-700 hover:bg-slate-50'
                                                }`}
                                            >
                                                {c.label}
                                            </button>
                                        ))}
                                    </div>
                                </div>

                                <div className="mt-6 flex justify-end">
                                    <button
                                        onClick={handleAddWatermark}
                                        disabled={isProcessing}
                                        className="px-6 py-2.5 rounded-xl bg-cyan-600 hover:bg-cyan-700 text-white font-semibold text-xs shadow-md shadow-cyan-500/20 disabled:opacity-50 transition-all flex items-center gap-2"
                                    >
                                        {isProcessing ? 'Menyematkan Watermark...' : 'Sematkan Watermark Sekarang'}
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
