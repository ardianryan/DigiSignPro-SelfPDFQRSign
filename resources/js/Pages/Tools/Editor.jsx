import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';
import { PDFDocument, rgb, StandardFonts } from 'pdf-lib';
import Swal from 'sweetalert2';

export default function VisualPdfEditor({ auth }) {
    const [file, setFile] = useState(null);
    const [pdfDocProxy, setPdfDocProxy] = useState(null);
    const [pageNum, setPageNum] = useState(1);
    const [totalPages, setTotalPages] = useState(0);
    const [scale, setScale] = useState(1.2);
    const [activeTool, setActiveTool] = useState('select'); // 'select', 'text', 'image', 'whiteout', 'draw'
    
    // Annotations State per page: { [pageNum]: [ { type: 'text'|'image'|'rect'|'draw', ... } ] }
    const [annotations, setAnnotations] = useState({});
    const [selectedAnnotationId, setSelectedAnnotationId] = useState(null);

    // Tool Configs
    const [textColor, setTextColor] = useState('#000000');
    const [textSize, setTextSize] = useState(16);
    const [textValue, setTextValue] = useState('Teks Baru');
    const [whiteoutColor, setWhiteoutColor] = useState('#ffffff');
    const [brushColor, setBrushColor] = useState('#000000');
    const [brushSize, setBrushSize] = useState(3);

    // Canvas & Drawing refs
    const canvasRef = useRef(null);
    const overlayRef = useRef(null);
    const [isDrawing, setIsDrawing] = useState(false);
    const [currentPath, setCurrentPath] = useState([]);
    const [isProcessing, setIsProcessing] = useState(false);

    // Dynamic PDF.js CDN loader
    useEffect(() => {
        if (window.pdfjsLib) return;

        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
        script.onload = () => {
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        };
        document.body.appendChild(script);
    }, []);

    // Load PDF
    const handleFileChange = async (e) => {
        const selected = e.target.files[0];
        if (!selected || selected.type !== 'application/pdf') {
            Swal.fire('Format Salah', 'Pilih file PDF yang valid.', 'warning');
            return;
        }

        if (!window.pdfjsLib) {
            Swal.fire('Memuat Modul', 'Modul PDF Viewer sedang diinisialisasi, silakan coba sebentar lagi.', 'info');
            return;
        }

        try {
            const buffer = await selected.arrayBuffer();
            const loadingTask = window.pdfjsLib.getDocument({ data: buffer });
            const doc = await loadingTask.promise;
            
            setFile(selected);
            setPdfDocProxy(doc);
            setTotalPages(doc.numPages);
            setPageNum(1);
            setAnnotations({});
            setSelectedAnnotationId(null);
        } catch (err) {
            console.error('PDF load error:', err);
            Swal.fire('Gagal', 'Tidak dapat memuat berkas PDF.', 'error');
        }
    };

    // Render current page to background canvas
    useEffect(() => {
        if (!pdfDocProxy || !canvasRef.current) return;

        let renderTask = null;
        const renderPage = async () => {
            try {
                const page = await pdfDocProxy.getPage(pageNum);
                const viewport = page.getViewport({ scale });
                const canvas = canvasRef.current;
                const ctx = canvas.getContext('2d');

                canvas.width = viewport.width;
                canvas.height = viewport.height;

                const renderContext = {
                    canvasContext: ctx,
                    viewport: viewport,
                };

                renderTask = page.render(renderContext);
                await renderTask.promise;
            } catch (err) {
                // Ignore cancellation exceptions
            }
        };

        renderPage();

        return () => {
            if (renderTask) renderTask.cancel();
        };
    }, [pdfDocProxy, pageNum, scale]);

    // Handle Canvas Click to Place Elements
    const handleOverlayClick = (e) => {
        if (!overlayRef.current) return;
        const rect = overlayRef.current.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        if (activeTool === 'text') {
            const newAnno = {
                id: `anno_${Date.now()}`,
                type: 'text',
                x,
                y,
                text: textValue,
                color: textColor,
                size: textSize,
            };
            addAnnotation(newAnno);
            setActiveTool('select');
        } else if (activeTool === 'whiteout') {
            const newAnno = {
                id: `anno_${Date.now()}`,
                type: 'rect',
                x,
                y,
                width: 140,
                height: 35,
                color: whiteoutColor,
            };
            addAnnotation(newAnno);
            setActiveTool('select');
        }
    };

    const addAnnotation = (anno) => {
        setAnnotations(prev => ({
            ...prev,
            [pageNum]: [...(prev[pageNum] || []), anno]
        }));
        setSelectedAnnotationId(anno.id);
    };

    const handleImageUpload = (e) => {
        const imgFile = e.target.files[0];
        if (!imgFile) return;

        const reader = new FileReader();
        reader.onload = (event) => {
            const img = new Image();
            img.onload = () => {
                const aspectRatio = img.width / img.height;
                const initialWidth = Math.min(180, img.width);
                const initialHeight = initialWidth / aspectRatio;

                const newAnno = {
                    id: `anno_${Date.now()}`,
                    type: 'image',
                    x: 60,
                    y: 60,
                    width: initialWidth,
                    height: initialHeight,
                    src: event.target.result,
                    originalFile: imgFile,
                };
                addAnnotation(newAnno);
                setActiveTool('select');
            };
            img.src = event.target.result;
        };
        reader.readAsDataURL(imgFile);
    };

    // Freehand Drawing Handlers
    const startDrawing = (e) => {
        if (activeTool !== 'draw' || !overlayRef.current) return;
        const rect = overlayRef.current.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        setIsDrawing(true);
        setCurrentPath([{ x, y }]);
    };

    const drawMove = (e) => {
        if (!isDrawing || activeTool !== 'draw' || !overlayRef.current) return;
        const rect = overlayRef.current.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        setCurrentPath(prev => [...prev, { x, y }]);
    };

    const stopDrawing = () => {
        if (!isDrawing || activeTool !== 'draw') return;
        setIsDrawing(false);

        if (currentPath.length > 1) {
            const newAnno = {
                id: `anno_${Date.now()}`,
                type: 'draw',
                path: currentPath,
                color: brushColor,
                size: brushSize,
            };
            addAnnotation(newAnno);
        }
        setCurrentPath([]);
    };

    const deleteSelectedAnnotation = () => {
        if (!selectedAnnotationId) return;
        setAnnotations(prev => ({
            ...prev,
            [pageNum]: (prev[pageNum] || []).filter(a => a.id !== selectedAnnotationId)
        }));
        setSelectedAnnotationId(null);
    };

    // Export & Bake Annotations using pdf-lib
    const handleExport = async () => {
        if (!file) return;

        setIsProcessing(true);
        try {
            const buffer = await file.arrayBuffer();
            const pdfDoc = await PDFDocument.load(buffer, { ignoreEncryption: true });
            const helveticaFont = await pdfDoc.embedFont(StandardFonts.Helvetica);
            const pdfPages = pdfDoc.getPages();

            // Iterate each page and apply annotations
            for (let p = 1; p <= pdfPages.length; p++) {
                const pageAnnos = annotations[p] || [];
                if (pageAnnos.length === 0) continue;

                const pdfPage = pdfPages[p - 1];
                const { width: pdfWidth, height: pdfHeight } = pdfPage.getSize();
                
                // Get rendered canvas dimensions for coordinate ratio translation
                const renderedWidth = canvasRef.current ? canvasRef.current.width : pdfWidth;
                const renderedHeight = canvasRef.current ? canvasRef.current.height : pdfHeight;
                const ratioX = pdfWidth / renderedWidth;
                const ratioY = pdfHeight / renderedHeight;

                for (const anno of pageAnnos) {
                    if (anno.type === 'text') {
                        const pdfX = anno.x * ratioX;
                        const pdfY = pdfHeight - (anno.y * ratioY) - (anno.size * ratioY);
                        
                        const r = parseInt(anno.color.slice(1, 3), 16) / 255;
                        const g = parseInt(anno.color.slice(3, 5), 16) / 255;
                        const b = parseInt(anno.color.slice(5, 7), 16) / 255;

                        pdfPage.drawText(anno.text, {
                            x: pdfX,
                            y: pdfY,
                            size: anno.size * ratioY,
                            font: helveticaFont,
                            color: rgb(r || 0, g || 0, b || 0),
                        });
                    } else if (anno.type === 'rect') {
                        const pdfX = anno.x * ratioX;
                        const pdfW = anno.width * ratioX;
                        const pdfH = anno.height * ratioY;
                        const pdfY = pdfHeight - (anno.y * ratioY) - pdfH;

                        const r = parseInt(anno.color.slice(1, 3), 16) / 255;
                        const g = parseInt(anno.color.slice(3, 5), 16) / 255;
                        const b = parseInt(anno.color.slice(5, 7), 16) / 255;

                        pdfPage.drawRectangle({
                            x: pdfX,
                            y: pdfY,
                            width: pdfW,
                            height: pdfH,
                            color: rgb(r, g, b),
                        });
                    } else if (anno.type === 'image') {
                        let embeddedImg;
                        const imgBuffer = await anno.originalFile.arrayBuffer();
                        if (anno.originalFile.type === 'image/png') {
                            embeddedImg = await pdfDoc.embedPng(imgBuffer);
                        } else {
                            embeddedImg = await pdfDoc.embedJpg(imgBuffer);
                        }

                        const pdfX = anno.x * ratioX;
                        const pdfW = anno.width * ratioX;
                        const pdfH = anno.height * ratioY;
                        const pdfY = pdfHeight - (anno.y * ratioY) - pdfH;

                        pdfPage.drawImage(embeddedImg, {
                            x: pdfX,
                            y: pdfY,
                            width: pdfW,
                            height: pdfH,
                        });
                    }
                }
            }

            const pdfBytes = await pdfDoc.save();
            const blob = new Blob([pdfBytes], { type: 'application/pdf' });
            const url = URL.createObjectURL(blob);

            const a = document.createElement('a');
            a.href = url;
            a.download = `Edited_${file.name}`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            // Silent telemetry
            fetch(route('tools.track_usage'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ tool: 'editor', files_count: 1 }),
            }).catch(() => {});

            Swal.fire({
                title: 'Berhasil Disimpan',
                text: 'Perubahan teks, gambar, dan stempel telah diterapkan ke dalam berkas PDF.',
                icon: 'success',
                confirmButtonColor: '#2563eb',
            });
        } catch (err) {
            console.error('Export error:', err);
            Swal.fire('Gagal Menyimpan', 'Terjadi kesalahan saat menyimpan perubahan.', 'error');
        } finally {
            setIsProcessing(false);
        }
    };

    const currentPageAnnos = annotations[pageNum] || [];

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <h2 className="text-lg font-bold text-slate-800 dark:text-slate-200">Visual PDF Editor</h2>
                    </div>
                    <Link href={route('dashboard')} className="text-xs text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Dashboard
                    </Link>
                </div>
            }
        >
            <Head title="Visual PDF Editor" />

            <div className="py-4">
                <div className="mx-auto max-w-7xl">
                    {!file ? (
                        <div className="bg-white dark:bg-gray-800 rounded-2xl p-10 border border-slate-200 dark:border-gray-700 shadow-xs text-center max-w-2xl mx-auto">
                            <label className="border-2 border-dashed border-slate-300 dark:border-gray-600 hover:border-blue-500 rounded-xl p-10 flex flex-col items-center justify-center cursor-pointer transition-colors">
                                <div className="w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center mb-3">
                                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                    </svg>
                                </div>
                                <h3 className="text-sm font-bold text-slate-800 dark:text-white">
                                    Pilih Berkas PDF untuk Diedit
                                </h3>
                                <p className="text-xs text-slate-500 mt-1 max-w-md">
                                    Tambahkan teks baru, tempel gambar stempel/tanda tangan, tutup teks lama (whiteout), atau buat coretan.
                                </p>
                                <input type="file" accept="application/pdf" onChange={handleFileChange} className="hidden" />
                            </label>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {/* Top Toolbar */}
                            <div className="bg-white dark:bg-gray-800 rounded-xl p-3.5 border border-slate-200 dark:border-gray-700 shadow-xs flex flex-wrap items-center justify-between gap-3">
                                {/* Tool Selection */}
                                <div className="flex flex-wrap items-center gap-1.5 bg-slate-100 dark:bg-gray-700 p-1 rounded-lg">
                                    <button
                                        type="button"
                                        onClick={() => setActiveTool('select')}
                                        className={`px-3 py-1.5 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-colors ${
                                            activeTool === 'select' ? 'bg-white dark:bg-gray-800 text-blue-600 dark:text-blue-400 shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
                                        }`}
                                    >
                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"></path>
                                        </svg>
                                        Pilih
                                    </button>

                                    <button
                                        type="button"
                                        onClick={() => setActiveTool('text')}
                                        className={`px-3 py-1.5 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-colors ${
                                            activeTool === 'text' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
                                        }`}
                                    >
                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        Teks
                                    </button>

                                    <label className={`px-3 py-1.5 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-colors cursor-pointer ${
                                        activeTool === 'image' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
                                    }`}>
                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        Gambar
                                        <input type="file" accept="image/*" onChange={handleImageUpload} className="hidden" />
                                    </label>

                                    <button
                                        type="button"
                                        onClick={() => setActiveTool('whiteout')}
                                        className={`px-3 py-1.5 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-colors ${
                                            activeTool === 'whiteout' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
                                        }`}
                                    >
                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                        </svg>
                                        Whiteout
                                    </button>

                                    <button
                                        type="button"
                                        onClick={() => setActiveTool('draw')}
                                        className={`px-3 py-1.5 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-colors ${
                                            activeTool === 'draw' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
                                        }`}
                                    >
                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                        </svg>
                                        Coretan
                                    </button>
                                </div>

                                {/* Page Navigator & Zoom Controls */}
                                <div className="flex items-center gap-2 text-xs">
                                    <button
                                        type="button"
                                        onClick={() => setPageNum(p => Math.max(1, p - 1))}
                                        disabled={pageNum <= 1}
                                        className="p-1 rounded-md border border-slate-200 dark:border-gray-600 hover:bg-slate-50 dark:hover:bg-gray-700 disabled:opacity-30"
                                    >
                                        <svg className="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7"></path>
                                        </svg>
                                    </button>
                                    <span className="font-medium text-slate-700 dark:text-slate-200">
                                        Hal. {pageNum} / {totalPages}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => setPageNum(p => Math.min(totalPages, p + 1))}
                                        disabled={pageNum >= totalPages}
                                        className="p-1 rounded-md border border-slate-200 dark:border-gray-600 hover:bg-slate-50 dark:hover:bg-gray-700 disabled:opacity-30"
                                    >
                                        <svg className="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </button>

                                    <div className="h-4 w-px bg-slate-200 dark:bg-gray-700 mx-1"></div>

                                    <button
                                        type="button"
                                        onClick={() => setScale(s => Math.max(0.8, s - 0.2))}
                                        className="w-6 h-6 rounded-md border border-slate-200 dark:border-gray-600 text-slate-600 dark:text-slate-300 font-bold flex items-center justify-center"
                                    >
                                        -
                                    </button>
                                    <span className="text-[11px] text-slate-500 font-mono">
                                        {Math.round(scale * 100)}%
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => setScale(s => Math.min(2.0, s + 0.2))}
                                        className="w-6 h-6 rounded-md border border-slate-200 dark:border-gray-600 text-slate-600 dark:text-slate-300 font-bold flex items-center justify-center"
                                    >
                                        +
                                    </button>
                                </div>

                                {/* Action Buttons */}
                                <div className="flex items-center gap-2">
                                    {selectedAnnotationId && (
                                        <button
                                            type="button"
                                            onClick={deleteSelectedAnnotation}
                                            className="px-3 py-1.5 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 text-xs font-medium border border-red-200"
                                        >
                                            Hapus Elemen
                                        </button>
                                    )}

                                    <button
                                        type="button"
                                        onClick={handleExport}
                                        disabled={isProcessing}
                                        className="px-4 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs disabled:opacity-50 transition-colors flex items-center gap-1.5"
                                    >
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                        </svg>
                                        {isProcessing ? 'Menyimpan...' : 'Simpan PDF'}
                                    </button>
                                </div>
                            </div>

                            {/* Tool Options */}
                            {activeTool === 'text' && (
                                <div className="bg-slate-50 dark:bg-gray-800 border border-slate-200 dark:border-gray-700 p-3 rounded-xl flex flex-wrap items-center gap-3 text-xs">
                                    <span className="font-semibold text-slate-700 dark:text-slate-200">Pengaturan Teks:</span>
                                    <input
                                        type="text"
                                        value={textValue}
                                        onChange={(e) => setTextValue(e.target.value)}
                                        placeholder="Ketik teks..."
                                        className="border border-slate-300 dark:border-gray-600 rounded-md px-2.5 py-1 text-xs w-56 dark:bg-gray-700 dark:text-white"
                                    />
                                    <div className="flex items-center gap-1.5">
                                        <span>Ukuran:</span>
                                        <input
                                            type="number"
                                            value={textSize}
                                            onChange={(e) => setTextSize(Number(e.target.value))}
                                            className="border border-slate-300 dark:border-gray-600 rounded-md px-2 py-1 text-xs w-14 text-center dark:bg-gray-700 dark:text-white"
                                            min="8"
                                            max="72"
                                        />
                                    </div>
                                    <div className="flex items-center gap-1.5">
                                        <span>Warna:</span>
                                        <input
                                            type="color"
                                            value={textColor}
                                            onChange={(e) => setTextColor(e.target.value)}
                                            className="w-6 h-6 rounded border border-slate-300 dark:border-gray-600 cursor-pointer"
                                        />
                                    </div>
                                    <span className="text-[11px] text-slate-500">
                                        Klik pada dokumen untuk menempatkan teks.
                                    </span>
                                </div>
                            )}

                            {activeTool === 'whiteout' && (
                                <div className="bg-slate-50 dark:bg-gray-800 border border-slate-200 dark:border-gray-700 p-3 rounded-xl flex flex-wrap items-center gap-3 text-xs">
                                    <span className="font-semibold text-slate-700 dark:text-slate-200">Mode Penutup:</span>
                                    <button
                                        type="button"
                                        onClick={() => setWhiteoutColor('#ffffff')}
                                        className={`px-2.5 py-1 rounded-md border text-xs font-medium ${
                                            whiteoutColor === '#ffffff' ? 'bg-white text-slate-900 border-slate-400 shadow-xs' : 'bg-transparent text-slate-600'
                                        }`}
                                    >
                                        Whiteout (Putih)
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setWhiteoutColor('#000000')}
                                        className={`px-2.5 py-1 rounded-md border text-xs font-medium ${
                                            whiteoutColor === '#000000' ? 'bg-slate-900 text-white border-slate-900 shadow-xs' : 'bg-transparent text-slate-600'
                                        }`}
                                    >
                                        Blackout (Sensor Hitam)
                                    </button>
                                    <span className="text-[11px] text-slate-500">
                                        Klik pada dokumen untuk menempatkan penutup.
                                    </span>
                                </div>
                            )}

                            {/* Main Canvas Viewer */}
                            <div className="bg-slate-100 dark:bg-gray-900 rounded-2xl p-6 overflow-auto flex justify-center min-h-[600px] border border-slate-200 dark:border-gray-800">
                                <div className="relative shadow-md rounded-md bg-white overflow-hidden select-none">
                                    {/* PDF Page Background Canvas */}
                                    <canvas ref={canvasRef} className="block" />

                                    {/* Interactive Overlay Layer for Annotations */}
                                    <div
                                        ref={overlayRef}
                                        onClick={handleOverlayClick}
                                        onMouseDown={startDrawing}
                                        onMouseMove={drawMove}
                                        onMouseUp={stopDrawing}
                                        className={`absolute inset-0 ${
                                            activeTool === 'text' ? 'cursor-text' :
                                            activeTool === 'whiteout' ? 'cursor-crosshair' :
                                            activeTool === 'draw' ? 'cursor-crosshair' : 'cursor-default'
                                        }`}
                                    >
                                        {/* Render Page Annotations */}
                                        {currentPageAnnos.map((anno) => {
                                            const isSelected = selectedAnnotationId === anno.id;

                                            if (anno.type === 'text') {
                                                return (
                                                    <div
                                                        key={anno.id}
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            setSelectedAnnotationId(anno.id);
                                                        }}
                                                        style={{
                                                            position: 'absolute',
                                                            left: `${anno.x}px`,
                                                            top: `${anno.y}px`,
                                                            color: anno.color,
                                                            fontSize: `${anno.size}px`,
                                                            fontFamily: 'Helvetica, Arial, sans-serif',
                                                            fontWeight: 'bold',
                                                        }}
                                                        className={`p-1 rounded cursor-move transition-all ${
                                                            isSelected ? 'ring-2 ring-blue-500 bg-blue-50/50' : 'hover:ring-1 hover:ring-slate-400'
                                                        }`}
                                                    >
                                                        {anno.text}
                                                    </div>
                                                );
                                            }

                                            if (anno.type === 'rect') {
                                                return (
                                                    <div
                                                        key={anno.id}
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            setSelectedAnnotationId(anno.id);
                                                        }}
                                                        style={{
                                                            position: 'absolute',
                                                            left: `${anno.x}px`,
                                                            top: `${anno.y}px`,
                                                            width: `${anno.width}px`,
                                                            height: `${anno.height}px`,
                                                            backgroundColor: anno.color,
                                                        }}
                                                        className={`cursor-move rounded-xs ${
                                                            isSelected ? 'ring-2 ring-blue-500' : 'hover:ring-1 hover:ring-slate-400'
                                                        }`}
                                                    />
                                                );
                                            }

                                            if (anno.type === 'image') {
                                                return (
                                                    <div
                                                        key={anno.id}
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            setSelectedAnnotationId(anno.id);
                                                        }}
                                                        style={{
                                                            position: 'absolute',
                                                            left: `${anno.x}px`,
                                                            top: `${anno.y}px`,
                                                            width: `${anno.width}px`,
                                                            height: `${anno.height}px`,
                                                        }}
                                                        className={`cursor-move p-0.5 rounded ${
                                                            isSelected ? 'ring-2 ring-blue-500 shadow-sm' : 'hover:ring-1 hover:ring-slate-400'
                                                        }`}
                                                    >
                                                        <img src={anno.src} alt="Uploaded Stamp" className="w-full h-full object-contain pointer-events-none" />
                                                    </div>
                                                );
                                            }

                                            return null;
                                        })}

                                        {/* Freehand drawing active stroke preview */}
                                        {isDrawing && currentPath.length > 1 && (
                                            <svg className="absolute inset-0 w-full h-full pointer-events-none">
                                                <polyline
                                                    points={currentPath.map(p => `${p.x},${p.y}`).join(' ')}
                                                    fill="none"
                                                    stroke={brushColor}
                                                    strokeWidth={brushSize}
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                />
                                            </svg>
                                        )}
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
