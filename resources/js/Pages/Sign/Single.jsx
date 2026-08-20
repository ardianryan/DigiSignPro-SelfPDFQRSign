import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';
import Swal from 'sweetalert2';

export default function SingleSign({ auth, max_upload_size = 10485760 }) {
    const maxMb = Math.round(max_upload_size / (1024 * 1024));

    const [file, setFile] = useState(null);
    const [pdfLoaded, setPdfLoaded] = useState(false);
    const [pageNum, setPageNum] = useState(1);
    const [totalPage, setTotalPage] = useState(0);
    const [pageRotation, setPageRotation] = useState(0); // 0, 90, 180, 270
    const [zoomScale, setZoomScale] = useState(1.0);
    
    // Canvas dimensions
    const [canvasDims, setCanvasDims] = useState({ width: 0, height: 0 });

    // Marker coordinates relative directly to the canvas (0 to canvas.width - markerSize)
    const [markerX, setMarkerX] = useState(100);
    const [markerY, setMarkerY] = useState(100);
    const [markerSize, setMarkerSize] = useState(80);
    const [isDraggingMarker, setIsDraggingMarker] = useState(false);
    const [isDraggingFile, setIsDraggingFile] = useState(false);

    // Form data
    const [formData, setFormData] = useState({
        document_number: '',
        document_subject: '',
        document_attachment: '',
        signed_date: new Date().toISOString().split('T')[0],
        pdf_password: '',
        show_qr_caption: true,
        qr_caption_position: 'bottom'
    });

    const [isProcessing, setIsProcessing] = useState(false);

    const canvasRef = useRef(null);
    const containerRef = useRef(null);
    const pdfDocRef = useRef(null);
    const dragStartRef = useRef({ mouseX: 0, mouseY: 0, markerX: 0, markerY: 0 });
    const baseViewportRef = useRef(null);

    // Ensure PDF.js is loaded
    useEffect(() => {
        if (!window.pdfjsLib) {
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
            script.onload = () => {
                window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
            };
            document.body.appendChild(script);
        }
    }, []);

    const handleFile = (selectedFile) => {
        if (!selectedFile) return;

        const isPdfMime = selectedFile.type === 'application/pdf';
        const isPdfExt = selectedFile.name.toLowerCase().endsWith('.pdf');

        if (!isPdfMime && !isPdfExt) {
            Swal.fire('Format Salah', 'Silakan unggah file PDF yang valid.', 'warning');
            return;
        }

        if (selectedFile.size > max_upload_size) {
            Swal.fire('Error', `Ukuran file melebihi batas maksimal (${maxMb}MB).`, 'error');
            return;
        }

        setFile(selectedFile);
        
        const reader = new FileReader();
        reader.onload = async function (e) {
            const typedarray = new Uint8Array(e.target.result);
            try {
                const loadingTask = window.pdfjsLib.getDocument({ data: typedarray });
                const pdf = await loadingTask.promise;
                pdfDocRef.current = pdf;
                setTotalPage(pdf.numPages);
                setPageNum(1);
                setPageRotation(0);
                setPdfLoaded(true);
            } catch (err) {
                console.error(err);
                Swal.fire('Error', 'Gagal membaca PDF. Pastikan file tidak rusak.', 'error');
            }
        };
        reader.readAsArrayBuffer(selectedFile);
    };

    // Render Page with clean, sharp canvas
    const renderPage = async (num, rot = pageRotation, scaleMult = zoomScale) => {
        if (!pdfDocRef.current || !canvasRef.current || !containerRef.current) return;

        try {
            const page = await pdfDocRef.current.getPage(num);
            const canvas = canvasRef.current;
            const container = containerRef.current;
            const ctx = canvas.getContext('2d');

            // Calculate rotation
            const totalRotation = (page.rotate + rot) % 360;
            const baseViewport = page.getViewport({ scale: 1, rotation: totalRotation });
            baseViewportRef.current = baseViewport;

            // Fit width inside container smoothly
            const padding = 32;
            const containerWidth = Math.max(300, container.clientWidth - padding);
            const autoFitScale = Math.min(2.0, containerWidth / baseViewport.width);
            const finalScale = Math.max(0.6, autoFitScale * scaleMult);

            const viewport = page.getViewport({ scale: finalScale, rotation: totalRotation });

            // Set canvas pixel buffer & CSS size
            canvas.width = Math.floor(viewport.width);
            canvas.height = Math.floor(viewport.height);
            canvas.style.width = `${Math.floor(viewport.width)}px`;
            canvas.style.height = `${Math.floor(viewport.height)}px`;

            setCanvasDims({
                width: Math.floor(viewport.width),
                height: Math.floor(viewport.height)
            });

            await page.render({
                canvasContext: ctx,
                viewport: viewport,
            }).promise;

            // Target stamp size in pixels (25mm in points = ~70.87pt)
            const targetMm = 25;
            const targetPoints = targetMm * 2.83465;
            const pointToPixelScale = viewport.width / baseViewport.width;
            const stampPx = Math.max(48, Math.round(targetPoints * pointToPixelScale));
            setMarkerSize(stampPx);

            // Clamp marker position inside canvas
            setMarkerX(prev => Math.min(Math.max(0, prev), Math.max(0, viewport.width - stampPx)));
            setMarkerY(prev => Math.min(Math.max(0, prev), Math.max(0, viewport.height - stampPx)));

        } catch (err) {
            console.error('Error rendering PDF page:', err);
        }
    };

    useEffect(() => {
        if (pdfLoaded) {
            renderPage(pageNum, pageRotation, zoomScale);
        }
    }, [pageNum, pageRotation, zoomScale, pdfLoaded]);

    const changePage = (delta) => {
        const next = pageNum + delta;
        if (next >= 1 && next <= totalPage) {
            setPageNum(next);
        }
    };

    const rotatePage = () => {
        setPageRotation(r => (r + 90) % 360);
    };

    // Marker Dragging (Directly relative to canvas coordinate space)
    const startDragMarker = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDraggingMarker(true);

        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;

        dragStartRef.current = {
            mouseX: clientX,
            mouseY: clientY,
            markerX: markerX,
            markerY: markerY
        };
    };

    useEffect(() => {
        const doDrag = (e) => {
            if (!isDraggingMarker) return;
            e.preventDefault();

            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;

            const dx = clientX - dragStartRef.current.mouseX;
            const dy = clientY - dragStartRef.current.mouseY;

            const maxX = Math.max(0, canvasDims.width - markerSize);
            const maxY = Math.max(0, canvasDims.height - markerSize);

            const nextX = Math.min(Math.max(0, dragStartRef.current.markerX + dx), maxX);
            const nextY = Math.min(Math.max(0, dragStartRef.current.markerY + dy), maxY);

            setMarkerX(nextX);
            setMarkerY(nextY);
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
    }, [isDraggingMarker, canvasDims, markerSize]);

    const processSigning = async () => {
        if (!file) return;

        if (!formData.document_number || !formData.document_subject) {
            Swal.fire('Peringatan', 'Nomor dan Perihal Dokumen wajib diisi.', 'warning');
            return;
        }

        if (!formData.pdf_password) {
            Swal.fire('Peringatan', 'Password Parafrase wajib diisi untuk keamanan dokumen.', 'warning');
            return;
        }

        setIsProcessing(true);
        Swal.fire({
            title: 'Memproses Tanda Tangan',
            text: 'Silakan tunggu sebentar...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        try {
            const baseViewport = baseViewportRef.current;
            const pdfPointsWidth = baseViewport ? baseViewport.width : 595.28;
            const pdfPointsHeight = baseViewport ? baseViewport.height : 841.89;

            // Pixel-exact coordinate mapping from canvas coordinates to PDF millimeters (mm)
            const xPt = (markerX / Math.max(1, canvasDims.width)) * pdfPointsWidth;
            const yPt = (markerY / Math.max(1, canvasDims.height)) * pdfPointsHeight;

            const xMm = xPt * 0.352778;
            const yMm = yPt * 0.352778;

            const submitData = new FormData();
            submitData.append('pdf_file', file);
            submitData.append('x', xMm);
            submitData.append('y', yMm);
            submitData.append('page', pageNum);
            submitData.append('document_number', formData.document_number);
            submitData.append('document_subject', formData.document_subject);
            submitData.append('document_attachment', formData.document_attachment);
            submitData.append('signed_date', formData.signed_date);
            submitData.append('pdf_password', formData.pdf_password);
            submitData.append('show_qr_caption', formData.show_qr_caption ? '1' : '0');
            submitData.append('qr_caption_position', formData.qr_caption_position);

            const response = await fetch(route('sign.single.store'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: submitData
            });

            const result = await response.json();
            setIsProcessing(false);

            if (result.status === 'success') {
                Swal.fire({
                    title: 'Berhasil!',
                    html: `Dokumen berhasil ditandatangani secara elektronik.<br><br>Kode Verifikasi: <b>${result.verify_code}</b>`,
                    icon: 'success',
                    confirmButtonText: 'Unduh Hasil'
                }).then(() => {
                    window.open(result.file_path, '_blank');
                    reset();
                    router.get(route('history.index'));
                });
            } else {
                Swal.fire('Gagal', result.message || 'Gagal menandatangani PDF.', 'error');
            }
        } catch (err) {
            setIsProcessing(false);
            Swal.fire('Error', 'Terjadi kesalahan sistem: ' + err.message, 'error');
        }
    };

    const reset = () => {
        setPdfLoaded(false);
        setFile(null);
        setPageNum(1);
        setTotalPage(0);
        setPageRotation(0);
        pdfDocRef.current = null;
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-slate-800 dark:text-slate-200">Single Sign (Tanda Tangan Tunggal)</h2>}
        >
            <Head title="Single Sign" />

            <div className="py-3 md:py-5">
                <div className="mx-auto w-full max-w-7xl">
                    {!pdfLoaded ? (
                        <div className="bg-white dark:bg-gray-800 rounded-2xl p-8 border border-slate-200 dark:border-gray-700 shadow-sm max-w-2xl mx-auto text-center">
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
                                onDrop={(e) => {
                                    e.preventDefault();
                                    setIsDraggingFile(false);
                                    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                                        handleFile(e.dataTransfer.files[0]);
                                    }
                                }}
                                className={`border-2 border-dashed rounded-xl p-10 flex flex-col items-center justify-center cursor-pointer transition-colors ${
                                    isDraggingFile
                                        ? 'border-blue-500 bg-blue-50/50 dark:bg-blue-900/20'
                                        : 'border-slate-300 dark:border-gray-600 hover:border-blue-500'
                                }`}
                            >
                                <div className="w-14 h-14 rounded-2xl bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center mb-3">
                                    <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                    </svg>
                                </div>
                                <h3 className="text-base font-bold text-slate-800 dark:text-white">
                                    {isDraggingFile ? 'Lepaskan Berkas PDF di Sini' : 'Pilih atau Tarik Berkas PDF'}
                                </h3>
                                <p className="text-xs text-slate-500 mt-1">
                                    Maksimal ukuran file: {maxMb}MB. Format yang didukung: .pdf
                                </p>
                                <input type="file" accept="application/pdf" onChange={(e) => handleFile(e.target.files[0])} className="hidden" />
                            </label>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
                            {/* Left Settings Panel */}
                            <div className="xl:col-span-4 bg-white dark:bg-gray-800 p-5 rounded-2xl border border-slate-200 dark:border-gray-700 shadow-sm space-y-4">
                                <div className="border-b border-slate-100 dark:border-gray-700 pb-3">
                                    <h3 className="text-sm font-bold text-slate-800 dark:text-white">Informasi Dokumen</h3>
                                    <p className="text-xs text-slate-500 mt-0.5 truncate">{file?.name}</p>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Nomor Dokumen <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={formData.document_number}
                                        onChange={(e) => setFormData({ ...formData, document_number: e.target.value })}
                                        placeholder="Contoh: 001/SK/DIR/2026"
                                        className="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Perihal / Subjek <span className="text-red-500">*</span>
                                    </label>
                                    <textarea
                                        rows="2"
                                        value={formData.document_subject}
                                        onChange={(e) => setFormData({ ...formData, document_subject: e.target.value })}
                                        placeholder="Perihal surat atau keterangan dokumen"
                                        className="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Lampiran (Opsional)
                                    </label>
                                    <input
                                        type="text"
                                        value={formData.document_attachment}
                                        onChange={(e) => setFormData({ ...formData, document_attachment: e.target.value })}
                                        placeholder="Contoh: 1 Berkas"
                                        className="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Tanggal Penandatanganan
                                    </label>
                                    <input
                                        type="date"
                                        value={formData.signed_date}
                                        onChange={(e) => setFormData({ ...formData, signed_date: e.target.value })}
                                        className="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Password Parafrase PDF <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="password"
                                        value={formData.pdf_password}
                                        onChange={(e) => setFormData({ ...formData, pdf_password: e.target.value })}
                                        placeholder="Masukkan password untuk enkripsi PDF"
                                        className="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500"
                                    />
                                </div>

                                <div className="border-t border-slate-100 dark:border-gray-700 pt-3 space-y-2">
                                    <label className="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={formData.show_qr_caption}
                                            onChange={(e) => setFormData({ ...formData, show_qr_caption: e.target.checked })}
                                            className="rounded text-blue-600 focus:ring-blue-500"
                                        />
                                        <span className="text-xs font-semibold text-slate-700 dark:text-slate-300">Tampilkan Keterangan TTE</span>
                                    </label>

                                    {formData.show_qr_caption && (
                                        <div className="flex gap-4 pl-6 text-xs text-slate-600 dark:text-slate-300">
                                            <label className="flex items-center gap-1.5 cursor-pointer">
                                                <input
                                                    type="radio"
                                                    name="qr_pos"
                                                    checked={formData.qr_caption_position === 'bottom'}
                                                    onChange={() => setFormData({ ...formData, qr_caption_position: 'bottom' })}
                                                    className="text-blue-600"
                                                />
                                                Bawah QR
                                            </label>
                                            <label className="flex items-center gap-1.5 cursor-pointer">
                                                <input
                                                    type="radio"
                                                    name="qr_pos"
                                                    checked={formData.qr_caption_position === 'right'}
                                                    onChange={() => setFormData({ ...formData, qr_caption_position: 'right' })}
                                                    className="text-blue-600"
                                                />
                                                Samping QR
                                            </label>
                                        </div>
                                    )}
                                </div>

                                <div className="flex gap-2 pt-2">
                                    <button
                                        type="button"
                                        onClick={reset}
                                        className="flex-1 py-2 rounded-lg border border-slate-300 dark:border-gray-600 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-gray-700"
                                    >
                                        Ganti File
                                    </button>
                                    <button
                                        type="button"
                                        onClick={processSigning}
                                        disabled={isProcessing}
                                        className="flex-2 py-2 px-4 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold shadow transition-colors disabled:opacity-50"
                                    >
                                        {isProcessing ? 'Memproses...' : 'Tanda Tangani PDF'}
                                    </button>
                                </div>
                            </div>

                            {/* Right PDF Preview Canvas Workspace */}
                            <div className="xl:col-span-8 flex flex-col space-y-3">
                                {/* Top Viewport Toolbar */}
                                <div className="bg-white dark:bg-gray-800 p-2.5 rounded-xl border border-slate-200 dark:border-gray-700 shadow-xs flex flex-wrap items-center justify-between gap-3 text-xs">
                                    <div className="flex items-center gap-2">
                                        <button
                                            type="button"
                                            onClick={() => changePage(-1)}
                                            disabled={pageNum <= 1}
                                            className="px-2.5 py-1 rounded border border-slate-200 dark:border-gray-600 hover:bg-slate-50 dark:hover:bg-gray-700 disabled:opacity-30 font-medium"
                                        >
                                            ← Sebelumnya
                                        </button>
                                        <span className="font-semibold text-slate-700 dark:text-slate-200">
                                            Hal. {pageNum} / {totalPage}
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() => changePage(1)}
                                            disabled={pageNum >= totalPage}
                                            className="px-2.5 py-1 rounded border border-slate-200 dark:border-gray-600 hover:bg-slate-50 dark:hover:bg-gray-700 disabled:opacity-30 font-medium"
                                        >
                                            Berikutnya →
                                        </button>
                                    </div>

                                    <div className="flex items-center gap-3">
                                        {/* Rotate page button */}
                                        <button
                                            type="button"
                                            onClick={rotatePage}
                                            className="px-2.5 py-1 rounded bg-slate-100 dark:bg-gray-700 hover:bg-slate-200 dark:hover:bg-gray-600 text-slate-700 dark:text-slate-200 font-semibold flex items-center gap-1"
                                            title="Putar orientasi halaman 90 derajat"
                                        >
                                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                            </svg>
                                            Putar {pageRotation !== 0 ? `(${pageRotation}°)` : '90°'}
                                        </button>

                                        {/* Zoom Controls */}
                                        <div className="flex items-center gap-1 border-l border-slate-200 dark:border-gray-700 pl-3">
                                            <button
                                                type="button"
                                                onClick={() => setZoomScale(z => Math.max(0.7, z - 0.15))}
                                                className="w-6 h-6 rounded border border-slate-200 dark:border-gray-600 text-slate-600 dark:text-slate-300 font-bold flex items-center justify-center"
                                            >
                                                -
                                            </button>
                                            <span className="text-[11px] font-mono text-slate-500 w-10 text-center">
                                                {Math.round(zoomScale * 100)}%
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() => setZoomScale(z => Math.min(1.8, z + 0.15))}
                                                className="w-6 h-6 rounded border border-slate-200 dark:border-gray-600 text-slate-600 dark:text-slate-300 font-bold flex items-center justify-center"
                                            >
                                                +
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {/* Preview Workspace Scroll Container */}
                                <div
                                    ref={containerRef}
                                    className="relative w-full bg-slate-200/80 dark:bg-gray-900 p-4 rounded-2xl border border-slate-200 dark:border-gray-800 overflow-auto flex justify-center min-h-[500px]"
                                    style={{ maxHeight: 'calc(100vh - 12rem)' }}
                                >
                                    {/* Dedicated Canvas Viewport wrapper with EXACT 1:1 Canvas Size */}
                                    <div
                                        className="relative shadow-xl rounded-xs bg-white select-none overflow-visible my-auto"
                                        style={{
                                            width: `${canvasDims.width}px`,
                                            height: `${canvasDims.height}px`,
                                        }}
                                    >
                                        {/* PDF Canvas */}
                                        <canvas ref={canvasRef} className="block pointer-events-none" />

                                        {/* QR Marker Stamp (Bound precisely inside this canvas wrapper) */}
                                        <div
                                            onMouseDown={startDragMarker}
                                            onTouchStart={startDragMarker}
                                            style={{
                                                position: 'absolute',
                                                left: `${markerX}px`,
                                                top: `${markerY}px`,
                                                width: `${markerSize}px`,
                                                height: `${markerSize}px`,
                                                cursor: isDraggingMarker ? 'grabbing' : 'grab',
                                                touchAction: 'none',
                                                zIndex: 30,
                                            }}
                                            className="select-none group"
                                            title="Tahan dan geser ke posisi tanda tangan yang diinginkan"
                                        >
                                            {/* Outer bounding stamp */}
                                            <div className="w-full h-full border-2 border-blue-600 bg-white shadow-lg p-1 flex flex-col items-center justify-center rounded-xs transition-shadow group-hover:shadow-blue-500/30">
                                                {/* Simulated QR Pattern */}
                                                <svg
                                                    viewBox="0 0 29 29"
                                                    className="w-full h-full pointer-events-none"
                                                    shapeRendering="crispEdges"
                                                >
                                                    <rect width="29" height="29" fill="#ffffff" />
                                                    <rect x="1" y="1" width="7" height="7" fill="#111827" />
                                                    <rect x="2" y="2" width="5" height="5" fill="#ffffff" />
                                                    <rect x="3" y="3" width="3" height="3" fill="#111827" />
                                                    <rect x="21" y="1" width="7" height="7" fill="#111827" />
                                                    <rect x="22" y="2" width="5" height="5" fill="#ffffff" />
                                                    <rect x="23" y="3" width="3" height="3" fill="#111827" />
                                                    <rect x="1" y="21" width="7" height="7" fill="#111827" />
                                                    <rect x="2" y="22" width="5" height="5" fill="#ffffff" />
                                                    <rect x="3" y="23" width="3" height="3" fill="#111827" />
                                                    <rect x="10" y="2" width="2" height="2" fill="#111827" />
                                                    <rect x="13" y="2" width="1" height="2" fill="#111827" />
                                                    <rect x="16" y="3" width="2" height="1" fill="#111827" />
                                                    <rect x="10" y="6" width="1" height="2" fill="#111827" />
                                                    <rect x="12" y="5" width="3" height="2" fill="#111827" />
                                                    <rect x="16" y="6" width="2" height="2" fill="#111827" />
                                                    <rect x="9" y="10" width="2" height="2" fill="#111827" />
                                                    <rect x="12" y="10" width="1" height="3" fill="#111827" />
                                                    <rect x="14" y="11" width="3" height="1" fill="#111827" />
                                                    <rect x="18" y="10" width="2" height="2" fill="#111827" />
                                                    <rect x="21" y="11" width="3" height="2" fill="#111827" />
                                                    <rect x="25" y="10" width="1" height="2" fill="#111827" />
                                                    <rect x="10" y="14" width="3" height="2" fill="#111827" />
                                                    <rect x="14" y="15" width="2" height="2" fill="#111827" />
                                                    <rect x="17" y="14" width="1" height="3" fill="#111827" />
                                                    <rect x="20" y="15" width="3" height="1" fill="#111827" />
                                                    <rect x="24" y="14" width="2" height="2" fill="#111827" />
                                                    <rect x="10" y="18" width="2" height="1" fill="#111827" />
                                                    <rect x="13" y="19" width="3" height="2" fill="#111827" />
                                                    <rect x="17" y="18" width="2" height="2" fill="#111827" />
                                                    <rect x="20" y="19" width="1" height="2" fill="#111827" />
                                                    <rect x="22" y="18" width="3" height="2" fill="#111827" />
                                                    <rect x="10" y="23" width="1" height="3" fill="#111827" />
                                                    <rect x="12" y="24" width="2" height="2" fill="#111827" />
                                                    <rect x="15" y="22" width="2" height="3" fill="#111827" />
                                                    <rect x="18" y="24" width="3" height="2" fill="#111827" />
                                                    <rect x="22" y="23" width="2" height="2" fill="#111827" />
                                                    <rect x="25" y="25" width="2" height="2" fill="#111827" />
                                                </svg>
                                            </div>

                                            {/* Optional Caption Preview */}
                                            {formData.show_qr_caption && formData.qr_caption_position === 'bottom' && (
                                                <div
                                                    className="absolute left-1/2 -translate-x-1/2 pointer-events-none text-center leading-tight bg-white/95 backdrop-blur-xs p-1 rounded border border-slate-300 shadow-sm"
                                                    style={{
                                                        top: '100%',
                                                        marginTop: '3px',
                                                        width: `${Math.max(markerSize * 1.8, 120)}px`,
                                                    }}
                                                >
                                                    <p className="text-[8px] font-mono text-slate-500">ID : {auth?.user?.signature_prefix || 'DS'}-XXXXXX</p>
                                                    <p className="text-[8px] text-slate-700 font-semibold truncate">{auth?.user?.name}</p>
                                                </div>
                                            )}

                                            {formData.show_qr_caption && formData.qr_caption_position === 'right' && (
                                                <div
                                                    className="absolute top-1/2 -translate-y-1/2 pointer-events-none leading-tight bg-white/95 backdrop-blur-xs p-1 rounded border border-slate-300 shadow-sm whitespace-nowrap"
                                                    style={{
                                                        left: '100%',
                                                        marginLeft: '3px',
                                                    }}
                                                >
                                                    <p className="text-[8px] font-mono text-slate-500">ID : {auth?.user?.signature_prefix || 'DS'}-XXXXXX</p>
                                                    <p className="text-[8px] text-slate-700 font-semibold">{auth?.user?.name}</p>
                                                    {auth?.user?.position && <p className="text-[7px] text-slate-500">{auth?.user?.position}</p>}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
