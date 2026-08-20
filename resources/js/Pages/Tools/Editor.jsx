import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';
import { PDFDocument, rgb, StandardFonts } from 'pdf-lib';
import Swal from 'sweetalert2';

// Supported Fonts Palette
const AVAILABLE_FONTS = [
    { id: 'Helvetica', name: 'Arial / Helvetica (Sans-Serif)', css: 'Arial, Helvetica, "Segoe UI", sans-serif', pdf: 'Helvetica' },
    { id: 'TimesRoman', name: 'Times New Roman (Serif Resmi)', css: '"Times New Roman", Times, Georgia, serif', pdf: 'TimesRoman' },
    { id: 'Courier', name: 'Courier New (Monospace)', css: '"Courier New", Courier, Consolas, monospace', pdf: 'Courier' },
    { id: 'Georgia', name: 'Georgia (Serif Klasik)', css: 'Georgia, "Times New Roman", serif', pdf: 'TimesRoman' },
    { id: 'Trebuchet', name: 'Trebuchet MS (Modern)', css: '"Trebuchet MS", "Lucida Sans Unicode", sans-serif', pdf: 'Helvetica' },
    { id: 'Verdana', name: 'Verdana (Legible Sans)', css: 'Verdana, Geneva, sans-serif', pdf: 'Helvetica' },
    { id: 'Calibri', name: 'Calibri / Carlito', css: 'Calibri, Carlito, "Open Sans", sans-serif', pdf: 'Helvetica' },
    { id: 'Roboto', name: 'Roboto / Inter', css: 'Roboto, Inter, -apple-system, sans-serif', pdf: 'Helvetica' },
];

