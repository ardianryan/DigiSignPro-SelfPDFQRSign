import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import Swal from 'sweetalert2';

export default function Bulk({ auth, max_upload_size_bulk }) {
    const maxMb = Math.round(max_upload_size_bulk / (1024 * 1024));

    const [zipLoaded, setZipLoaded] = useState(false);
    const [batchId, setBatchId] = useState('');
    const [previewFilename, setPreviewFilename] = useState('');
    const [pageNum, setPageNum] = useState(1);
    const [totalPage, setTotalPage] = useState(0);
    const [isUploading, setIsUploading] = useState(false);
    const [isProcessing, setIsProcessing] = useState(false);

    // Marker coordinate state
    const [markerX, setMarkerX] = useState(0);
    const [markerY, setMarkerY] = useState(0);
    const [markerSize, setMarkerSize] = useState(100);
    const [scale] = useState(1.5);

    const [formData, setFormData] = useState({
        base_number: '',
        subject: '',
        pdf_password: '',
        show_qr_caption: true,
        qr_caption_position: 'bottom'
    });

    const canvasRef = useRef(null);
    const containerRef = useRef(null);
    const markerRef = useRef(null);
    const pdfDocRef = useRef(null);

    const [isDraggingMarker, setIsDraggingMarker] = useState(false);
    const dragOffset = useRef({ x: 0, y: 0 });

    // Load PDF.js dynamically
    useEffect(() => {
        if (window.pdfjsLib) return;

        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
        script.onload = () => {
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        };
        document.body.appendChild(script);
    }, []);

    const uploadZip = async (selectedFile) => {
        if (!selectedFile) return;

        if (!selectedFile.name.endsWith('.zip') && selectedFile.type !== 'application/zip' && selectedFile.type !== 'application/x-zip-compressed') {
            Swal.fire('Error', 'File harus berupa arsip ZIP.', 'error');
            return;
        }

        if (selectedFile.size > max_upload_size_bulk) {
            Swal.fire('Error', `Ukuran ZIP melebihi batas maksimal (${maxMb}MB).`, 'error');
            return;
        }

        setIsUploading(true);
        Swal.fire({
            title: 'Mengunggah & Mengekstrak',
            text: 'Membaca preview dokumen ZIP...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const uploadData = new FormData();
        uploadData.append('zip_file', selectedFile);

        try {
            const response = await fetch(route('sign.bulk.preview'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: uploadData
            });

            const result = await response.json();
            setIsUploading(false);
            Swal.close();

            if (result.status === 'success') {
                setBatchId(result.batch_id);
                setPreviewFilename(result.filename);
                setZipLoaded(true);

                // Fetch and render the preview PDF
                const pdfResponse = await fetch(result.preview_url);
                const arrayBuffer = await pdfResponse.arrayBuffer();
                const typedarray = new Uint8Array(arrayBuffer);

                const loadingTask = window.pdfjsLib.getDocument(typedarray);
                const pdf = await loadingTask.promise;
                pdfDocRef.current = pdf;
                setTotalPage(pdf.numPages);
                setPageNum(1);
                setTimeout(() => renderPage(1), 100);
            } else {
                Swal.fire('Gagal', result.message || 'Gagal mengekstrak ZIP.', 'error');
            }
        } catch (err) {
            setIsUploading(false);
            Swal.fire('Error', 'Terjadi kesalahan saat memproses preview: ' + err.message, 'error');
        }
    };

    const renderPage = async (num) => {
        if (!pdfDocRef.current || !canvasRef.current) return;

        try {
            const page = await pdfDocRef.current.getPage(num);
            const canvas = canvasRef.current;
            const ctx = canvas.getContext('2d');
            const viewport = page.getViewport({ scale });

            canvas.height = viewport.height;
            canvas.width = viewport.width;

            const renderContext = {
                canvasContext: ctx,
                viewport: viewport
            };

            await page.render(renderContext).promise;

            const targetMm = 25;
            const targetPoints = targetMm * 2.83465;

            const pdfPointsWidth = canvas.width / scale;
            if (pdfPointsWidth > 0) {
                const cssToPointsRatio = canvas.clientWidth / pdfPointsWidth;
                const markerPx = targetPoints * cssToPointsRatio;
                setMarkerSize(markerPx);

                setMarkerX(canvas.offsetLeft + (canvas.clientWidth - markerPx) / 2);
                setMarkerY(canvas.offsetTop + (canvas.clientHeight - markerPx) / 2);
            }
        } catch (err) {
            console.error('Error rendering bulk preview page:', err);
        }
    };

    useEffect(() => {
        if (zipLoaded) {
            renderPage(pageNum);
        }
    }, [pageNum, zipLoaded]);

    const changePage = (delta) => {
        const next = pageNum + delta;
        if (next >= 1 && next <= totalPage) {
            setPageNum(next);
        }
    };

    // Drag Marker
    const startDragMarker = (e) => {
        e.preventDefault();
        setIsDraggingMarker(true);

        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;

        dragOffset.current = {
            x: clientX - markerX,
            y: clientY - markerY
        };
    };

    useEffect(() => {
        const doDrag = (e) => {
            if (!isDraggingMarker) return;
            e.preventDefault();

            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;

            let newLeft = clientX - dragOffset.current.x;
            let newTop = clientY - dragOffset.current.y;

            const canvas = canvasRef.current;
            if (canvas) {
                const minLeft = canvas.offsetLeft;
                const minTop = canvas.offsetTop;
                const maxLeft = minLeft + canvas.clientWidth - markerSize;
                const maxTop = minTop + canvas.clientHeight - markerSize;

                if (newLeft < minLeft) newLeft = minLeft;
                if (newTop < minTop) newTop = minTop;
                if (newLeft > maxLeft) newLeft = maxLeft;
                if (newTop > maxTop) newTop = maxTop;

                setMarkerX(newLeft);
                setMarkerY(newTop);
            }
        };

        const stopDrag = () => {
            setIsDraggingMarker(false);
        };

        if (isDraggingMarker) {
            window.addEventListener('mousemove', doDrag, { passive: false });
            window.addEventListener('mouseup', stopDrag);
            window.addEventListener('touchmove', doDrag, { passive: false });
            window.addEventListener('touchend', stopDrag);
        }

        return () => {
            window.removeEventListener('mousemove', doDrag);
            window.removeEventListener('mouseup', stopDrag);
            window.removeEventListener('touchmove', doDrag);
            window.removeEventListener('touchend', stopDrag);
        };
    }, [isDraggingMarker, markerX, markerY, markerSize]);

    const processBulkSigning = async () => {
        if (!formData.pdf_password) {
            Swal.fire('Peringatan', 'Password Parafrase wajib diisi untuk keamanan ZIP.', 'warning');
            return;
        }

        setIsProcessing(true);
        Swal.fire({
            title: 'Memproses Tanda Tangan Masal (Bulk)',
            text: 'Menandatangani PDF satu per satu. Mohon tunggu...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        try {
            const canvas = canvasRef.current;
            const visualScale = canvas.width / canvas.clientWidth;

            const visualX = markerX - canvas.offsetLeft;
            const visualY = markerY - canvas.offsetTop;

            const realX = visualX * visualScale;
            const realY = visualY * visualScale;

            const xPt = realX / scale;
            const yPt = realY / scale;

            const xMm = xPt * 0.352778;
            const yMm = yPt * 0.352778;

            const submitData = new FormData();
            submitData.append('batch_id', batchId);
            submitData.append('x', xMm);
            submitData.append('y', yMm);
            submitData.append('page', pageNum);
            submitData.append('base_number', formData.base_number);
            submitData.append('subject', formData.subject);
            submitData.append('pdf_password', formData.pdf_password);
            submitData.append('show_qr_caption', formData.show_qr_caption ? '1' : '0');
            submitData.append('qr_caption_position', formData.qr_caption_position);

            const response = await fetch(route('sign.bulk.store'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: submitData
            });

            const result = await response.json();
            setIsProcessing(false);

            if (result.status === 'success') {
                let successMessage = `Proses selesai!<br>Berhasil: <b>${result.processed}</b> dokumen.`;
                if (result.failed > 0) {
                    successMessage += `<br>Gagal: <b>${result.failed}</b> dokumen.`;
                }

                Swal.fire({
                    title: 'Sukses!',
                    html: successMessage,
                    icon: 'success',
                    confirmButtonText: 'Unduh ZIP Signed'
                }).then(() => {
                    window.location.href = result.zip_url;
                    reset();
                    router.get(route('history.index'));
                });
            } else {
                Swal.fire('Gagal', result.message || 'Gagal menandatangani ZIP secara bulk.', 'error');
            }
        } catch (err) {
            setIsProcessing(false);
            Swal.fire('Error', 'Terjadi kesalahan sistem: ' + err.message, 'error');
        }
    };

    const reset = () => {
        setZipLoaded(false);
        setBatchId('');
        setPreviewFilename('');
        setPageNum(1);
        setTotalPage(0);
        pdfDocRef.current = null;
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-slate-800 dark:text-slate-200">Bulk Sign (Massal)</h2>}
        >
            <Head title="Bulk Sign" />

            <div className="py-6">
                <div className="mx-auto max-w-5xl">
                    <div className="mb-6">
                        <p className="text-slate-500 dark:text-slate-400 font-medium">Upload ZIP berisi banyak file PDF, posisikan QR, lalu tandatangani sekaligus.</p>
                    </div>

                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700 p-6">
                        
                        {/* Step 1: ZIP Upload */}
                        {!zipLoaded && (
                            <div className="text-center py-20 border-2 border-dashed border-slate-300 dark:border-gray-600 bg-slate-50 dark:bg-gray-700/50 rounded-xl">
                                <svg className="mx-auto h-12 w-12 text-slate-400 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 4H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-2m-4-1v8m0 0l3-3m-3 3L9 8m-5 5h2.586a1 1 0 01.707.293l2.414 2.414a1 1 0 00.707.293h3.172a1 1 0 00.707-.293l2.414-2.414a1 1 0 01.707-.293H20"></path>
                                </svg>
                                <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">Pilih berkas ZIP untuk diunggah</p>
                                <label className="mt-4 inline-block px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg cursor-pointer transition-colors font-medium">
                                    Pilih File ZIP
                                    <input type="file" accept=".zip" className="hidden" onChange={(e) => uploadZip(e.target.files[0])} />
                                </label>
                                <p className="mt-2 text-xs text-slate-400 dark:text-gray-400">Maksimal {maxMb}MB. Format ZIP berisi file PDF.</p>
                            </div>
                        )}

                        {/* Step 2: Editor Preview */}
                        {zipLoaded && (
                            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                                {/* Left Form Control */}
                                <div className="lg:col-span-1 space-y-4">
                                    <div className="bg-slate-50 dark:bg-gray-700/50 p-6 rounded-xl border border-slate-200 dark:border-gray-600">
                                        <h3 className="font-bold text-slate-800 dark:text-white mb-4">Metadata Batch</h3>
                                        <div className="space-y-4">
                                            <div>
                                                <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">Prefix / Nomor Basis</label>
                                                <input
                                                    type="text"
                                                    value={formData.base_number}
                                                    onChange={(e) => setFormData({ ...formData, base_number: e.target.value })}
                                                    placeholder="Contoh: DS-2026-OUT"
                                                    className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                                />
                                                <p className="text-[10px] text-slate-400 mt-1">Nomor dokumen otomatis diurutkan (Contoh: DS-2026-OUT - 1, DS-2026-OUT - 2, dst).</p>
                                            </div>

                                            <div>
                                                <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">Perihal Batch</label>
                                                <textarea
                                                    value={formData.subject}
                                                    onChange={(e) => setFormData({ ...formData, subject: e.target.value })}
                                                    placeholder="Perihal tanda tangan massal..."
                                                    rows="3"
                                                    className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                                />
                                            </div>

                                            <div>
                                                <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">Password Parafrase <span className="text-red-500">*</span></label>
                                                <input
                                                    type="password"
                                                    value={formData.pdf_password}
                                                    onChange={(e) => setFormData({ ...formData, pdf_password: e.target.value })}
                                                    placeholder="Masukkan password parafrase..."
                                                    className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                                />
                                                <p className="text-xs text-slate-400 mt-1">Dibutuhkan untuk mengenkripsi dan mengamankan seluruh PDF.</p>
                                            </div>

                                            <div className="flex items-start">
                                                <input
                                                    id="show_qr_caption"
                                                    type="checkbox"
                                                    checked={formData.show_qr_caption}
                                                    onChange={(e) => setFormData({ ...formData, show_qr_caption: e.target.checked })}
                                                    className="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500 mt-1"
                                                />
                                                <div className="ml-2 text-sm">
                                                    <label htmlFor="show_qr_caption" class="font-medium text-slate-700 dark:text-slate-200">Tampilkan Keterangan QR</label>
                                                    <p className="text-slate-400 text-xs">Menyematkan detail verifikasi di sekeliling QR Code.</p>
                                                </div>
                                            </div>

                                            {formData.show_qr_caption && (
                                                <div className="pl-3 border-l-2 border-slate-200 dark:border-gray-600 space-y-2">
                                                    <p className="text-xs font-semibold text-slate-600 dark:text-slate-300">Posisi Keterangan:</p>
                                                    <div className="flex items-center space-x-4">
                                                        <label className="inline-flex items-center">
                                                            <input
                                                                type="radio"
                                                                name="qr_position"
                                                                checked={formData.qr_caption_position === 'bottom'}
                                                                onChange={() => setFormData({ ...formData, qr_caption_position: 'bottom' })}
                                                                className="text-blue-600 focus:ring-blue-500"
                                                            />
                                                            <span className="ml-2 text-xs text-slate-700 dark:text-slate-200">Di Bawah QR</span>
                                                        </label>
                                                        <label className="inline-flex items-center">
                                                            <input
                                                                type="radio"
                                                                name="qr_position"
                                                                checked={formData.qr_caption_position === 'right'}
                                                                onChange={() => setFormData({ ...formData, qr_caption_position: 'right' })}
                                                                className="text-blue-600 focus:ring-blue-500"
                                                            />
                                                            <span className="ml-2 text-xs text-slate-700 dark:text-slate-200">Di Samping</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    <div className="flex gap-2">
                                        <button
                                            onClick={reset}
                                            className="flex-1 py-2 px-4 border border-slate-300 dark:border-gray-600 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-gray-700 transition-colors"
                                        >
                                            Batal
                                        </button>
                                        <button
                                            onClick={processBulkSigning}
                                            disabled={isProcessing}
                                            className="flex-2 py-2 px-6 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-bold transition-colors shadow-md disabled:opacity-50"
                                        >
                                            Tanda Tangani Semua
                                        </button>
                                    </div>
                                </div>

                                {/* Right Canvas Preview Workspace */}
                                <div className="lg:col-span-2 flex flex-col items-center">
                                    <div className="flex items-center justify-between w-full max-w-lg mb-4 bg-slate-50 dark:bg-gray-700 px-4 py-2 rounded-lg border border-slate-200 dark:border-gray-600">
                                        <button
                                            onClick={() => changePage(-1)}
                                            disabled={pageNum <= 1}
                                            className="px-3 py-1 bg-white dark:bg-gray-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-gray-600 rounded-md text-xs hover:bg-slate-50 disabled:opacity-50"
                                        >
                                            Prev
                                        </button>
                                        <span className="text-sm font-medium text-slate-800 dark:text-white">
                                            Preview: {previewFilename} (Halaman {pageNum}/{totalPage})
                                        </span>
                                        <button
                                            onClick={() => changePage(1)}
                                            disabled={pageNum >= totalPage}
                                            className="px-3 py-1 bg-white dark:bg-gray-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-gray-600 rounded-md text-xs hover:bg-slate-50 disabled:opacity-50"
                                        >
                                            Next
                                        </button>
                                    </div>

                                    <div
                                        ref={containerRef}
                                        className="relative bg-slate-200 dark:bg-gray-900 p-4 rounded-xl border border-slate-300 dark:border-gray-600 overflow-auto max-w-full"
                                        style={{ minHeight: '600px' }}
                                    >
                                        <canvas ref={canvasRef} id="pdf-render" className="shadow-lg mx-auto" />

                                        {/* Drag Marker */}
                                        <div
                                            ref={markerRef}
                                            id="qr-marker"
                                            onMouseDown={startDragMarker}
                                            onTouchStart={startDragMarker}
                                            style={{
                                                position: 'absolute',
                                                left: `${markerX}px`,
                                                top: `${markerY}px`,
                                                width: `${markerSize}px`,
                                                height: `${markerSize}px`,
                                                border: '2px dashed #3b82f6',
                                                background: 'rgba(59, 130, 246, 0.2)',
                                                cursor: 'move',
                                                display: 'flex',
                                                flexDirection: 'column',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                zIndex: 10,
                                                touchAction: 'none'
                                            }}
                                            className="rounded-lg shadow-md select-none"
                                        >
                                            <div className="w-8 h-8 text-blue-600 flex items-center justify-center">
                                                <svg className="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M12 4v1m6 11h2m-6 0h-2v4h-4v-2h-2v4h6v-2h2v-2h2v-2h-2v2zM12 2h2v2h-2V2zm4 4v2h2V6h-2zm-4 4v2h2v-2h-2v2zM6 6h4v4H6V6zm14 0h-4v4h4V6zM6 16h4v4H6v-4z"></path>
                                                </svg>
                                            </div>
                                            <span className="text-[9px] text-blue-700 font-bold bg-white/80 dark:bg-slate-900/80 px-1 rounded mt-1 select-none pointer-events-none">
                                                TTE QR STAMP
                                            </span>
                                        </div>
                                    </div>

                                    <p className="text-xs text-slate-400 dark:text-gray-400 mt-2">
                                        * Koordinat stamp biru ini akan diterapkan ke semua dokumen di dalam ZIP secara otomatis.
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
