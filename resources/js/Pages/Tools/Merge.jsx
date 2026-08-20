import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { PDFDocument } from 'pdf-lib';
import Swal from 'sweetalert2';

export default function MergePdf({ auth }) {
    const [files, setFiles] = useState([]);
    const [isDraggingFile, setIsDraggingFile] = useState(false);
    const [isProcessing, setIsProcessing] = useState(false);

    const addPdfFiles = (incomingFiles) => {
        const selected = Array.from(incomingFiles).filter(f => f.type === 'application/pdf' || f.name.endsWith('.pdf'));
        if (selected.length === 0) {
            Swal.fire('Format Salah', 'Silakan pilih file dengan format PDF saja.', 'warning');
            return;
        }
        setFiles(prev => [...prev, ...selected]);
    };

    const handleFileChange = (e) => {
        if (e.target.files) {
            addPdfFiles(e.target.files);
        }
    };

    const handleDrop = (e) => {
        e.preventDefault();
        setIsDraggingFile(false);
        if (e.dataTransfer.files) {
            addPdfFiles(e.dataTransfer.files);
        }
    };

    const removeFile = (index) => {
        setFiles(files.filter((_, i) => i !== index));
    };

    const moveUp = (index) => {
        if (index === 0) return;
        const copy = [...files];
        const temp = copy[index - 1];
        copy[index - 1] = copy[index];
        copy[index] = temp;
        setFiles(copy);
    };

    const moveDown = (index) => {
        if (index === files.length - 1) return;
        const copy = [...files];
        const temp = copy[index + 1];
        copy[index + 1] = copy[index];
        copy[index] = temp;
        setFiles(copy);
    };

    const handleMerge = async () => {
        if (files.length < 2) {
            Swal.fire('Peringatan', 'Pilih minimal 2 file PDF untuk digabungkan.', 'warning');
            return;
        }

        setIsProcessing(true);
        try {
            const mergedPdf = await PDFDocument.create();

            for (const file of files) {
                const arrayBuffer = await file.arrayBuffer();
                const pdf = await PDFDocument.load(arrayBuffer, { ignoreEncryption: true });
                const copiedPages = await mergedPdf.copyPages(pdf, pdf.getPageIndices());
                copiedPages.forEach((page) => mergedPdf.addPage(page));
            }

            const mergedPdfBytes = await mergedPdf.save();
            const blob = new Blob([mergedPdfBytes], { type: 'application/pdf' });
            const url = URL.createObjectURL(blob);

            const downloadLink = document.createElement('a');
            downloadLink.href = url;
            downloadLink.download = `Merged_Document_${new Date().toISOString().slice(0,10)}.pdf`;
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
            URL.revokeObjectURL(url);

            // Silent telemetry usage tracking
            fetch(route('tools.track_usage'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ tool: 'merge', files_count: files.length }),
            }).catch(() => {});

            Swal.fire({
                title: 'Berhasil Digabung!',
                text: 'File PDF hasil penggabungan telah otomatis terunduh langsung dari browser Anda.',
                icon: 'success',
                confirmButtonColor: '#2563eb',
            });
        } catch (error) {
            console.error('Merge error:', error);
            Swal.fire('Gagal Menggabungkan', 'Terjadi kesalahan saat memproses file PDF. Pastikan file tidak terkunci password.', 'error');
        } finally {
            setIsProcessing(false);
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold text-slate-800 dark:text-slate-200">Merge PDF (Gabung Dokumen)</h2>
                    <Link href={route('tools.index')} className="text-xs text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Kembali ke Tool Hub
                    </Link>
                </div>
            }
        >
            <Head title="Merge PDF" />

            <div className="py-6">
                <div className="mx-auto max-w-4xl">
                    {/* Privacy Guarantee Box */}
                    <div className="mb-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 flex items-center gap-3 text-xs text-emerald-800 dark:text-emerald-300">
                        <svg className="w-5 h-5 flex-shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                        <span><strong>Privasi Terjamin:</strong> File PDF diproses 100% di memori browser Anda tanpa pernah diunggah atau disimpan ke server mana pun.</span>
                    </div>

                    {/* Upload Dropzone */}
                    <div className="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-slate-200 dark:border-gray-700 shadow-sm mb-6">
                        <label
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
                            className={`border-2 border-dashed rounded-xl p-8 flex flex-col items-center justify-center cursor-pointer transition-colors text-center ${
                                isDraggingFile
                                    ? 'border-blue-500 bg-blue-50/50 dark:bg-blue-900/20'
                                    : 'border-slate-300 dark:border-gray-600 hover:border-blue-500'
                            }`}
                        >
                            <div className="w-12 h-12 rounded-full bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center mb-3">
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                            </div>
                            <span className="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                {isDraggingFile ? 'Lepaskan Berkas PDF di Sini' : 'Klik untuk memilih file PDF atau drag file ke sini'}
                            </span>
                            <span className="text-xs text-slate-400 mt-1">Dapat memilih lebih dari 1 file sekaligus</span>
                            <input type="file" multiple accept="application/pdf" onChange={handleFileChange} className="hidden" />
                        </label>
                    </div>

                    {/* File List */}
                    {files.length > 0 && (
                        <div className="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-slate-200 dark:border-gray-700 shadow-sm mb-6">
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="text-sm font-bold text-slate-800 dark:text-white">
                                    Daftar File ({files.length}) — Urutkan Sesuai Keinginan
                                </h3>
                                <button
                                    onClick={() => setFiles([])}
                                    className="text-xs text-red-500 hover:underline"
                                >
                                    Hapus Semua
                                </button>
                            </div>

                            <div className="space-y-2">
                                {files.map((file, idx) => (
                                    <div
                                        key={idx}
                                        className="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-gray-700/50 border border-slate-200/80 dark:border-gray-600/60"
                                    >
                                        <div className="flex items-center gap-3 overflow-hidden">
                                            <span className="w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center flex-shrink-0">
                                                {idx + 1}
                                            </span>
                                            <div className="truncate">
                                                <p className="text-xs font-semibold text-slate-800 dark:text-white truncate">
                                                    {file.name}
                                                </p>
                                                <p className="text-[10px] text-slate-400">
                                                    {(file.size / 1024 / 1024).toFixed(2)} MB
                                                </p>
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-1">
                                            <button
                                                onClick={() => moveUp(idx)}
                                                disabled={idx === 0}
                                                className="p-1 text-slate-400 hover:text-blue-500 disabled:opacity-30"
                                                title="Pindah ke atas"
                                            >
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 15l7-7 7 7"></path>
                                                </svg>
                                            </button>
                                            <button
                                                onClick={() => moveDown(idx)}
                                                disabled={idx === files.length - 1}
                                                className="p-1 text-slate-400 hover:text-blue-500 disabled:opacity-30"
                                                title="Pindah ke bawah"
                                            >
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"></path>
                                                </svg>
                                            </button>
                                            <button
                                                onClick={() => removeFile(idx)}
                                                className="p-1 text-slate-400 hover:text-red-500 ml-2"
                                                title="Hapus"
                                            >
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {/* Merge Button */}
                            <div className="mt-6 flex justify-end">
                                <button
                                    onClick={handleMerge}
                                    disabled={isProcessing || files.length < 2}
                                    className="px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs shadow-md shadow-blue-500/20 disabled:opacity-50 transition-all flex items-center gap-2"
                                >
                                    {isProcessing ? (
                                        <>
                                            <svg className="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                            </svg>
                                            Menggabungkan PDF...
                                        </>
                                    ) : (
                                        <>
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path>
                                            </svg>
                                            Gabungkan {files.length} File PDF
                                        </>
                                    )}
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
