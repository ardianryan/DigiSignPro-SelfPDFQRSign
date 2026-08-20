import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { PDFDocument, PageSizes } from 'pdf-lib';
import Swal from 'sweetalert2';

export default function ImageToPdf({ auth }) {
    const [images, setImages] = useState([]);
    const [pageSize, setPageSize] = useState('A4'); // 'A4', 'Letter', 'Fit'
    const [orientation, setOrientation] = useState('portrait'); // 'portrait', 'landscape'
    const [isProcessing, setIsProcessing] = useState(false);

    const handleFileChange = (e) => {
        const selected = Array.from(e.target.files).filter(f => f.type.startsWith('image/'));
        if (selected.length === 0) {
            Swal.fire('Format Salah', 'Pilih file gambar (JPG, PNG, WebP).', 'warning');
            return;
        }

        const newItems = selected.map(file => ({
            file,
            preview: URL.createObjectURL(file),
            name: file.name
        }));

        setImages(prev => [...prev, ...newItems]);
    };

    const removeImage = (index) => {
        URL.revokeObjectURL(images[index].preview);
        setImages(images.filter((_, i) => i !== index));
    };

    const handleConvert = async () => {
        if (images.length === 0) {
            Swal.fire('Peringatan', 'Pilih minimal 1 gambar untuk dikonversi.', 'warning');
            return;
        }

        setIsProcessing(true);
        try {
            const pdfDoc = await PDFDocument.create();

            for (const item of images) {
                const imgBuffer = await item.file.arrayBuffer();
                let embeddedImg;

                if (item.file.type === 'image/png') {
                    embeddedImg = await pdfDoc.embedPng(imgBuffer);
                } else {
                    embeddedImg = await pdfDoc.embedJpg(imgBuffer);
                }

                let pageWidth = PageSizes.A4[0];
                let pageHeight = PageSizes.A4[1];

                if (pageSize === 'Letter') {
                    pageWidth = PageSizes.Letter[0];
                    pageHeight = PageSizes.Letter[1];
                } else if (pageSize === 'Fit') {
                    pageWidth = embeddedImg.width;
                    pageHeight = embeddedImg.height;
                }

                if (pageSize !== 'Fit' && orientation === 'landscape') {
                    const temp = pageWidth;
                    pageWidth = pageHeight;
                    pageHeight = temp;
                }

                const page = pdfDoc.addPage([pageWidth, pageHeight]);

                // Fit image maintaining aspect ratio
                const scaleFactor = Math.min(pageWidth / embeddedImg.width, pageHeight / embeddedImg.height);
                const drawWidth = embeddedImg.width * scaleFactor;
                const drawHeight = embeddedImg.height * scaleFactor;
                const x = (pageWidth - drawWidth) / 2;
                const y = (pageHeight - drawHeight) / 2;

                page.drawImage(embeddedImg, {
                    x,
                    y,
                    width: drawWidth,
                    height: drawHeight,
                });
            }

            const pdfBytes = await pdfDoc.save();
            const blob = new Blob([pdfBytes], { type: 'application/pdf' });
            const url = URL.createObjectURL(blob);

            const a = document.createElement('a');
            a.href = url;
            a.download = `Images_Converted_${new Date().toISOString().slice(0,10)}.pdf`;
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
                body: JSON.stringify({ tool: 'image_to_pdf', files_count: images.length }),
            }).catch(() => {});

            Swal.fire('Berhasil!', 'File PDF hasil konversi gambar telah terunduh.', 'success');
        } catch (err) {
            console.error(err);
            Swal.fire('Gagal', 'Terjadi kesalahan saat mengonversi gambar ke PDF.', 'error');
        } finally {
            setIsProcessing(false);
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold text-slate-800 dark:text-slate-200">Image to PDF (Konversi Gambar)</h2>
                    <Link href={route('tools.index')} className="text-xs text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Kembali ke Tool Hub
                    </Link>
                </div>
            }
        >
            <Head title="Image to PDF" />

            <div className="py-6">
                <div className="mx-auto max-w-4xl">
                    {/* Privacy Guarantee Box */}
                    <div className="mb-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 flex items-center gap-3 text-xs text-emerald-800 dark:text-emerald-300">
                        <svg className="w-5 h-5 flex-shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                        <span><strong>Privasi Terjamin:</strong> Gambar dikonversi ke format PDF secara instan di memori perangkat Anda tanpa pernah diunggah ke server.</span>
                    </div>

                    {/* Upload Dropzone */}
                    <div className="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-slate-200 dark:border-gray-700 shadow-sm mb-6">
                        <label className="border-2 border-dashed border-slate-300 dark:border-gray-600 hover:border-amber-500 rounded-xl p-8 flex flex-col items-center justify-center cursor-pointer transition-colors text-center">
                            <div className="w-12 h-12 rounded-full bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 flex items-center justify-center mb-3">
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <span className="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                Pilih Gambar (JPG / PNG / WebP)
                            </span>
                            <span className="text-xs text-slate-400 mt-1">Dapat memilih banyak gambar sekaligus untuk digabung ke 1 PDF</span>
                            <input type="file" multiple accept="image/*" onChange={handleFileChange} className="hidden" />
                        </label>
                    </div>

                    {/* Options & Image Grid */}
                    {images.length > 0 && (
                        <div className="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-slate-200 dark:border-gray-700 shadow-sm">
                            {/* Page Setup Controls */}
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pb-6 mb-6 border-b border-slate-100 dark:border-gray-700">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Ukuran Kertas:
                                    </label>
                                    <select
                                        value={pageSize}
                                        onChange={(e) => setPageSize(e.target.value)}
                                        className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                    >
                                        <option value="A4">A4 (Standar)</option>
                                        <option value="Letter">US Letter</option>
                                        <option value="Fit">Sesuaikan Ukuran Asli Gambar (Fit)</option>
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Orientasi Halaman:
                                    </label>
                                    <select
                                        value={orientation}
                                        onChange={(e) => setOrientation(e.target.value)}
                                        disabled={pageSize === 'Fit'}
                                        className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none disabled:opacity-40"
                                    >
                                        <option value="portrait">Tegak (Portrait)</option>
                                        <option value="landscape">Mendatar (Landscape)</option>
                                    </select>
                                </div>
                            </div>

                            {/* Image Previews */}
                            <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 mb-6">
                                {images.map((item, idx) => (
                                    <div key={idx} className="relative rounded-xl overflow-hidden border border-slate-200 dark:border-gray-700 group aspect-3/4 bg-slate-100 dark:bg-gray-900">
                                        <img src={item.preview} alt={item.name} className="w-full h-full object-cover" />
                                        <div className="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                            <button
                                                type="button"
                                                onClick={() => removeImage(idx)}
                                                className="p-2 rounded-full bg-red-600 text-white hover:bg-red-700 shadow-md"
                                                title="Hapus gambar"
                                            >
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </div>
                                        <span className="absolute bottom-2 left-2 px-2 py-0.5 rounded bg-slate-900/80 text-white text-[10px] font-bold">
                                            Hal. {idx + 1}
                                        </span>
                                    </div>
                                ))}
                            </div>

                            <div className="flex justify-end">
                                <button
                                    onClick={handleConvert}
                                    disabled={isProcessing}
                                    className="px-6 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-semibold text-xs shadow-md shadow-amber-500/20 disabled:opacity-50 transition-all flex items-center gap-2"
                                >
                                    {isProcessing ? 'Mengonversi...' : `Konversi ${images.length} Gambar ke PDF`}
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
