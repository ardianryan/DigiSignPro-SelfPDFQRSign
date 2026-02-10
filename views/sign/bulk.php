<?php
require_once __DIR__ . '/../../includes/auth_session.php';
requireLogin();

$page_title = "Bulk Sign Generator";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Fetch Max Upload Size (Bulk)
$settings_result = $conn->query("SELECT max_upload_size_bulk FROM app_settings WHERE id = 1");
$settings = $settings_result->fetch_assoc();
$max_upload_bulk_size = $settings['max_upload_size_bulk'] ?? 52428800; // Default 50MB
$max_upload_bulk_mb = round($max_upload_bulk_size / 1048576);
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-slate-800">Bulk Sign Generator</h2>
        <p class="text-slate-500">Upload ZIP berisi banyak file PDF, preview satu, dan tanda tangani semua sekaligus.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8" x-data="bulkSignApp()">
        
        <!-- Step 1: Upload ZIP -->
        <div x-show="!zipLoaded">
            <div 
                class="text-center py-10 border-2 border-dashed rounded-xl transition-colors bg-slate-50 mb-6"
                :class="{'border-blue-500 bg-blue-50': isDraggingFile, 'border-slate-300': !isDraggingFile}"
                @dragover.prevent="isDraggingFile = true"
                @dragleave.prevent="isDraggingFile = false"
                @drop.prevent="handleDrop($event)"
            >
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <p class="mt-2 text-sm text-slate-600">Drag & drop file ZIP berisi kumpulan PDF di sini, atau</p>
                <label class="mt-4 inline-block px-6 py-2 bg-blue-600 text-white rounded-lg cursor-pointer hover:bg-blue-700 transition-colors">
                    Pilih File ZIP
                    <input type="file" accept=".zip,application/zip,application/x-zip-compressed" class="hidden" @change="uploadZip($event)">
                </label>
                <p class="mt-2 text-xs text-slate-400">Maksimal <?php echo $max_upload_bulk_mb; ?>MB. Format ZIP.</p>
                <div x-show="isUploading" class="mt-4">
                    <p class="text-blue-600 text-sm animate-pulse">Mengupload dan mengekstrak preview...</p>
                </div>
            </div>
        </div>

        <!-- Step 2: Preview & Config -->
        <div x-show="zipLoaded" style="display: none;">
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left: Config Form -->
                <div class="lg:col-span-1 order-2 lg:order-1">
                    <div class="bg-slate-50 p-6 rounded-lg border border-slate-200">
                        <h3 class="font-bold text-slate-800 mb-4">Konfigurasi Bulk Sign</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Nomor Surat Dasar</label>
                                <input type="text" x-model="formData.base_number" placeholder="Contoh: 001/SK/2024" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                                <p class="text-xs text-slate-500 mt-1">Akan ditambahkan suffix urutan (-1, -2, dst)</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Perihal / Keterangan</label>
                                <input type="text" x-model="formData.subject" placeholder="Surat Keputusan Massal" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Password Parafrase <span class="text-red-500">*</span></label>
                                <input type="text" x-model="formData.pdf_password" placeholder="Masukkan password parafrase..." class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                                <p class="text-xs text-slate-500 mt-1">Wajib diisi untuk keamanan dan mengunci semua PDF.</p>
                            </div>

                            <div class="flex items-start mt-2">
                                <div class="flex items-center h-5">
                                    <input id="show_qr_caption" type="checkbox" x-model="formData.show_qr_caption" class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="show_qr_caption" class="font-medium text-slate-700">Tampilkan Keterangan QR</label>
                                    <p class="text-slate-500 text-xs">Menampilkan ID TTE, Nama, dan Jabatan.</p>
                                    
                                    <!-- Position Selection -->
                                    <div x-show="formData.show_qr_caption" class="mt-2 space-y-2 pl-1 border-l-2 border-slate-200">
                                        <p class="text-xs font-semibold text-slate-600 mb-1">Posisi Keterangan:</p>
                                        <div class="flex items-center space-x-4">
                                            <label class="inline-flex items-center">
                                                <input type="radio" x-model="formData.qr_caption_position" value="bottom" class="text-blue-600 border-slate-300 focus:ring-blue-500">
                                                <span class="ml-2 text-xs text-slate-700">Di Bawah QR</span>
                                            </label>
                                            <label class="inline-flex items-center">
                                                <input type="radio" x-model="formData.qr_caption_position" value="right" class="text-blue-600 border-slate-300 focus:ring-blue-500">
                                                <span class="ml-2 text-xs text-slate-700">Di Samping Kanan</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-blue-50 p-3 rounded text-xs text-blue-700">
                                <p><strong>Info:</strong> QR Code akan ditempatkan pada posisi yang sama untuk SEMUA file PDF dalam ZIP.</p>
                            </div>
                        </div>

                        <div class="mt-6 pt-6 border-t border-slate-200">
                            <button @click="processBulk()" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg shadow-lg flex justify-center items-center transition-all" :disabled="isProcessing">
                                <span x-show="!isProcessing">Proses Semua File</span>
                                <span x-show="isProcessing" class="flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    Memproses...
                                </span>
                            </button>
                            <button @click="reset()" class="w-full mt-3 text-sm text-red-600 hover:text-red-800 py-2">Batal & Ganti ZIP</button>
                        </div>
                    </div>
                </div>

                <!-- Right: Preview -->
                <div class="lg:col-span-2 order-1 lg:order-2">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h3 class="text-lg font-bold text-slate-800">Preview (Sampel File)</h3>
                            <p class="text-xs text-slate-500">Geser kotak QR Code. Posisi ini akan diterapkan ke semua file.</p>
                            <p class="text-xs text-blue-600 font-mono mt-1" x-text="previewFilename"></p>
                        </div>
                        
                         <!-- Pagination Controls -->
                        <div class="flex items-center space-x-2 bg-white rounded-lg border border-slate-200 p-1 shadow-sm" x-show="totalPage > 1">
                            <button @click="changePage(-1)" :disabled="pageNum <= 1" class="p-1 rounded hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                            </button>
                            <span class="text-sm font-medium text-slate-700">
                                Halaman <span x-text="pageNum"></span> dari <span x-text="totalPage"></span>
                            </span>
                            <button @click="changePage(1)" :disabled="pageNum >= totalPage" class="p-1 rounded hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                            </button>
                        </div>
                    </div>

                    <div class="relative overflow-hidden border border-slate-300 bg-slate-500 flex justify-center" id="pdf-container">
                        <canvas id="pdf-render" class="shadow-lg max-w-full"></canvas>
                        
                        <!-- Draggable QR Marker -->
                        <div id="qr-marker" 
                             class="absolute bg-white/80 border-2 border-blue-600 flex flex-col items-center justify-center cursor-move shadow-xl backdrop-blur-sm z-10"
                             style="top: 50px; left: 50px; width: 96px; height: 96px;"
                             @mousedown="startDrag($event)"
                             @touchstart="startDrag($event)">
                            <div class="text-center">
                                <span class="text-xs font-bold text-blue-700">QR SIGN</span>
                            </div>

                            <!-- Caption Preview (Bottom) -->
                            <div x-show="formData.show_qr_caption && formData.qr_caption_position === 'bottom'" 
                                 class="absolute top-full left-1/2 -translate-x-1/2 mt-[2px] w-[180%] bg-white/90 border border-slate-300 p-1 text-[8px] leading-tight text-slate-600 shadow-sm rounded-sm z-20 pointer-events-none text-center">
                                <div>ID : DS-SAMPLE...</div>
                                <div>Ditandatangani secara elektronik oleh</div>
                                <div class="font-bold text-[11px] text-slate-900 my-[1px]"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Nama User'); ?></div>
                                <div class="font-bold text-slate-800"><?php echo htmlspecialchars($_SESSION['position'] ?? 'Jabatan'); ?></div>
                            </div>

                            <!-- Caption Preview (Right) -->
                            <div x-show="formData.show_qr_caption && formData.qr_caption_position === 'right'" 
                                 class="absolute left-full top-1/2 -translate-y-1/2 ml-[4px] w-[180px] bg-white/90 border border-slate-300 p-1 text-[8px] leading-tight text-slate-600 shadow-sm rounded-sm z-20 pointer-events-none text-left flex flex-col justify-center">
                                <div>ID : DS-SAMPLE...</div>
                                <div>Ditandatangani secara elektronik oleh</div>
                                <div class="font-bold text-[11px] text-slate-900 my-[1px]"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Nama User'); ?></div>
                                <div class="font-bold text-slate-800"><?php echo htmlspecialchars($_SESSION['position'] ?? 'Jabatan'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- PDF.js CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

<script>
    // Initialize PDF.js worker
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    function bulkSignApp() {
        let pdfDocInstance = null;

        return {
            init() {
                window.addEventListener('resize', () => {
                    if (this.zipLoaded) {
                         this.updateMarkerSize();
                    }
                });
            },

            zipLoaded: false,
            isUploading: false,
            isProcessing: false,
            batchId: null,
            previewFilename: '',
            
            // PDF State
            pageNum: 1,
            totalPage: 0,
            scale: 1.5,
            canvas: null,
            ctx: null,
            
            maxFileSize: <?php echo $max_upload_bulk_size; ?>,
            
            formData: {
                base_number: '',
                subject: '',
                pdf_password: '',
                show_qr_caption: false,
                qr_caption_position: 'bottom'
            },
            
            // Marker Position
            markerX: 50,
            markerY: 50,
            markerSize: 96, // Will be updated dynamically
            
            // File Drag State
            isDraggingFile: false,

            handleDrop(event) {
                this.isDraggingFile = false;
                const file = event.dataTransfer.files[0];
                if (file) {
                    const mockEvent = { target: { files: [file] } };
                    this.uploadZip(mockEvent);
                }
            },
            
            // Drag State
            isDragging: false,
            startX: 0,
            startY: 0,
            initialLeft: 0,
            initialTop: 0,

            async uploadZip(event) {
                const file = event.target.files[0];
                if (!file) return;

                if (file.size > this.maxFileSize) {
                    Swal.fire('Error', 'Ukuran file melebihi batas maksimal (' + Math.round(this.maxFileSize/1048576) + 'MB)', 'error');
                    return;
                }

                this.isUploading = true;
                const formData = new FormData();
                formData.append('zip_file', file);

                try {
                    const response = await fetch('<?php echo BASE_URL; ?>/sign/preview_bulk', {
                        method: 'POST',
                        body: formData
                    });
                    const text = await response.text();
                    let result;
                    try {
                        result = JSON.parse(text);
                    } catch (e) {
                        console.error('Server response:', text);
                        Swal.fire('Error', 'Respon server tidak valid', 'error');
                        return;
                    }

                    if (result.status === 'success') {
                        this.batchId = result.batch_id;
                        this.previewFilename = result.filename;
                        this.zipLoaded = true;
                        
                        // Load PDF Preview
                        await this.loadDocument(result.preview_url);
                    } else {
                        Swal.fire('Error', result.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Gagal mengupload file ZIP', 'error');
                } finally {
                    this.isUploading = false;
                }
            },

            async loadDocument(url) {
                try {
                    const loadingTask = pdfjsLib.getDocument(url);
                    pdfDocInstance = await loadingTask.promise;
                    this.totalPage = pdfDocInstance.numPages;
                    this.pageNum = 1;
                    await this.renderPage(this.pageNum);
                } catch (error) {
                    console.error('Error loading PDF:', error);
                    Swal.fire('Error', 'Gagal memuat preview PDF', 'error');
                }
            },

            async renderPage(num) {
                if (!pdfDocInstance) return;
                try {
                    const page = await pdfDocInstance.getPage(num);
                    this.canvas = document.getElementById('pdf-render');
                    this.ctx = this.canvas.getContext('2d');
                    
                    const viewport = page.getViewport({scale: this.scale});
                    this.canvas.height = viewport.height;
                    this.canvas.width = viewport.width;

                    const renderContext = {
                        canvasContext: this.ctx,
                        viewport: viewport
                    };
                    await page.render(renderContext).promise;
                    
                    // Update Marker Size to match 25mm
                    this.updateMarkerSize();
                } catch (error) {
                    console.error('Error rendering page:', error);
                }
            },

            async changePage(delta) {
                const newPage = this.pageNum + delta;
                if (newPage >= 1 && newPage <= this.totalPage) {
                    this.pageNum = newPage;
                    await this.renderPage(this.pageNum);
                }
            },

            updateMarkerSize() {
                setTimeout(() => {
                    const canvas = document.getElementById('pdf-render');
                    if (!canvas) return;
                    
                    // Target QR Size in mm (Must match backend)
                    const targetMm = 25; 
                    
                    // Convert to Points (1mm = 2.83465pt)
                    const targetPoints = targetMm * 2.83465;
                    
                    // Calculate scale ratio (Internal PDF Points -> CSS Pixels)
                    // Logical PDF points width = canvas.width / this.scale
                    const pdfPointsWidth = canvas.width / this.scale;
                    
                    if (pdfPointsWidth > 0) {
                        const cssToPointsRatio = canvas.clientWidth / pdfPointsWidth;
                        const markerPx = targetPoints * cssToPointsRatio;
                        
                        this.markerSize = markerPx;
                        
                        const marker = document.getElementById('qr-marker');
                        if (marker) {
                            marker.style.width = markerPx + 'px';
                            marker.style.height = markerPx + 'px';
                        }
                    }
                }, 50); // Small delay to ensure layout is ready
            },

            startDrag(e) {
                this.isDragging = true;
                const marker = document.getElementById('qr-marker');
                const clientX = e.type.includes('touch') ? e.touches[0].clientX : e.clientX;
                const clientY = e.type.includes('touch') ? e.touches[0].clientY : e.clientY;
                
                this.startX = clientX;
                this.startY = clientY;
                this.initialLeft = marker.offsetLeft;
                this.initialTop = marker.offsetTop;

                window.addEventListener('mousemove', this.doDrag.bind(this));
                window.addEventListener('mouseup', this.stopDrag.bind(this));
                window.addEventListener('touchmove', this.doDrag.bind(this), {passive: false});
                window.addEventListener('touchend', this.stopDrag.bind(this));
            },

            doDrag(e) {
                if (!this.isDragging) return;
                e.preventDefault();
                const clientX = e.type.includes('touch') ? e.touches[0].clientX : e.clientX;
                const clientY = e.type.includes('touch') ? e.touches[0].clientY : e.clientY;

                const dx = clientX - this.startX;
                const dy = clientY - this.startY;

                let newLeft = this.initialLeft + dx;
                let newTop = this.initialTop + dy;

                const container = document.getElementById('pdf-render');
                const maxLeft = container.width - this.markerSize;
                const maxTop = container.height - this.markerSize;

                if (newLeft < 0) newLeft = 0;
                if (newTop < 0) newTop = 0;
                if (newLeft > maxLeft) newLeft = maxLeft;
                if (newTop > maxTop) newTop = maxTop;

                const marker = document.getElementById('qr-marker');
                marker.style.left = newLeft + 'px';
                marker.style.top = newTop + 'px';
                
                this.markerX = newLeft;
                this.markerY = newTop;
            },

            stopDrag() {
                this.isDragging = false;
                window.removeEventListener('mousemove', this.doDrag.bind(this));
                window.removeEventListener('mouseup', this.stopDrag.bind(this));
                window.removeEventListener('touchmove', this.doDrag.bind(this));
                window.removeEventListener('touchend', this.stopDrag.bind(this));
            },

            reset() {
                this.zipLoaded = false;
                this.batchId = null;
                this.pdfDocInstance = null;
                this.previewFilename = '';
            },

            processBulk() {
                if (!this.formData.base_number || !this.formData.subject) {
                    Swal.fire('Error', 'Mohon lengkapi Nomor Surat dan Perihal', 'warning');
                    return;
                }

                if (!this.formData.pdf_password) {
                    Swal.fire('Peringatan', 'Password Parafrase wajib diisi untuk keamanan dokumen.', 'warning');
                    return;
                }

                this.isProcessing = true;
                
                // Calculate Coordinates with Visual Scale Correction
                const canvas = document.getElementById('pdf-render');
                
                // Visual Scale = Internal Resolution / Displayed Width
                // This fixes the shift when canvas is shrunk by CSS (max-w-full)
                const visualScale = canvas.width / canvas.clientWidth;
                
                // 1. Convert Marker Visual Position to Internal Canvas Pixels
                const realX = this.markerX * visualScale;
                const realY = this.markerY * visualScale;
                
                // 2. Convert Internal Pixels to PDF Points (unscaled by PDF.js scale)
                const xPt = realX / this.scale;
                const yPt = realY / this.scale;
                
                // 3. Convert Points to Millimeters (Standard PDF unit)
                // 1 Point = 0.352778 mm
                const xMm = xPt * 0.352778;
                const yMm = yPt * 0.352778;
                
                const formData = new FormData();
                formData.append('batch_id', this.batchId);
                formData.append('x', xMm);
                formData.append('y', yMm);
                formData.append('page', this.pageNum);
                formData.append('base_number', this.formData.base_number);
                formData.append('subject', this.formData.subject);
                formData.append('pdf_password', this.formData.pdf_password);
                formData.append('show_qr_caption', this.formData.show_qr_caption ? 1 : 0);
                formData.append('qr_caption_position', this.formData.qr_caption_position);
                
                fetch('<?php echo BASE_URL; ?>/sign/process_bulk', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.text())
                .then(text => {
                    let result;
                    try {
                        result = JSON.parse(text);
                    } catch (e) {
                        console.error('Server response:', text);
                        Swal.fire('Error', 'Respon server tidak valid', 'error');
                        return;
                    }
                    return result;
                })
                .then(result => {
                    if (result.status === 'success') {
                        // Download ZIP
                        const a = document.createElement('a');
                        a.href = result.zip_url;
                        a.download = result.zip_url.split('/').pop();
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        
                        let msg = `Berhasil memproses ${result.processed} dokumen.`;
                        if (result.failed > 0) {
                            msg += ` Gagal: ${result.failed}. Cek file error di dalam ZIP.`;
                        }
                        Swal.fire('Sukses', msg, 'success');
                        this.reset();
                    } else {
                        Swal.fire('Error', result.message || 'Terjadi kesalahan', 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire('Error', 'Gagal memproses dokumen', 'error');
                })
                .finally(() => {
                    this.isProcessing = false;
                });
            }
        };
    }
</script>
<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