export default function VisualPdfEditor({ auth }) {
    const [file, setFile] = useState(null);
    const [pdfDocProxy, setPdfDocProxy] = useState(null);
    const [pageNum, setPageNum] = useState(1);
    const [totalPages, setTotalPages] = useState(0);
    const [scale, setScale] = useState(1.25);
    const [activeTool, setActiveTool] = useState('select'); // 'select', 'edit-pdf-text', 'text', 'image', 'whiteout', 'draw'
    
    // Annotations per page: { [pageNum]: [ { id, type, x, y, width, height, text, color, size, font, isBold, isItalic } ] }
    const [annotations, setAnnotations] = useState({});
    const [selectedAnnotationId, setSelectedAnnotationId] = useState(null);
    const [editingAnnotationId, setEditingAnnotationId] = useState(null);

    // Text items extracted by PDF.js with precise typography metrics
    const [pdfTextItems, setPdfTextItems] = useState([]);

    // Toolbar settings
    const [textColor, setTextColor] = useState('#000000');
    const [textSize, setTextSize] = useState(12);
    const [textFont, setTextFont] = useState('Helvetica');
    const [textBold, setTextBold] = useState(false);
    const [textItalic, setTextItalic] = useState(false);
    const [whiteoutColor, setWhiteoutColor] = useState('#ffffff');
    const [brushColor, setBrushColor] = useState('#2563eb');
    const [brushSize, setBrushSize] = useState(3);

    // Refs
    const canvasRef = useRef(null);
    const workspaceRef = useRef(null);
    const [isDrawing, setIsDrawing] = useState(false);
    const [currentPath, setCurrentPath] = useState([]);
    const [isProcessing, setIsProcessing] = useState(false);
    const [isDraggingFile, setIsDraggingFile] = useState(false);

    // Dragging element state (strictly only active when activeTool === 'select')
    const [draggingElementId, setDraggingElementId] = useState(null);
    const dragStartPos = useRef({ x: 0, y: 0 });
    const elementStartPos = useRef({ x: 0, y: 0 });

    // Dynamic PDF.js loader
    useEffect(() => {
        if (window.pdfjsLib) return;

        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
        script.onload = () => {
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        };
        document.body.appendChild(script);
    }, []);

    const switchTool = (tool) => {
        setActiveTool(tool);
        setSelectedAnnotationId(null);
        setEditingAnnotationId(null);
    };

    // Load PDF
    const processPdfFile = async (selected) => {
        if (!selected || (selected.type !== 'application/pdf' && !selected.name.toLowerCase().endsWith('.pdf'))) {
            Swal.fire('Format Salah', 'Pilih file PDF yang valid.', 'warning');
            return;
        }

        if (!window.pdfjsLib) {
            Swal.fire('Memuat Modul', 'Modul PDF Viewer sedang diinisialisasi, silakan coba lagi.', 'info');
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
            setEditingAnnotationId(null);
        } catch (err) {
            console.error('PDF load error:', err);
            Swal.fire('Gagal', 'Tidak dapat memuat berkas PDF.', 'error');
        }
    };

    const handleFileChange = (e) => {
        if (e.target.files && e.target.files[0]) {
            processPdfFile(e.target.files[0]);
        }
    };

    const handleDrop = (e) => {
        e.preventDefault();
        setIsDraggingFile(false);
        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            processPdfFile(e.dataTransfer.files[0]);
        }
    };

    // Render PDF page & Extract exact text bounding boxes with PDF.js font styles
    useEffect(() => {
        if (!pdfDocProxy || !canvasRef.current) return;

        let renderTask = null;
        let isCancelled = false;

        const renderPage = async () => {
            try {
                const page = await pdfDocProxy.getPage(pageNum);
                if (isCancelled) return;

                const viewport = page.getViewport({ scale });
                const canvas = canvasRef.current;
                const ctx = canvas.getContext('2d');

                canvas.width = viewport.width;
                canvas.height = viewport.height;

                renderTask = page.render({
                    canvasContext: ctx,
                    viewport: viewport,
                });
                await renderTask.promise;
                if (isCancelled) return;

                // Extract text items and true font styles from PDF dictionary
                const textContent = await page.getTextContent();
                const styles = textContent.styles || {};
                const items = [];

                for (const item of textContent.items) {
                    if (!item.str || item.str.trim() === '') continue;

                    const tx = window.pdfjsLib.Util.transform(viewport.transform, item.transform);
                    const itemFontSize = Math.hypot(tx[0], tx[1]);
                    const itemWidth = item.width * scale;
                    
                    // True font name from style registry
                    const fontStyle = styles[item.fontName] || {};
                    const rawFontName = (fontStyle.fontFamily || item.fontName || '').toLowerCase();
                    const ascent = fontStyle.ascent || 0.82;
                    
                    // Pixel-exact top Y coordinate calculated from PDF baseline
                    const x = tx[4];
                    const y = tx[5] - (itemFontSize * ascent);
                    const height = itemFontSize * 1.15;

                    // Detect font family
                    let detectedFont = 'Helvetica';
                    if (rawFontName.includes('times') || rawFontName.includes('serif') || rawFontName.includes('roman') || rawFontName.includes('cambria') || rawFontName.includes('garamond') || rawFontName.includes('minion')) {
                        detectedFont = 'TimesRoman';
                    } else if (rawFontName.includes('georgia')) {
                        detectedFont = 'Georgia';
                    } else if (rawFontName.includes('courier') || rawFontName.includes('mono') || rawFontName.includes('consolas') || rawFontName.includes('typewriter')) {
                        detectedFont = 'Courier';
                    } else if (rawFontName.includes('trebuchet')) {
                        detectedFont = 'Trebuchet';
                    } else if (rawFontName.includes('verdana')) {
                        detectedFont = 'Verdana';
                    } else if (rawFontName.includes('calibri') || rawFontName.includes('carlito')) {
                        detectedFont = 'Calibri';
                    } else if (rawFontName.includes('roboto') || rawFontName.includes('inter')) {
                        detectedFont = 'Roboto';
                    }

                    // Detect bold & italic
                    const isBold = rawFontName.includes('bold') || rawFontName.includes('black') || rawFontName.includes('heavy') || rawFontName.includes('semibold') || rawFontName.includes('700') || rawFontName.includes('800') || rawFontName.includes('900');
                    const isItalic = rawFontName.includes('italic') || rawFontName.includes('oblique') || rawFontName.includes('slanted');

                    items.push({
                        str: item.str,
                        x: Math.max(0, x),
                        y: Math.max(0, y),
                        width: Math.max(8, itemWidth),
                        height: Math.max(10, height),
                        fontSize: Math.round(itemFontSize),
                        fontName: item.fontName,
                        detectedFont,
                        isBold,
                        isItalic,
                    });
                }

                setPdfTextItems(items);
            } catch (err) {
                // Ignore cancellations
            }
        };

        renderPage();

        return () => {
            isCancelled = true;
            if (renderTask) renderTask.cancel();
        };
    }, [pdfDocProxy, pageNum, scale]);

    // Click on PDF Text to Edit Directly (Foxit-like Pixel-Exact In-Place Replacement)
    const handlePdfTextClick = (item, e) => {
        if (activeTool !== 'edit-pdf-text' && activeTool !== 'select') return;

        e.stopPropagation();
        
        // Auto-match toolbar font & styles
        setTextFont(item.detectedFont);
        setTextSize(item.fontSize);
        setTextBold(item.isBold);
        setTextItalic(item.isItalic);

        // 1. Precise whiteout patch covering the original text
        const whiteoutId = `rect_auto_${Date.now()}`;
        const whiteoutAnno = {
            id: whiteoutId,
            type: 'rect',
            x: Math.max(0, item.x - 1),
            y: Math.max(0, item.y - 1),
            width: item.width + 3,
            height: item.height + 2,
            color: '#ffffff',
        };

        // 2. Editable text annotation with exact position, zero displacement
        const textId = `text_edit_${Date.now() + 1}`;
        const textAnno = {
            id: textId,
            type: 'text',
            x: item.x,
            y: item.y,
            text: item.str,
            color: textColor,
            size: item.fontSize,
            font: item.detectedFont,
            isBold: item.isBold,
            isItalic: item.isItalic,
            width: Math.max(item.width + 10, 80),
            height: item.height,
        };

        setAnnotations(prev => ({
            ...prev,
            [pageNum]: [...(prev[pageNum] || []), whiteoutAnno, textAnno]
        }));

        setSelectedAnnotationId(textId);
        setEditingAnnotationId(textId);
    };

    // Click on empty workspace area
    const handleWorkspaceClick = (e) => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.closest('.annotation-item') || e.target.closest('.pdf-text-span')) {
            return;
        }

        if (!workspaceRef.current) return;
        const rect = workspaceRef.current.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const clickY = e.clientY - rect.top;

        if (activeTool === 'text') {
            const newId = `text_${Date.now()}`;
            const newAnno = {
                id: newId,
                type: 'text',
                x: clickX,
                y: clickY,
                text: 'Ketik teks di sini...',
                color: textColor,
                size: textSize,
                font: textFont,
                isBold: textBold,
                isItalic: textItalic,
                width: 200,
                height: Math.max(24, textSize + 8),
            };
            addAnnotation(newAnno);
            setSelectedAnnotationId(newId);
            setEditingAnnotationId(newId);
        } else if (activeTool === 'whiteout') {
            const newId = `rect_${Date.now()}`;
            const newAnno = {
                id: newId,
                type: 'rect',
                x: clickX - 50,
                y: clickY - 12,
                width: 120,
                height: 24,
                color: whiteoutColor,
            };
            addAnnotation(newAnno);
            setSelectedAnnotationId(newId);
        } else {
            setEditingAnnotationId(null);
            setSelectedAnnotationId(null);
        }
    };

    const addAnnotation = (anno) => {
        setAnnotations(prev => ({
            ...prev,
            [pageNum]: [...(prev[pageNum] || []), anno]
        }));
    };

    const updateAnnotation = (id, fields) => {
        setAnnotations(prev => ({
            ...prev,
            [pageNum]: (prev[pageNum] || []).map(a => a.id === id ? { ...a, ...fields } : a)
        }));
    };

    // Insert Stamp Image
    const handleImageUpload = (e) => {
        const imgFile = e.target.files[0];
        if (!imgFile) return;

        const reader = new FileReader();
        reader.onload = (event) => {
            const img = new Image();
            img.onload = () => {
                const aspectRatio = img.width / img.height;
                const initialWidth = Math.min(160, img.width);
                const initialHeight = initialWidth / aspectRatio;

                const newId = `img_${Date.now()}`;
                const newAnno = {
                    id: newId,
                    type: 'image',
                    x: 80,
                    y: 80,
                    width: initialWidth,
                    height: initialHeight,
                    src: event.target.result,
                    originalFile: imgFile,
                };
                addAnnotation(newAnno);
                setSelectedAnnotationId(newId);
            };
            img.src = event.target.result;
        };
        reader.readAsDataURL(imgFile);
        e.target.value = '';
    };

    // Element Dragging - STRICTLY only allowed when activeTool === 'select'
    const handleElementMouseDown = (e, anno) => {
        if (activeTool !== 'select') return;
        if (editingAnnotationId === anno.id) return;
        
        e.stopPropagation();
        setSelectedAnnotationId(anno.id);
        setDraggingElementId(anno.id);
        dragStartPos.current = { x: e.clientX, y: e.clientY };
        elementStartPos.current = { x: anno.x, y: anno.y };
    };

    useEffect(() => {
        const handleMouseMove = (e) => {
            if (!draggingElementId || activeTool !== 'select') return;

            const dx = e.clientX - dragStartPos.current.x;
            const dy = e.clientY - dragStartPos.current.y;

            updateAnnotation(draggingElementId, {
                x: Math.max(0, elementStartPos.current.x + dx),
                y: Math.max(0, elementStartPos.current.y + dy),
            });
        };

        const handleMouseUp = () => {
            setDraggingElementId(null);
        };

        if (draggingElementId) {
            window.addEventListener('mousemove', handleMouseMove);
            window.addEventListener('mouseup', handleMouseUp);
        }

        return () => {
            window.removeEventListener('mousemove', handleMouseMove);
            window.removeEventListener('mouseup', handleMouseUp);
        };
    }, [draggingElementId, activeTool, pageNum]);

    // Freehand Drawing Handlers
    const startDrawing = (e) => {
        if (activeTool !== 'draw' || !workspaceRef.current) return;
        const rect = workspaceRef.current.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        setIsDrawing(true);
        setCurrentPath([{ x, y }]);
    };

    const drawMove = (e) => {
        if (!isDrawing || activeTool !== 'draw' || !workspaceRef.current) return;
        const rect = workspaceRef.current.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        setCurrentPath(prev => [...prev, { x, y }]);
    };

    const stopDrawing = () => {
        if (!isDrawing || activeTool !== 'draw') return;
        setIsDrawing(false);

        if (currentPath.length > 1) {
            const newAnno = {
                id: `draw_${Date.now()}`,
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
        setEditingAnnotationId(null);
    };

    // Get CSS font family string
    const getCssFontFamily = (fontId) => {
        const found = AVAILABLE_FONTS.find(f => f.id === fontId);
        return found ? found.css : 'Arial, Helvetica, sans-serif';
    };

    // Export PDF with pdf-lib
    const handleExport = async () => {
        if (!file) return;

        setIsProcessing(true);
        try {
            const buffer = await file.arrayBuffer();
            const pdfDoc = await PDFDocument.load(buffer, { ignoreEncryption: true });
            
            const fontHelvetica = await pdfDoc.embedFont(StandardFonts.Helvetica);
            const fontHelveticaBold = await pdfDoc.embedFont(StandardFonts.HelveticaBold);
            const fontHelveticaOblique = await pdfDoc.embedFont(StandardFonts.HelveticaOblique);
            const fontHelveticaBoldOblique = await pdfDoc.embedFont(StandardFonts.HelveticaBoldOblique);

            const fontTimes = await pdfDoc.embedFont(StandardFonts.TimesRoman);
            const fontTimesBold = await pdfDoc.embedFont(StandardFonts.TimesRomanBold);
            const fontTimesItalic = await pdfDoc.embedFont(StandardFonts.TimesRomanItalic);
            const fontTimesBoldItalic = await pdfDoc.embedFont(StandardFonts.TimesRomanBoldItalic);

            const fontCourier = await pdfDoc.embedFont(StandardFonts.Courier);
            const fontCourierBold = await pdfDoc.embedFont(StandardFonts.CourierBold);

            const pdfPages = pdfDoc.getPages();

            for (let p = 1; p <= pdfPages.length; p++) {
                const pageAnnos = annotations[p] || [];
                if (pageAnnos.length === 0) continue;

                const pdfPage = pdfPages[p - 1];
                const { width: pdfWidth, height: pdfHeight } = pdfPage.getSize();
                
                const renderedWidth = canvasRef.current ? canvasRef.current.width : pdfWidth;
                const renderedHeight = canvasRef.current ? canvasRef.current.height : pdfHeight;
                const ratioX = pdfWidth / renderedWidth;
                const ratioY = pdfHeight / renderedHeight;

                for (const anno of pageAnnos) {
                    if (anno.type === 'text') {
                        const pdfX = anno.x * ratioX;
                        const fontSizeInPdf = anno.size * ratioY;
                        // Calculate exact Y baseline in PDF coordinate system
                        const pdfY = pdfHeight - (anno.y * ratioY) - (fontSizeInPdf * 0.82);

                        let selectedFont = fontHelvetica;
                        const fontObj = AVAILABLE_FONTS.find(f => f.id === anno.font);
                        const pdfFamily = fontObj ? fontObj.pdf : 'Helvetica';

                        if (pdfFamily === 'TimesRoman') {
                            if (anno.isBold && anno.isItalic) selectedFont = fontTimesBoldItalic;
                            else if (anno.isBold) selectedFont = fontTimesBold;
                            else if (anno.isItalic) selectedFont = fontTimesItalic;
                            else selectedFont = fontTimes;
                        } else if (pdfFamily === 'Courier') {
                            selectedFont = anno.isBold ? fontCourierBold : fontCourier;
                        } else {
                            if (anno.isBold && anno.isItalic) selectedFont = fontHelveticaBoldOblique;
                            else if (anno.isBold) selectedFont = fontHelveticaBold;
                            else if (anno.isItalic) selectedFont = fontHelveticaOblique;
                            else selectedFont = fontHelvetica;
                        }

                        const r = parseInt(anno.color.slice(1, 3), 16) / 255;
                        const g = parseInt(anno.color.slice(3, 5), 16) / 255;
                        const b = parseInt(anno.color.slice(5, 7), 16) / 255;

                        const lines = (anno.text || '').split('\n');
                        lines.forEach((lineText, lIdx) => {
                            if (!lineText) return;
                            pdfPage.drawText(lineText, {
                                x: pdfX,
                                y: pdfY - (lIdx * fontSizeInPdf * 1.15),
                                size: fontSizeInPdf,
                                font: selectedFont,
                                color: rgb(r || 0, g || 0, b || 0),
                            });
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
                title: 'Berhasil Disimpan!',
                text: 'Perubahan teks dan berkas PDF telah berhasil diekspor.',
                icon: 'success',
                confirmButtonColor: '#2563eb',
            });
        } catch (err) {
            console.error('Export error:', err);
            Swal.fire('Gagal Menyimpan', 'Terjadi kesalahan saat mengekspor dokumen.', 'error');
        } finally {
            setIsProcessing(false);
        }
    };

    const currentPageAnnos = annotations[pageNum] || [];
    const selectedAnno = currentPageAnnos.find(a => a.id === selectedAnnotationId);

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
                                className={`border-2 border-dashed rounded-xl p-10 flex flex-col items-center justify-center cursor-pointer transition-colors ${
                                    isDraggingFile
                                        ? 'border-blue-500 bg-blue-50/50 dark:bg-blue-900/20'
                                        : 'border-slate-300 dark:border-gray-600 hover:border-blue-500'
                                }`}
                            >
                                <div className="w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center mb-3">
                                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                    </svg>
                                </div>
                                <h3 className="text-sm font-bold text-slate-800 dark:text-white">
                                    {isDraggingFile ? 'Lepaskan Berkas PDF di Sini' : 'Pilih atau Tarik Berkas PDF ke Sini'}
                                </h3>
                                <p className="text-xs text-slate-500 mt-1 max-w-md">
                                    Edit teks PDF dengan presisi tanpa pergeseran posisi, otomatis penyesuaian font, atau tambahkan teks baru di posisi mana pun.
                                </p>
                                <input type="file" accept="application/pdf" onChange={handleFileChange} className="hidden" />
                            </label>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {/* Top Toolbar */}
                            <div className="bg-white dark:bg-gray-800 rounded-xl p-3 border border-slate-200 dark:border-gray-700 shadow-xs flex flex-wrap items-center justify-between gap-3">
                                {/* Tool Buttons */}
                                <div className="flex flex-wrap items-center gap-1.5 bg-slate-100 dark:bg-gray-700 p-1 rounded-lg">
                                    <button
                                        type="button"
                                        onClick={() => switchTool('select')}
                                        className={`px-3 py-1.5 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-colors ${
                                            activeTool === 'select' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
                                        }`}
                                    >
                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"></path>
                                        </svg>
                                        Pilih / Geser
                                    </button>

                                    <button
                                        type="button"
                                        onClick={() => switchTool('edit-pdf-text')}
                                        className={`px-3 py-1.5 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-colors ${
                                            activeTool === 'edit-pdf-text' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
                                        }`}
                                        title="Klik langsung pada teks dokumen untuk menggantinya secara presisi"
                                    >
                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        Ganti Teks PDF
                                    </button>

                                    <button
                                        type="button"
                                        onClick={() => switchTool('text')}
                                        className={`px-3 py-1.5 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-colors ${
                                            activeTool === 'text' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
                                        }`}
                                        title="Klik di mana saja pada dokumen untuk mengetik teks"
                                    >
                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                        Ketik Teks Baru
                                    </button>

                                    <label className={`px-3 py-1.5 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-colors cursor-pointer ${
                                        activeTool === 'image' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
                                    }`}>
                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        Stempel Gambar
                                        <input type="file" accept="image/*" onChange={handleImageUpload} className="hidden" />
                                    </label>

                                    <button
                                        type="button"
                                        onClick={() => switchTool('whiteout')}
                                        className={`px-3 py-1.5 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-colors ${
                                            activeTool === 'whiteout' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
                                        }`}
                                    >
                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                        </svg>
                                        Tutup / Sensor
                                    </button>

                                    <button
                                        type="button"
                                        onClick={() => switchTool('draw')}
                                        className={`px-3 py-1.5 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-colors ${
                                            activeTool === 'draw' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
                                        }`}
                                    >
                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                        </svg>
                                        Pena Bebas
                                    </button>
                                </div>

                                {/* Page Nav & Zoom */}
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

                                {/* Actions */}
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
                                        className="px-4 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs disabled:opacity-50 transition-colors flex items-center gap-1.5 shadow-sm"
                                    >
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                        </svg>
                                        {isProcessing ? 'Menyimpan...' : 'Simpan PDF'}
                                    </button>
                                </div>
                            </div>

                            {/* Secondary Formatting Bar */}
                            <div className="bg-slate-50 dark:bg-gray-800 border border-slate-200 dark:border-gray-700 p-2.5 rounded-xl flex flex-wrap items-center justify-between gap-3 text-xs">
                                <div className="flex flex-wrap items-center gap-3">
                                    <div className="flex items-center gap-1.5">
                                        <span className="font-semibold text-slate-600 dark:text-slate-300">Font:</span>
                                        <select
                                            value={selectedAnno?.font || textFont}
                                            onChange={(e) => {
                                                setTextFont(e.target.value);
                                                if (selectedAnnotationId) updateAnnotation(selectedAnnotationId, { font: e.target.value });
                                            }}
                                            className="border border-slate-300 dark:border-gray-600 rounded-md px-2 py-1 text-xs dark:bg-gray-700 dark:text-white"
                                        >
                                            {AVAILABLE_FONTS.map(f => (
                                                <option key={f.id} value={f.id}>{f.name}</option>
                                            ))}
                                        </select>
                                    </div>

                                    <div className="flex items-center gap-1.5">
                                        <span className="font-semibold text-slate-600 dark:text-slate-300">Ukuran:</span>
                                        <input
                                            type="number"
                                            value={selectedAnno?.size || textSize}
                                            onChange={(e) => {
                                                const val = Number(e.target.value);
                                                setTextSize(val);
                                                if (selectedAnnotationId) updateAnnotation(selectedAnnotationId, { size: val });
                                            }}
                                            className="border border-slate-300 dark:border-gray-600 rounded-md px-2 py-1 text-xs w-16 text-center dark:bg-gray-700 dark:text-white"
                                            min="6"
                                            max="72"
                                        />
                                    </div>

                                    <div className="flex items-center gap-1">
                                        <button
                                            type="button"
                                            onClick={() => {
                                                const next = !textBold;
                                                setTextBold(next);
                                                if (selectedAnnotationId) updateAnnotation(selectedAnnotationId, { isBold: next });
                                            }}
                                            className={`w-7 h-7 rounded border font-bold text-xs flex items-center justify-center ${
                                                (selectedAnno ? selectedAnno.isBold : textBold) ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-300 dark:border-gray-600 text-slate-700 dark:text-slate-300'
                                            }`}
                                        >
                                            B
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                const next = !textItalic;
                                                setTextItalic(next);
                                                if (selectedAnnotationId) updateAnnotation(selectedAnnotationId, { isItalic: next });
                                            }}
                                            className={`w-7 h-7 rounded border italic font-serif text-xs flex items-center justify-center ${
                                                (selectedAnno ? selectedAnno.isItalic : textItalic) ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-300 dark:border-gray-600 text-slate-700 dark:text-slate-300'
                                            }`}
                                        >
                                            I
                                        </button>
                                    </div>

                                    <div className="flex items-center gap-1.5">
                                        <span className="font-semibold text-slate-600 dark:text-slate-300">Warna:</span>
                                        <input
                                            type="color"
                                            value={selectedAnno?.color || textColor}
                                            onChange={(e) => {
                                                setTextColor(e.target.value);
                                                if (selectedAnnotationId) updateAnnotation(selectedAnnotationId, { color: e.target.value });
                                            }}
                                            className="w-6 h-6 rounded border border-slate-300 dark:border-gray-600 cursor-pointer"
                                        />
                                    </div>
                                </div>

                                <div className="text-[11px] text-slate-500 font-medium">
                                    {activeTool === 'select' && 'Mode Pilih: Klik elemen untuk memindahkan posisinya.'}
                                    {activeTool === 'edit-pdf-text' && 'Mode Ganti Teks: Klik teks pada dokumen untuk mengedit langsung di tempat (in-place).'}
                                    {activeTool === 'text' && 'Mode Ketik: Klik pada dokumen untuk mengetik teks baru.'}
                                    {activeTool === 'draw' && 'Mode Pena: Gambar atau coret bebas pada dokumen.'}
                                    {activeTool === 'whiteout' && 'Mode Tutup: Klik dokumen untuk menutup bagian yang diinginkan.'}
                                </div>
                            </div>

                            {/* Canvas Workspace */}
                            <div className="bg-slate-200/80 dark:bg-gray-900 rounded-2xl p-6 overflow-auto flex justify-center min-h-[600px] border border-slate-200 dark:border-gray-800">
                                <div
                                    ref={workspaceRef}
                                    onClick={handleWorkspaceClick}
                                    onMouseDown={startDrawing}
                                    onMouseMove={drawMove}
                                    onMouseUp={stopDrawing}
                                    className={`relative shadow-md rounded-xs bg-white overflow-hidden select-none ${
                                        activeTool === 'text' ? 'cursor-text' :
                                        activeTool === 'edit-pdf-text' ? 'cursor-text' :
                                        activeTool === 'whiteout' ? 'cursor-crosshair' :
                                        activeTool === 'draw' ? 'cursor-crosshair' : 'cursor-default'
                                    }`}
                                >
                                    {/* 1. PDF Page Render Canvas (Bottom) */}
                                    <canvas ref={canvasRef} className="block pointer-events-none" />

                                    {/* 2. PDF Native Selectable / Clickable Text Spans (Middle, z-20) */}
                                    <div className="absolute inset-0 z-20 pointer-events-none">
                                        {(activeTool === 'edit-pdf-text' || activeTool === 'select') && pdfTextItems.map((item, idx) => (
                                            <div
                                                key={`pdf_text_${idx}`}
                                                onClick={(e) => handlePdfTextClick(item, e)}
                                                className="pdf-text-span absolute pointer-events-auto cursor-pointer select-text text-transparent hover:bg-blue-400/25 hover:ring-1 hover:ring-blue-500 rounded-xs transition-colors"
                                                style={{
                                                    left: `${item.x}px`,
                                                    top: `${item.y}px`,
                                                    width: `${item.width}px`,
                                                    height: `${item.height}px`,
                                                    fontSize: `${item.fontSize}px`,
                                                    lineHeight: `${item.height}px`,
                                                }}
                                                title={`Klik untuk ganti teks: "${item.str}" (Font: ${item.detectedFont})`}
                                            >
                                                {item.str}
                                            </div>
                                        ))}
                                    </div>

                                    {/* 3. Interactive Annotations Layer (Top, z-30) */}
                                    <div className="absolute inset-0 z-30 pointer-events-none">
                                        {currentPageAnnos.map((anno) => {
                                            const isSelected = selectedAnnotationId === anno.id;
                                            const isEditing = editingAnnotationId === anno.id;
                                            const isPointerActive = activeTool === 'select' || isEditing;

                                            if (anno.type === 'text') {
                                                return (
                                                    <div
                                                        key={anno.id}
                                                        className={`annotation-item ${isPointerActive ? 'pointer-events-auto' : 'pointer-events-none'}`}
                                                        onMouseDown={(e) => handleElementMouseDown(e, anno)}
                                                        onDoubleClick={(e) => {
                                                            if (activeTool !== 'select' && activeTool !== 'edit-pdf-text') return;
                                                            e.stopPropagation();
                                                            setEditingAnnotationId(anno.id);
                                                            setSelectedAnnotationId(anno.id);
                                                        }}
                                                        style={{
                                                            position: 'absolute',
                                                            left: `${anno.x}px`,
                                                            top: `${anno.y}px`,
                                                            zIndex: isEditing ? 50 : 35,
                                                        }}
                                                    >
                                                        {isEditing ? (
                                                            <div className="relative pointer-events-auto">
                                                                <input
                                                                    autoFocus
                                                                    type="text"
                                                                    value={anno.text}
                                                                    onChange={(e) => updateAnnotation(anno.id, { text: e.target.value })}
                                                                    onBlur={() => setEditingAnnotationId(null)}
                                                                    onKeyDown={(e) => {
                                                                        if (e.key === 'Enter' || e.key === 'Escape') {
                                                                            setEditingAnnotationId(null);
                                                                        }
                                                                    }}
                                                                    style={{
                                                                        color: anno.color,
                                                                        fontSize: `${anno.size}px`,
                                                                        fontFamily: getCssFontFamily(anno.font),
                                                                        fontWeight: anno.isBold ? 'bold' : 'normal',
                                                                        fontStyle: anno.isItalic ? 'italic' : 'normal',
                                                                        lineHeight: '1',
                                                                        padding: '0 2px',
                                                                        margin: '0',
                                                                        minWidth: `${Math.max(60, anno.width || 60)}px`,
                                                                        height: `${anno.height || (anno.size * 1.2)}px`,
                                                                    }}
                                                                    className="border border-blue-500 bg-white text-slate-900 shadow-md focus:outline-none"
                                                                />
                                                                <button
                                                                    type="button"
                                                                    onMouseDown={(e) => {
                                                                        e.stopPropagation();
                                                                        setEditingAnnotationId(null);
                                                                    }}
                                                                    className="absolute -top-3 -right-3 bg-blue-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-[10px] shadow"
                                                                >
                                                                    ✓
                                                                </button>
                                                            </div>
                                                        ) : (
                                                            <div
                                                                style={{
                                                                    color: anno.color,
                                                                    fontSize: `${anno.size}px`,
                                                                    fontFamily: getCssFontFamily(anno.font),
                                                                    fontWeight: anno.isBold ? 'bold' : 'normal',
                                                                    fontStyle: anno.isItalic ? 'italic' : 'normal',
                                                                    lineHeight: '1',
                                                                    padding: '0',
                                                                    margin: '0',
                                                                    whiteSpace: 'nowrap',
                                                                }}
                                                                className={`select-none transition-all ${
                                                                    activeTool === 'select' ? 'cursor-move' : ''
                                                                } ${
                                                                    isSelected && activeTool === 'select' ? 'ring-2 ring-blue-500 bg-blue-50/70 shadow-sm' : ''
                                                                }`}
                                                            >
                                                                {anno.text}
                                                            </div>
                                                        )}
                                                    </div>
                                                );
                                            }

                                            if (anno.type === 'rect') {
                                                return (
                                                    <div
                                                        key={anno.id}
                                                        onMouseDown={(e) => handleElementMouseDown(e, anno)}
                                                        style={{
                                                            position: 'absolute',
                                                            left: `${anno.x}px`,
                                                            top: `${anno.y}px`,
                                                            width: `${anno.width}px`,
                                                            height: `${anno.height}px`,
                                                            backgroundColor: anno.color,
                                                            zIndex: 31,
                                                        }}
                                                        className={`annotation-item rounded-xs ${isPointerActive ? 'pointer-events-auto cursor-move' : 'pointer-events-none'} ${
                                                            isSelected && activeTool === 'select' ? 'ring-2 ring-blue-500 shadow-sm' : ''
                                                        }`}
                                                    />
                                                );
                                            }

                                            if (anno.type === 'image') {
                                                return (
                                                    <div
                                                        key={anno.id}
                                                        onMouseDown={(e) => handleElementMouseDown(e, anno)}
                                                        style={{
                                                            position: 'absolute',
                                                            left: `${anno.x}px`,
                                                            top: `${anno.y}px`,
                                                            width: `${anno.width}px`,
                                                            height: `${anno.height}px`,
                                                            zIndex: 32,
                                                        }}
                                                        className={`annotation-item p-0.5 rounded ${isPointerActive ? 'pointer-events-auto cursor-move' : 'pointer-events-none'} ${
                                                            isSelected && activeTool === 'select' ? 'ring-2 ring-blue-500 shadow-sm' : ''
                                                        }`}
                                                    >
                                                        <img src={anno.src} alt="Stamp" className="w-full h-full object-contain pointer-events-none" />
                                                    </div>
                                                );
                                            }

                                            return null;
                                        })}

                                        {/* Freehand drawing paths */}
                                        {isDrawing && currentPath.length > 1 && (
                                            <svg className="absolute inset-0 w-full h-full pointer-events-none z-40">
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
