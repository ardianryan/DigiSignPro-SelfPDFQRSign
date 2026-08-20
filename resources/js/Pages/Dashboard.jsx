import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Swal from 'sweetalert2';

export default function Dashboard({ auth, stats = {}, recentSignatures = [], adminAnalytics = null, settings = {} }) {
    const user = auth?.user || usePage().props.auth.user;
    const isAdmin = user.role === 'admin';
    const maxPrefixLen = settings?.max_prefix_length || 3;

    const [toolCategory, setToolCategory] = useState('all');
    const [toolSearch, setToolSearch] = useState('');
    const [showPrefixModal, setShowPrefixModal] = useState(false);

    // Time-based greeting without emojis
    const hour = new Date().getHours();
    let greetingTime = 'Pagi';
    if (hour >= 11 && hour < 15) greetingTime = 'Siang';
    else if (hour >= 15 && hour < 18) greetingTime = 'Sore';
    else if (hour >= 18 || hour < 4) greetingTime = 'Malam';

    const { data, setData, post, processing, errors } = useForm({
        signature_prefix: user.signature_prefix || 'DS',
    });

    const updatePrefix = (e) => {
        e.preventDefault();
        post(route('dashboard.prefix'), {
            preserveScroll: true,
            onSuccess: () => {
                setShowPrefixModal(false);
                Swal.fire({
                    title: 'Berhasil',
                    text: 'Prefix tanda tangan berhasil diperbarui.',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false,
                });
            },
            onError: (err) => Swal.fire('Gagal', Object.values(err).join('<br>'), 'error'),
        });
    };

    const copyVerifyCode = (code) => {
        navigator.clipboard.writeText(code);
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: `Kode ${code} disalin`,
            showConfirmButton: false,
            timer: 1500,
        });
    };

    const pdfTools = [
        {
            id: 'editor',
            title: 'Visual PDF Editor',
            category: 'editor',
            categoryLabel: 'Editor Visual',
            description: 'Ubah teks, tempel tanda tangan gambar/stempel, penutup teks (whiteout), dan anotasi.',
            iconBg: 'from-blue-600 to-indigo-600',
            href: route('tools.editor'),
            icon: (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
            )
        },
        {
            id: 'merge',
            title: 'Merge PDF',
            category: 'organize',
            categoryLabel: 'Tata Letak',
            description: 'Gabungkan beberapa file PDF menjadi satu berkas dokumen utuh.',
            iconBg: 'from-slate-700 to-slate-800',
            href: route('tools.merge'),
            icon: (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path>
            )
        },
        {
            id: 'split',
            title: 'Split PDF',
            category: 'organize',
            categoryLabel: 'Tata Letak',
            description: 'Ekstrak rentang halaman tertentu atau pisahkan per halaman.',
            iconBg: 'from-slate-700 to-slate-800',
            href: route('tools.split'),
            icon: (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
            )
        },
        {
            id: 'organize',
            title: 'Organize & Rotate',
            category: 'organize',
            categoryLabel: 'Tata Letak',
            description: 'Atur susunan urutan halaman, rotasi sudut, dan hapus halaman.',
            iconBg: 'from-slate-700 to-slate-800',
            href: route('tools.organize'),
            icon: (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            )
        },
        {
            id: 'image-to-pdf',
            title: 'Image to PDF',
            category: 'convert',
            categoryLabel: 'Konversi',
            description: 'Ubah file gambar JPG atau PNG menjadi PDF dengan format halaman terstandar.',
            iconBg: 'from-slate-700 to-slate-800',
            href: route('tools.image_to_pdf'),
            icon: (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            )
        },
        {
            id: 'watermark',
            title: 'Watermark PDF',
            category: 'security',
            categoryLabel: 'Keamanan & Dokumen',
            description: 'Sisipkan stempel teks atau tanda kepemilikan dokumen.',
            iconBg: 'from-slate-700 to-slate-800',
            href: route('tools.watermark'),
            icon: (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path>
            )
        },
        {
            id: 'page-number',
            title: 'Page Numbering',
            category: 'security',
            categoryLabel: 'Keamanan & Dokumen',
            description: 'Tambahkan penomoran halaman otomatis pada posisi header atau footer.',
            iconBg: 'from-slate-700 to-slate-800',
            href: route('tools.page_number'),
            icon: (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
            )
        },
        {
            id: 'protect',
            title: 'Protect & Encrypt',
            category: 'security',
            categoryLabel: 'Keamanan & Dokumen',
            description: 'Enkripsi berkas dokumen PDF dengan kata sandi keamanan.',
            iconBg: 'from-slate-700 to-slate-800',
            href: route('tools.protect'),
            icon: (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
            )
        }
    ];

    const filteredTools = pdfTools.filter(t => {
        const matchCat = toolCategory === 'all' || t.category === toolCategory;
        const matchSearch = t.title.toLowerCase().includes(toolSearch.toLowerCase()) ||
                            t.description.toLowerCase().includes(toolSearch.toLowerCase());
        return matchCat && matchSearch;
    });

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />

            <div className="space-y-6 animate-fade-in-up">
                {/* 1. Header Ringkas & Profesional */}
                <div className="rounded-2xl bg-slate-900 p-6 sm:p-8 text-white border border-slate-800 shadow-sm">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <div className="space-y-2 max-w-2xl">
                            <div className="inline-flex items-center gap-2 rounded-md bg-slate-800 px-2.5 py-1 text-xs text-slate-300 border border-slate-700">
                                <span>{settings?.app_name || 'DigiSign Pro'}</span>
                                <span className="text-slate-500">•</span>
                                <span>v2.1.0</span>
                            </div>
                            <h1 className="text-2xl font-bold tracking-tight text-white">
                                Selamat {greetingTime}, {user.name}
                            </h1>
                            <p className="text-slate-400 text-xs sm:text-sm leading-relaxed">
                                Sistem manajemen tanda tangan elektronik terenkripsi dan perkakas manipulasi dokumen PDF.
                            </p>
                            <div className="flex flex-wrap items-center gap-2.5 pt-2">
                                <span className="inline-flex items-center px-2.5 py-1 rounded-md bg-slate-800 text-xs text-slate-300 border border-slate-700">
                                    <span className="text-slate-400 mr-1.5">Role:</span> {user.role?.toUpperCase()}
                                </span>
                                {user.position && (
                                    <span className="inline-flex items-center px-2.5 py-1 rounded-md bg-slate-800 text-xs text-slate-300 border border-slate-700">
                                        <span className="text-slate-400 mr-1.5">Jabatan:</span> {user.position}
                                    </span>
                                )}
                                <button
                                    onClick={() => setShowPrefixModal(true)}
                                    className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-slate-800 hover:bg-slate-700 text-xs text-slate-300 border border-slate-700 transition-colors"
                                >
                                    <span className="text-slate-400">Prefix:</span> {user.signature_prefix || 'DS'}
                                    <svg className="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        {/* Quick Action CTA */}
                        <div className="flex flex-col sm:flex-row md:flex-col gap-2.5 flex-shrink-0">
                            <Link
                                href={route('sign.single.create')}
                                className="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs sm:text-sm transition-colors flex items-center justify-center gap-2"
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Buat Tanda Tangan
                            </Link>
                            <Link
                                href={route('sign.bulk.create')}
                                className="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-medium text-xs sm:text-sm border border-slate-700 transition-colors flex items-center justify-center gap-2"
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                                Bulk Sign (ZIP)
                            </Link>
                        </div>
                    </div>
                </div>

                {/* 2. Key Metrics Row */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {/* Stat 1: TTE Signatures */}
                    <div className="bg-white dark:bg-gray-800 p-5 rounded-xl border border-slate-200 dark:border-gray-700 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                                {isAdmin ? 'Total TTE Global' : 'TTE Saya'}
                            </span>
                            <div className="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                        </div>
                        <p className="text-2xl font-bold text-slate-800 dark:text-white mt-2">
                            {isAdmin ? (stats.total_signatures_count ?? 0) : (stats.my_signatures_count ?? 0)}
                        </p>
                        <div className="flex items-center gap-2 mt-2 pt-2 border-t border-slate-100 dark:border-gray-700 text-[11px] text-slate-500">
                            <span>Hari Ini: <strong className="text-slate-700 dark:text-slate-300">{stats.my_signatures_today ?? 0}</strong></span>
                            <span>•</span>
                            <span>Bulan Ini: <strong className="text-slate-700 dark:text-slate-300">{stats.my_signatures_this_month ?? 0}</strong></span>
                        </div>
                    </div>

                    {/* Stat 2: Total Files Processed */}
                    {isAdmin ? (
                        <div className="bg-white dark:bg-gray-800 p-5 rounded-xl border border-slate-200 dark:border-gray-700 shadow-xs">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                                    Berkas Terproses
                                </span>
                                <div className="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                    </svg>
                                </div>
                            </div>
                            <p className="text-2xl font-bold text-slate-800 dark:text-white mt-2">
                                {adminAnalytics?.total_files_processed ?? (stats.total_signatures_count ?? 0)}
                            </p>
                            <p className="text-[11px] text-slate-500 mt-2 pt-2 border-t border-slate-100 dark:border-gray-700">
                                Total TTE & Manipulasi PDF
                            </p>
                        </div>
                    ) : (
                        <div className="bg-white dark:bg-gray-800 p-5 rounded-xl border border-slate-200 dark:border-gray-700 shadow-xs">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                                    Validasi Dokumen
                                </span>
                                <div className="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <p className="text-2xl font-bold text-slate-800 dark:text-white mt-2">Tervalidasi</p>
                            <p className="text-[11px] text-slate-500 mt-2 pt-2 border-t border-slate-100 dark:border-gray-700">
                                Terverifikasi QR Kriptografis
                            </p>
                        </div>
                    )}

                    {/* Stat 3: Total Tool Usage Counter */}
                    {isAdmin ? (
                        <div className="bg-white dark:bg-gray-800 p-5 rounded-xl border border-slate-200 dark:border-gray-700 shadow-xs">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                                    PDF Tools Digunakan
                                </span>
                                <div className="w-8 h-8 rounded-lg bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                                    </svg>
                                </div>
                            </div>
                            <p className="text-2xl font-bold text-slate-800 dark:text-white mt-2">
                                {adminAnalytics?.total_tools_used ?? 0} <span className="text-xs font-normal text-slate-500">kali</span>
                            </p>
                            <p className="text-[11px] text-slate-500 mt-2 pt-2 border-t border-slate-100 dark:border-gray-700">
                                8 Perkakas PDF
                            </p>
                        </div>
                    ) : (
                        <div className="bg-white dark:bg-gray-800 p-5 rounded-xl border border-slate-200 dark:border-gray-700 shadow-xs">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                                    Penyimpanan
                                </span>
                                <div className="w-8 h-8 rounded-lg bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 00-9.78 2.096A4.001 4.001 0 003 15z"></path>
                                    </svg>
                                </div>
                            </div>
                            <p className="text-2xl font-bold text-slate-800 dark:text-white mt-2 capitalize">
                                {settings?.storage_mode || 'Local'}
                            </p>
                            <p className="text-[11px] text-slate-500 mt-2 pt-2 border-t border-slate-100 dark:border-gray-700">
                                Enkripsi AES-256
                            </p>
                        </div>
                    )}

                    {/* Stat 4: Users / Storage */}
                    {isAdmin ? (
                        <div className="bg-white dark:bg-gray-800 p-5 rounded-xl border border-slate-200 dark:border-gray-700 shadow-xs">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                                    Total Pengguna
                                </span>
                                <div className="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <p className="text-2xl font-bold text-slate-800 dark:text-white mt-2">
                                {stats.total_users_count ?? 1}
                            </p>
                            <p className="text-[11px] text-slate-500 mt-2 pt-2 border-t border-slate-100 dark:border-gray-700">
                                Akun Terdaftar
                            </p>
                        </div>
                    ) : (
                        <div className="bg-white dark:bg-gray-800 p-5 rounded-xl border border-slate-200 dark:border-gray-700 shadow-xs">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                                    Akses API
                                </span>
                                <div className="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                                    </svg>
                                </div>
                            </div>
                            <p className="text-2xl font-bold text-slate-800 dark:text-white mt-2">
                                {user.api_key ? 'Aktif' : 'Tersedia'}
                            </p>
                            <Link href={route('profile.edit')} className="text-[11px] text-blue-600 hover:underline mt-2 pt-2 border-t border-slate-100 dark:border-gray-700 block">
                                Kelola Kunci API
                            </Link>
                        </div>
                    )}
                </div>

                {/* 3. Admin Analytics: PDF Tool Usage Counter Breakdown */}
                {isAdmin && adminAnalytics?.tools_breakdown && (
                    <div className="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-slate-200 dark:border-gray-700 shadow-xs">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                            <div>
                                <h3 className="text-base font-bold text-slate-800 dark:text-white">
                                    Statistik Penggunaan PDF Tools
                                </h3>
                                <p className="text-xs text-slate-500 mt-0.5">
                                    Frekuensi eksekusi dan total berkas yang dimanipulasi per perkakas.
                                </p>
                            </div>
                            <div className="text-xs font-semibold px-2.5 py-1 rounded-md bg-slate-100 dark:bg-gray-700 text-slate-600 dark:text-slate-300 self-start sm:self-auto">
                                Total: <span className="text-blue-600 dark:text-blue-400 font-bold">{adminAnalytics.total_tools_used} kali</span>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            {adminAnalytics.tools_breakdown.map((tool) => (
                                <div
                                    key={tool.key}
                                    className="p-3.5 rounded-xl bg-slate-50 dark:bg-gray-700/40 border border-slate-200/80 dark:border-gray-600/60 flex flex-col justify-between"
                                >
                                    <div>
                                        <div className="flex items-center justify-between mb-1.5">
                                            <span className="text-xs font-bold text-slate-800 dark:text-white">
                                                {tool.title}
                                            </span>
                                            <span className="text-xs font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-1.5 py-0.5 rounded">
                                                {tool.usage_count}x
                                            </span>
                                        </div>
                                        <p className="text-[11px] text-slate-500 dark:text-slate-400">
                                            Berkas: <strong className="text-slate-700 dark:text-slate-200">{tool.files_count}</strong>
                                        </p>
                                    </div>

                                    <div className="mt-2.5">
                                        <div className="w-full bg-slate-200 dark:bg-gray-600 h-1.5 rounded-full overflow-hidden">
                                            <div
                                                className="bg-blue-600 h-1.5 rounded-full"
                                                style={{ width: `${Math.max(tool.percentage, tool.usage_count > 0 ? 8 : 0)}%` }}
                                            ></div>
                                        </div>
                                        <div className="flex justify-between items-center text-[10px] text-slate-400 mt-1">
                                            <span>Proporsi</span>
                                            <span>{tool.percentage}%</span>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* 4. Integrated PDF Suite Grid */}
                <div className="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-slate-200 dark:border-gray-700 shadow-xs">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 mb-5 border-b border-slate-100 dark:border-gray-700">
                        <div>
                            <h2 className="text-base font-bold text-slate-800 dark:text-white">
                                PDF Tools Suite
                            </h2>
                            <p className="text-xs text-slate-500 mt-0.5">
                                Perkakas manipulasi dokumen PDF dengan pemrosesan langsung di peramban (Zero-Server).
                            </p>
                        </div>

                        {/* Search & Category Filter */}
                        <div className="flex flex-col sm:flex-row gap-2.5 items-stretch sm:items-center">
                            <div className="flex gap-1 bg-slate-100 dark:bg-gray-700 p-1 rounded-lg">
                                <button
                                    onClick={() => setToolCategory('all')}
                                    className={`px-3 py-1 rounded-md text-xs font-medium transition-colors ${
                                        toolCategory === 'all' ? 'bg-white dark:bg-gray-800 text-blue-600 dark:text-blue-400 shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-white'
                                    }`}
                                >
                                    Semua ({pdfTools.length})
                                </button>
                                <button
                                    onClick={() => setToolCategory('organize')}
                                    className={`px-3 py-1 rounded-md text-xs font-medium transition-colors ${
                                        toolCategory === 'organize' ? 'bg-white dark:bg-gray-800 text-blue-600 dark:text-blue-400 shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-white'
                                    }`}
                                >
                                    Tata Letak
                                </button>
                                <button
                                    onClick={() => setToolCategory('convert')}
                                    className={`px-3 py-1 rounded-md text-xs font-medium transition-colors ${
                                        toolCategory === 'convert' ? 'bg-white dark:bg-gray-800 text-blue-600 dark:text-blue-400 shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-white'
                                    }`}
                                >
                                    Konversi
                                </button>
                                <button
                                    onClick={() => setToolCategory('security')}
                                    className={`px-3 py-1 rounded-md text-xs font-medium transition-colors ${
                                        toolCategory === 'security' ? 'bg-white dark:bg-gray-800 text-blue-600 dark:text-blue-400 shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-white'
                                    }`}
                                >
                                    Keamanan
                                </button>
                            </div>

                            <div className="relative">
                                <input
                                    type="text"
                                    value={toolSearch}
                                    onChange={(e) => setToolSearch(e.target.value)}
                                    placeholder="Cari perkakas..."
                                    className="w-full sm:w-44 border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg pl-8 pr-3 py-1 text-xs focus:ring-1 focus:ring-blue-500 focus:outline-none"
                                />
                                <svg className="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    {/* Bento Grid Cards */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        {filteredTools.map((tool) => (
                            <Link
                                key={tool.id}
                                href={tool.href}
                                className="group rounded-xl bg-slate-50/70 dark:bg-gray-700/30 p-4 border border-slate-200/80 dark:border-gray-600/50 hover:bg-white dark:hover:bg-gray-700 hover:shadow-sm hover:border-slate-300 transition-all flex flex-col justify-between"
                            >
                                <div>
                                    <div className="flex items-center justify-between mb-3">
                                        <div className="w-9 h-9 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                {tool.icon}
                                            </svg>
                                        </div>
                                        <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">
                                            {tool.categoryLabel}
                                        </span>
                                    </div>
                                    <h3 className="text-sm font-bold text-slate-800 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                        {tool.title}
                                    </h3>
                                    <p className="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed line-clamp-2">
                                        {tool.description}
                                    </p>
                                </div>

                                <div className="mt-3 pt-2.5 border-t border-slate-200/60 dark:border-gray-600/40 flex items-center justify-between text-[11px] font-semibold text-blue-600 dark:text-blue-400">
                                    <span>Buka</span>
                                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </div>
                            </Link>
                        ))}
                    </div>
                </div>

                {/* 5. Recent Signatures List */}
                <div className="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-slate-200 dark:border-gray-700 shadow-xs">
                    <div className="flex items-center justify-between mb-4">
                        <div>
                            <h3 className="text-base font-bold text-slate-800 dark:text-white">
                                Dokumen Terbaru
                            </h3>
                            <p className="text-xs text-slate-500 mt-0.5">
                                Berkas PDF yang baru-baru ini ditandatangani.
                            </p>
                        </div>
                        <Link
                            href={route('history.index')}
                            className="text-xs font-semibold text-blue-600 hover:text-blue-700 flex items-center gap-1 hover:underline"
                        >
                            Semua Riwayat
                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </Link>
                    </div>

                    {recentSignatures.length === 0 ? (
                        <div className="text-center py-8 border border-dashed border-slate-200 dark:border-gray-700 rounded-xl">
                            <svg className="w-10 h-10 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p className="text-xs font-medium text-slate-500">Belum ada dokumen yang ditandatangani.</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead>
                                    <tr className="border-b border-slate-100 dark:border-gray-700 text-slate-400 font-semibold uppercase tracking-wider">
                                        <th className="pb-2.5 px-3">Nama Berkas</th>
                                        <th className="pb-2.5 px-3">Nomor Surat</th>
                                        <th className="pb-2.5 px-3">Kode Verifikasi</th>
                                        <th className="pb-2.5 px-3">Waktu</th>
                                        <th className="pb-2.5 px-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-gray-700">
                                    {recentSignatures.map((sig) => (
                                        <tr key={sig.id} className="hover:bg-slate-50 dark:hover:bg-gray-700/50 transition-colors">
                                            <td className="py-2.5 px-3">
                                                <div className="flex items-center gap-2">
                                                    <div className="w-7 h-7 rounded bg-slate-100 dark:bg-gray-700 text-slate-600 dark:text-slate-300 flex items-center justify-center flex-shrink-0">
                                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                                        </svg>
                                                    </div>
                                                    <div className="max-w-xs truncate">
                                                        <p className="font-semibold text-slate-800 dark:text-white truncate">
                                                            {sig.document_name || 'Dokumen TTE'}
                                                        </p>
                                                        <p className="text-[11px] text-slate-400 truncate">
                                                            {sig.document_subject || 'Tanpa perihal'}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="py-2.5 px-3 text-slate-600 dark:text-slate-300 font-medium">
                                                {sig.document_number || '—'}
                                            </td>
                                            <td className="py-2.5 px-3">
                                                <button
                                                    onClick={() => copyVerifyCode(sig.verify_code)}
                                                    className="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-100 dark:bg-gray-700 text-slate-700 dark:text-slate-200 font-mono font-semibold text-[11px] hover:bg-slate-200"
                                                    title="Salin kode"
                                                >
                                                    {sig.verify_code}
                                                    <svg className="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                                    </svg>
                                                </button>
                                            </td>
                                            <td className="py-2.5 px-3 text-slate-500 text-[11px]">
                                                {new Date(sig.signed_at || sig.created_at).toLocaleDateString('id-ID', {
                                                    day: '2-digit',
                                                    month: 'short',
                                                    year: 'numeric'
                                                })}
                                            </td>
                                            <td className="py-2.5 px-3 text-right">
                                                <a
                                                    href={route('verify', { code: sig.verify_code })}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="px-2.5 py-1 rounded-md bg-slate-100 dark:bg-gray-700 hover:bg-blue-600 hover:text-white text-slate-700 dark:text-slate-200 font-medium transition-colors inline-flex items-center gap-1"
                                                >
                                                    Verifikasi
                                                </a>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {/* Prefix Customization Modal */}
            {showPrefixModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs animate-fade-in">
                    <div className="bg-white dark:bg-gray-800 rounded-2xl p-6 max-w-md w-full shadow-xl border border-slate-200 dark:border-gray-700">
                        <div className="flex items-center justify-between mb-3">
                            <h3 className="text-base font-bold text-slate-800 dark:text-white">
                                Atur Prefix Tanda Tangan
                            </h3>
                            <button onClick={() => setShowPrefixModal(false)} className="text-slate-400 hover:text-slate-600">
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mb-4">
                            Awalan kode unik verifikasi untuk seluruh dokumen Anda (2–{maxPrefixLen} huruf kapital A-Z).
                        </p>
                        <form onSubmit={updatePrefix} className="space-y-4">
                            <div>
                                <input
                                    type="text"
                                    value={data.signature_prefix}
                                    onChange={(e) => setData('signature_prefix', e.target.value.toUpperCase())}
                                    maxLength={maxPrefixLen}
                                    minLength={2}
                                    required
                                    className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3.5 py-2 text-sm uppercase font-mono font-bold focus:ring-1 focus:ring-blue-500 focus:outline-none"
                                    placeholder="DS"
                                />
                                {errors.signature_prefix && (
                                    <p className="text-xs text-red-600 mt-1">{errors.signature_prefix}</p>
                                )}
                            </div>
                            <div className="flex justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setShowPrefixModal(false)}
                                    className="px-3.5 py-2 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-gray-700"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold disabled:opacity-50"
                                >
                                    {processing ? 'Menyimpan...' : 'Simpan'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
