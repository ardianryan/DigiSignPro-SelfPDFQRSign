<?php
require_once __DIR__ . '/../../includes/auth_session.php';
requireLogin();

$page_title = "Single Sign";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Fetch Max Upload Size
$settings_result = $conn->query("SELECT max_upload_size FROM app_settings WHERE id = 1");
$settings = $settings_result->fetch_assoc();
$max_upload_size = $settings['max_upload_size'] ?? 10485760; // Default 10MB
$max_upload_mb = round($max_upload_size / 1048576);
?>

<div class="max-w-5xl mx-auto">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-slate-800">Single Sign Document</h2>
        <p class="text-slate-500">Upload PDF dan tempatkan QR Code tanda tangan.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6" x-data="pdfSignerApp()">
        
        <!-- Step 1: Upload & Form -->
        <div x-show="!pdfLoaded">
            <!-- Upload Area -->
            <div 
                class="text-center py-10 border-2 border-dashed rounded-xl transition-colors bg-slate-50 mb-6"
                :class="{'border-blue-500 bg-blue-50': isDraggingFile, 'border-slate-300': !isDraggingFile}"
                @dragover.prevent="isDraggingFile = true"
                @dragleave.prevent="isDraggingFile = false"
                @drop.prevent="handleDrop($event)"
            >
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                <p class="mt-2 text-sm text-slate-600">Drag & drop file PDF di sini, atau</p>
                <label class="mt-4 inline-block px-6 py-2 bg-blue-600 text-white rounded-lg cursor-pointer hover:bg-blue-700 transition-colors">
                    Pilih File
                    <input type="file" accept="application/pdf" class="hidden" @change="loadPdf($event)">
                </label>
                <p class="mt-2 text-xs text-slate-400">Maksimal <?php echo $max_upload_mb; ?>MB. Format PDF.</p>
            </div>
        </div>

        <!-- Step 2: Preview, Input & Drag -->
        <div x-show="pdfLoaded" class="relative" style="display: none;">
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left: Form Input -->
                <div class="lg:col-span-1 order-2 lg:order-1">
                    <div class="bg-slate-50 p-6 rounded-lg border border-slate-200">
                        <h3 class="font-bold text-slate-800 mb-4">Detail Dokumen</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Nomor Dokumen</label>
                                <input type="text" x-model="formData.document_number" placeholder="Contoh: 001/SK/2024" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Perihal Dokumen</label>
                                <textarea x-model="formData.document_subject" placeholder="Ringkasan isi dokumen..." rows="3" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Lampiran</label>
                                <input type="text" x-model="formData.document_attachment" placeholder="Contoh: 1 Berkas" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal Tanda Tangan</label>
                                <input type="date" x-model="formData.signed_date" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Password Parafrase <span class="text-red-500">*</span></label>
                                <input type="text" x-model="formData.pdf_password" placeholder="Masukkan password parafrase..." class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                                <p class="text-xs text-slate-500 mt-1">Wajib diisi untuk keamanan dan mengunci PDF.</p>
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
                        </div>

                        <div class="mt-6 pt-6 border-t border-slate-200">
                            <button @click="processSigning()" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg shadow-lg flex justify-center items-center transition-all" :disabled="isProcessing">
                                <span x-show="!isProcessing">Tanda Tangani Sekarang</span>
                                <span x-show="isProcessing" class="flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    Memproses...
                                </span>
                            </button>
                            <button @click="reset()" class="w-full mt-3 text-sm text-red-600 hover:text-red-800 py-2">Batal & Ganti File</button>
                        </div>
                    </div>
                </div>

                <!-- Right: Preview -->
                <div class="lg:col-span-2 order-1 lg:order-2">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h3 class="text-lg font-bold text-slate-800">Preview & Posisi QR</h3>
                            <p class="text-xs text-slate-500">Geser kotak QR Code ke posisi yang diinginkan.</p>
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
    
    // Get current user name from PHP
    const currentUserName = "<?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?>";
    const baseUrl = "<?php echo BASE_URL; ?>";

    function pdfSignerApp() {
        // Store pdfDoc outside of Alpine's reactive scope to avoid Proxy issues with private fields
        let pdfDocInstance = null;

        return {
            init() {
                window.addEventListener('resize', () => {
                    if (this.pdfLoaded) {
                         this.updateMarkerSize();
                    }
                });
            },

            pdfLoaded: false,
            file: null,
            pageNum: 1,
            totalPage: 0,
            scale: 1.5,
            canvas: null,
            ctx: null,
            isProcessing: false,
            maxFileSize: <?php echo $max_upload_size; ?>,
            
            // Form Data
            formData: {
                document_number: '',
                document_subject: '',
                document_attachment: '',
                signed_date: new Date().toISOString().split('T')[0],
                pdf_password: '',
                show_qr_caption: false,
                qr_caption_position: 'bottom'
            },
            
            // Marker Position (relative to canvas)
            markerX: 50,
            markerY: 50,
            markerSize: 96, // Will be updated dynamically
            
            // File Drag State
            isDraggingFile: false,

            // Drag state (QR Marker)
            isDragging: false,
            startX: 0,
            startY: 0,
            initialLeft: 0,
            initialTop: 0,

            handleDrop(event) {
                this.isDraggingFile = false;
                const file = event.dataTransfer.files[0];
                if (file) {
                    // Create a mock event object to reuse loadPdf
                    const mockEvent = { target: { files: [file] } };
                    this.loadPdf(mockEvent);
                }
            },

            loadPdf(event) {
                const file = event.target.files[0];
                if (file && file.type === 'application/pdf') {
                    if (file.size > this.maxFileSize) {
                        Swal.fire('Error', 'Ukuran file melebihi batas maksimal (' + Math.round(this.maxFileSize/1048576) + 'MB)', 'error');
                        return;
                    }
                    this.file = file;
                    const fileReader = new FileReader();
                    fileReader.onload = (e) => {
                        const typedarray = new Uint8Array(e.target.result);
                        this.loadDocument(typedarray);
                    };
                    fileReader.readAsArrayBuffer(file);
                } else {
                    Swal.fire('Error', 'Harap upload file PDF yang valid', 'error');
                }
            },

            async loadDocument(data) {
                try {
                    // Load the document
                    pdfDocInstance = await pdfjsLib.getDocument(data).promise;
                    this.totalPage = pdfDocInstance.numPages;
                    this.pageNum = 1;
                    
                    // Render the first page
                    await this.renderPage(this.pageNum);
                    this.pdfLoaded = true;
                } catch (error) {
                    console.error('Error loading PDF:', error);
                    Swal.fire('Error', 'Gagal memuat PDF', 'error');
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

            async changePage(delta) {
                const newPage = this.pageNum + delta;
                if (newPage >= 1 && newPage <= this.totalPage) {
                    this.pageNum = newPage;
                    await this.renderPage(this.pageNum);
                }
            },

            reset() {
                this.pdfLoaded = false;
                this.file = null;
                pdfDocInstance = null;
                this.pageNum = 1;
                this.totalPage = 0;
            },

            // Dragging Logic
            startDrag(e) {
                this.isDragging = true;
                const marker = document.getElementById('qr-marker');
                
                // Get mouse/touch position
                const clientX = e.type.includes('touch') ? e.touches[0].clientX : e.clientX;
                const clientY = e.type.includes('touch') ? e.touches[0].clientY : e.clientY;
                
                this.startX = clientX;
                this.startY = clientY;
                
                this.initialLeft = marker.offsetLeft;
                this.initialTop = marker.offsetTop;

                // Add global event listeners
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

                // Boundaries (Constrain to Visual Canvas Area)
                const canvas = document.getElementById('pdf-render');
                
                // Canvas visual boundaries relative to container
                const minLeft = canvas.offsetLeft;
                const minTop = canvas.offsetTop;
                const maxLeft = minLeft + canvas.clientWidth - this.markerSize;
                const maxTop = minTop + canvas.clientHeight - this.markerSize;

                if (newLeft < minLeft) newLeft = minLeft;
                if (newTop < minTop) newTop = minTop;
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

            async processSigning() {
                if (!this.file) return;

                // Validate required fields
                if (!this.formData.document_number || !this.formData.document_subject) {
                    Swal.fire('Peringatan', 'Nomor dan Perihal Dokumen wajib diisi.', 'warning');
                    return;
                }

                if (!this.formData.pdf_password) {
                    Swal.fire('Peringatan', 'Password Parafrase wajib diisi untuk keamanan dokumen.', 'warning');
                    return;
                }
                
                this.isProcessing = true;
                
                // --- FIXED COORDINATE CALCULATION ---
                // 1. Get the visual canvas
                const canvas = document.getElementById('pdf-render');
                
                // 2. Calculate the visual scaling (CSS width vs Internal width)
                // canvas.width is the internal resolution (set by viewport.width)
                // canvas.clientWidth is the displayed width (affected by max-w-full)
                const visualScale = canvas.width / canvas.clientWidth;
                
                // 3. Convert Marker Position (Visual Px) to Internal Canvas Px
                // Adjust for canvas offset (centering) relative to container
                const visualX = this.markerX - canvas.offsetLeft;
                const visualY = this.markerY - canvas.offsetTop;
                
                const realX = visualX * visualScale;
                const realY = visualY * visualScale;
                
                // 4. Convert Internal Canvas Px to PDF Points (unscaled)
                // Internal Px = Points * this.scale
                // Points = Internal Px / this.scale
                const xPt = realX / this.scale;
                const yPt = realY / this.scale;
                
                // 5. Convert Points to Millimeters (1 pt = 0.352778 mm)
                const xMm = xPt * 0.352778;
                const yMm = yPt * 0.352778;

                // Send to backend
                const submitData = new FormData();
                submitData.append('pdf_file', this.file);
                submitData.append('x', xMm);
                submitData.append('y', yMm);
                submitData.append('page', this.pageNum);
                
                // Append new fields
                submitData.append('document_number', this.formData.document_number);
                submitData.append('document_subject', this.formData.document_subject);
                submitData.append('document_attachment', this.formData.document_attachment); // Optional
                submitData.append('signed_date', this.formData.signed_date);
                submitData.append('pdf_password', this.formData.pdf_password);
                submitData.append('csrf_token', '<?php echo get_csrf_token(); ?>');
                submitData.append('show_qr_caption', this.formData.show_qr_caption ? 1 : 0);
                submitData.append('qr_caption_position', this.formData.qr_caption_position);

                fetch('<?php echo BASE_URL; ?>/sign/process_single', {
                    method: 'POST',
                    body: submitData
                })
                .then(response => response.json())
                .then(data => {
                    this.isProcessing = false;
                    if (data.status === 'success') {
                        Swal.fire({
                            title: 'Berhasil!',
                            html: `
                                <div class="text-left text-sm text-slate-600 mb-4">
                                    <p class="mb-2">Dokumen berhasil ditandatangani secara elektronik oleh <strong>${currentUserName}</strong>.</p>
                                    <p>Sertifikat elektronik telah diterbitkan dan dapat diverifikasi keasliannya.</p>
                                </div>
                            `,
                            icon: 'success',
                            showCancelButton: true,
                            showDenyButton: true,
                            confirmButtonText: 'Unduh Dokumen',
                            denyButtonText: 'Lihat Halaman Verifikasi',
                            cancelButtonText: 'Tanda Tangan Lain',
                            confirmButtonColor: '#2563EB', // Blue
                            denyButtonColor: '#059669', // Emerald/Green
                            cancelButtonColor: '#64748B', // Slate
                            reverseButtons: true
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Download
                                window.location.href = '/' + data.file_path;
                                // Optional: Reload after download if desired, or let user decide
                                setTimeout(() => window.location.reload(), 2000);
                            } else if (result.isDenied) {
                                // View Verification
                                window.open('/verify/?token=' + data.verify_code, '_blank');
                                // Reset form for next sign
                                setTimeout(() => window.location.reload(), 1000);
                            } else if (result.dismiss === Swal.DismissReason.cancel) {
                                // Sign Another
                                window.location.reload();
                            }
                        });
                    } else {
                        Swal.fire('Gagal', data.message, 'error');
                    }
                })
                .catch(error => {
                    this.isProcessing = false;
                    console.error('Error:', error);
                    Swal.fire('Error', 'Terjadi kesalahan sistem.', 'error');
                });
            }
        }
    }
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
