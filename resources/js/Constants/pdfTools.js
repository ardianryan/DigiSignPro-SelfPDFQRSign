export const getPdfTools = () => [
    {
        id: 'editor',
        title: 'Visual PDF Editor',
        category: 'editor',
        categoryLabel: 'Editor Visual',
        description: 'Edit teks, bubuhkan stempel tanda tangan, dan tutup bagian dokumen dengan whiteout.',
        iconBg: 'from-blue-600 to-indigo-600',
        href: route('tools.editor'),
        iconPath: 'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z'
    },
    {
        id: 'merge',
        title: 'Merge PDF',
        category: 'organize',
        categoryLabel: 'Tata Letak',
        description: 'Satukan beberapa berkas PDF menjadi satu dokumen berurutan secara rapi.',
        iconBg: 'from-slate-700 to-slate-800',
        href: route('tools.merge'),
        iconPath: 'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2'
    },
    {
        id: 'split',
        title: 'Split PDF',
        category: 'organize',
        categoryLabel: 'Tata Letak',
        description: 'Ekstrak rentang halaman tertentu atau pisahkan setiap lembar dokumen.',
        iconBg: 'from-slate-700 to-slate-800',
        href: route('tools.split'),
        iconPath: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4'
    },
    {
        id: 'organize',
        title: 'Organize & Rotate',
        category: 'organize',
        categoryLabel: 'Tata Letak',
        description: 'Atur susunan urutan halaman, putar sudut lembar, dan hapus halaman yang tidak perlu.',
        iconBg: 'from-slate-700 to-slate-800',
        href: route('tools.organize'),
        iconPath: 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15'
    },
    {
        id: 'image-to-pdf',
        title: 'Image to PDF',
        category: 'convert',
        categoryLabel: 'Konversi',
        description: 'Konversi gambar format JPG atau PNG menjadi dokumen PDF siap cetak.',
        iconBg: 'from-slate-700 to-slate-800',
        href: route('tools.image_to_pdf'),
        iconPath: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'
    },
    {
        id: 'watermark',
        title: 'Watermark PDF',
        category: 'security',
        categoryLabel: 'Keamanan Dokumen',
        description: 'Sisipkan tanda air teks kepemilikan dokumen dengan sudut rotasi dan transparansi.',
        iconBg: 'from-slate-700 to-slate-800',
        href: route('tools.watermark'),
        iconPath: 'M7 20l4-16m2 16l4-16M6 9h14M4 15h14'
    },
    {
        id: 'page-number',
        title: 'Page Numbering',
        category: 'security',
        categoryLabel: 'Keamanan Dokumen',
        description: 'Beri nomor halaman otomatis pada posisi header atau footer dokumen.',
        iconBg: 'from-slate-700 to-slate-800',
        href: route('tools.page_number'),
        iconPath: 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z'
    },
    {
        id: 'protect',
        title: 'Protect & Encrypt',
        category: 'security',
        categoryLabel: 'Keamanan Dokumen',
        description: 'Kunci berkas PDF dengan kata sandi enkripsi sebelum dibagikan.',
        iconBg: 'from-slate-700 to-slate-800',
        href: route('tools.protect'),
        iconPath: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'
    }
];
