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

    // Dynamic Time of Day Greeting
    const hour = new Date().getHours();
    let greetingTime = 'Pagi';
    if (hour >= 11 && hour < 15) greetingTime = 'Siang';
    else if (hour >= 15 && hour < 18) greetingTime = 'Sore';
    else if (hour >= 18 || hour < 4) greetingTime = 'Malam';

    const { data, setData, post, processing, errors, recentlySuccessful } = useForm({
        signature_prefix: user.signature_prefix || 'DS',
    });

    const updatePrefix = (e) => {
        e.preventDefault();
        post(route('dashboard.prefix'), {
            preserveScroll: true,
            onSuccess: () => {
                setShowPrefixModal(false);
                Swal.fire({
                    title: 'Berhasil!',
                    text: 'Prefix tanda tangan Anda berhasil diperbarui.',
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
            title: `Kode ${code} disalin!`,
            showConfirmButton: false,
            timer: 1500,
        });
    };

    const pdfTools = [
        {
            id: 'merge',
            title: 'Merge PDF',
            category: 'organize',
            categoryLabel: 'Tata Letak',
            description: 'Gabungkan beberapa file PDF menjadi satu dokumen utuh.',
            iconBg: 'from-blue-500 to-indigo-600',
            badge: 'Populer',
            badgeColor: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
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
            description: 'Ekstrak rentang halaman tertentu atau pisahkan semua halaman.',
            iconBg: 'from-purple-500 to-pink-600',
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
            description: 'Atur susunan urutan halaman, putar sudut, dan hapus halaman.',
            iconBg: 'from-emerald-500 to-teal-600',
            badge: 'Interaktif',
            badgeColor: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
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
            description: 'Ubah foto JPG/PNG menjadi PDF dengan pengaturan ukuran kertas.',
            iconBg: 'from-amber-500 to-orange-600',
            href: route('tools.image_to_pdf'),
            icon: (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            )
        },
        {
            id: 'watermark',
            title: 'Watermark PDF',
            category: 'security',
            categoryLabel: 'Keamanan & Desain',
            description: 'Tambahkan stempel teks atau logo transparan pada dokumen.',
            iconBg: 'from-cyan-500 to-blue-600',
            href: route('tools.watermark'),
            icon: (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path>
            )
        },
        {
            id: 'page-number',
            title: 'Page Numbering',
            category: 'security',
            categoryLabel: 'Keamanan & Desain',
            description: 'Sisipkan penomoran halaman otomatis di header atau footer.',
            iconBg: 'from-violet-500 to-purple-600',
            href: route('tools.page_number'),
            icon: (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
            )
        },
        {
            id: 'protect',
            title: 'Protect & Encrypt',
            category: 'security',
            categoryLabel: 'Keamanan & Desain',
            description: 'Enkripsi dokumen PDF dengan password keamanan tinggi.',
            iconBg: 'from-rose-500 to-red-600',
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

            <div className="space-y-8 animate-fade-in-up">
                {/* 1. Executive Hero Header */}
                <div className="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-8 sm:p-10 text-white shadow-xl border border-slate-800 transition-all duration-300">
                    <div className="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <div className="space-y-3 max-w-2xl">
                            <div className="inline-flex items-center gap-2 rounded-full bg-blue-500/20 px-3.5 py-1 text-xs font-semibold text-blue-300 border border-blue-500/30 backdrop-blur-md">
                                <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                {settings?.app_name || 'DigiSign Pro'} • v2.1.0 Enterprise
                            </div>
                            <h1 className="text-2xl sm:text-4xl font-extrabold tracking-tight">
                                Selamat {greetingTime}, {user.name}! 👋
                            </h1>
                            <p className="text-slate-300 text-xs sm:text-sm leading-relaxed">
                                Kelola tanda tangan elektronik terenkripsi, verifikasi kode QR, dan manfaatkan fitur All-in-One PDF Suite dengan keamanan Zero-Server Storage.
                            </p>
                            <div className="flex flex-wrap items-center gap-3 pt-2">
                                <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-slate-800/80 text-xs text-slate-300 border border-slate-700">
                                    <span className="font-semibold text-white">Role:</span> {user.role?.toUpperCase()}
                                </span>
                                {user.position && (
                                    <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-slate-800/80 text-xs text-slate-300 border border-slate-700">
                                        <span className="font-semibold text-white">Jabatan:</span> {user.position}
                                    </span>
                                )}
                                <button
                                    onClick={() => setShowPrefixModal(true)}
                                    className="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-indigo-600/40 hover:bg-indigo-600/70 text-xs text-indigo-200 border border-indigo-500/40 transition-colors"
                                >
                                    <span className="font-semibold text-white">Prefix:</span> {user.signature_prefix || 'DS'} ✎
                                </button>
                            </div>
                        </div>

                        {/* Quick Sign Action CTA */}
                        <div className="flex flex-col sm:flex-row md:flex-col gap-3 flex-shrink-0">
                            <Link
                                href={route('sign.single.create')}
                                className="px-6 py-3 rounded-2xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs sm:text-sm shadow-lg shadow-blue-500/30 transition-all transform hover:-translate-y-0.5 flex items-center justify-center gap-2"
                            >
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                </svg>
                                Buat Tanda Tangan Baru
                            </Link>
                            <Link
                                href={route('sign.bulk.create')}
                                className="px-6 py-3 rounded-2xl bg-slate-800/90 hover:bg-slate-700 text-slate-200 hover:text-white font-semibold text-xs sm:text-sm border border-slate-700 transition-all flex items-center justify-center gap-2"
                            >
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                                Bulk Sign (ZIP Massal)
                            </Link>
                        </div>
                    </div>

                    {/* Decorative Background Orbs */}
                    <div className="absolute -right-20 -top-20 h-72 w-72 rounded-full bg-blue-600/15 blur-3xl pointer-events-none"></div>
                    <div className="absolute -left-20 -bottom-20 h-72 w-72 rounded-full bg-purple-600/15 blur-3xl pointer-events-none"></div>
                </div>

                {/* 2. Key Metrics & Counters Row */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    {/* Stat 1: TTE Signatures */}
                    <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-slate-200 dark:border-gray-700 hover:shadow-md transition-all">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-bold text-slate-400 uppercase tracking-wider">
                                {isAdmin ? 'Total TTE Global' : 'TTE Saya'}
                            </span>
                            <div className="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                        </div>
                        <p className="text-3xl font-extrabold text-slate-800 dark:text-white mt-3">
                            {isAdmin ? (stats.total_signatures_count ?? 0) : (stats.my_signatures_count ?? 0)}
                        </p>
                        <div className="flex items-center gap-3 mt-3 pt-3 border-t border-slate-100 dark:border-gray-700 text-[11px] text-slate-500">
                            <span>Hari Ini: <strong className="text-blue-600">{stats.my_signatures_today ?? 0}</strong></span>
                            <span>•</span>
                            <span>Bulan Ini: <strong className="text-blue-600">{stats.my_signatures_this_month ?? 0}</strong></span>
                        </div>
                    </div>

                    {/* Stat 2: Total Files Processed (Counter File Admin) */}
                    {isAdmin ? (
                        <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-slate-200 dark:border-gray-700 hover:shadow-md transition-all">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-bold text-slate-400 uppercase tracking-wider">
                                    Total Berkas Terproses
                                </span>
                                <div className="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                    </svg>
                                </div>
                            </div>
                            <p className="text-3xl font-extrabold text-slate-800 dark:text-white mt-3">
                                {adminAnalytics?.total_files_processed ?? (stats.total_signatures_count ?? 0)}
                            </p>
                            <p className="text-[11px] text-slate-500 mt-3 pt-3 border-t border-slate-100 dark:border-gray-700">
                                Akumulasi TTE + Manipulasi PDF Tools
                            </p>
                        </div>
                    ) : (
                        <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-slate-200 dark:border-gray-700 hover:shadow-md transition-all">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-bold text-slate-400 uppercase tracking-wider">
                                    Status Dokumen
                                </span>
                                <div className="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <p className="text-3xl font-extrabold text-emerald-600 mt-3">Tervalidasi</p>
                            <p className="text-[11px] text-slate-500 mt-3 pt-3 border-t border-slate-100 dark:border-gray-700">
                                Terverifikasi dengan QR Kriptografis
                            </p>
                        </div>
                    )}

                    {/* Stat 3: Total Tool Usage Counter */}
                    {isAdmin ? (
                        <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-slate-200 dark:border-gray-700 hover:shadow-md transition-all">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-bold text-slate-400 uppercase tracking-wider">
                                    PDF Tools Digunakan
                                </span>
                                <div className="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                                    </svg>
                                </div>
                            </div>
                            <p className="text-3xl font-extrabold text-slate-800 dark:text-white mt-3">
                                {adminAnalytics?.total_tools_used ?? 0} <span className="text-xs font-normal text-slate-400">kali</span>
                            </p>
                            <p className="text-[11px] text-slate-500 mt-3 pt-3 border-t border-slate-100 dark:border-gray-700">
                                7 Tool Client-Side Zero-Server
                            </p>
                        </div>
                    ) : (
                        <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-slate-200 dark:border-gray-700 hover:shadow-md transition-all">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-bold text-slate-400 uppercase tracking-wider">
                                    Penyimpanan
                                </span>
                                <div className="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 00-9.78 2.096A4.001 4.001 0 003 15z"></path>
                                    </svg>
                                </div>
                            </div>
                            <p className="text-2xl font-bold text-slate-800 dark:text-white mt-3 capitalize">
                                {settings?.storage_mode || 'Local'}
                            </p>
                            <p className="text-[11px] text-slate-500 mt-3 pt-3 border-t border-slate-100 dark:border-gray-700">
                                Enkripsi AES-256 Cloud & Server
                            </p>
                        </div>
                    )}

                    {/* Stat 4: Users / Storage */}
                    {isAdmin ? (
                        <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-slate-200 dark:border-gray-700 hover:shadow-md transition-all">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-bold text-slate-400 uppercase tracking-wider">
                                    Total Pengguna
                                </span>
                                <div className="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <p className="text-3xl font-extrabold text-slate-800 dark:text-white mt-3">
                                {stats.total_users_count ?? 1}
                            </p>
                            <p className="text-[11px] text-slate-500 mt-3 pt-3 border-t border-slate-100 dark:border-gray-700">
                                Akun Staf & Penanda Tangan
                            </p>
                        </div>
                    ) : (
                        <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-slate-200 dark:border-gray-700 hover:shadow-md transition-all">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-bold text-slate-400 uppercase tracking-wider">
                                    Akses API v1
                                </span>
                                <div className="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                                    </svg>
                                </div>
                            </div>
                            <p className="text-2xl font-bold text-slate-800 dark:text-white mt-3">
                                {user.api_key ? 'Aktif' : 'Tersedia'}
                            </p>
                            <Link href={route('profile.edit')} className="text-[11px] text-blue-600 hover:underline mt-3 pt-3 border-t border-slate-100 dark:border-gray-700 block">
                                Kelola Kunci API di Profil →
                            </Link>
                        </div>
                    )}
                </div>

                {/* 3. Admin Analytics: PDF Tool Usage Counter Breakdown */}
                {isAdmin && adminAnalytics?.tools_breakdown && (
                    <div className="bg-white dark:bg-gray-800 rounded-3xl p-6 sm:p-8 border border-slate-200 dark:border-gray-700 shadow-sm transition-all">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-6">
                            <div>
                                <h3 className="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2">
                                    <svg className="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                    Statistik Penggunaan PDF Tools (Admin Analytics)
                                </h3>
                                <p className="text-xs text-slate-500 mt-1">
                                    Rekapitulasi frekuensi eksekusi dan total berkas yang dimanipulasi per masing-masing tool.
                                </p>
                            </div>
                            <div className="text-xs font-semibold px-3 py-1.5 rounded-full bg-slate-100 dark:bg-gray-700 text-slate-600 dark:text-slate-300 self-start sm:self-auto">
                                Total Eksekusi: <span className="text-indigo-600 font-bold">{adminAnalytics.total_tools_used} kali</span>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                            {adminAnalytics.tools_breakdown.map((tool) => (
                                <div
                                    key={tool.key}
                                    className="p-4 rounded-2xl bg-slate-50 dark:bg-gray-700/40 border border-slate-200/80 dark:border-gray-600/60 flex flex-col justify-between hover:border-indigo-500/50 transition-colors"
                                >
                                    <div>
                                        <div className="flex items-center justify-between mb-2">
                                            <span className="text-xs font-bold text-slate-800 dark:text-white">
                                                {tool.title}
                                            </span>
                                            <span className="text-xs font-extrabold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/40 px-2 py-0.5 rounded-md">
                                                {tool.usage_count}x
                                            </span>
                                        </div>
                                        <p className="text-[11px] text-slate-500 dark:text-slate-400">
                                            Berkas Terproses: <strong className="text-slate-700 dark:text-slate-200">{tool.files_count} file</strong>
                                        </p>
                                    </div>

                                    <div className="mt-3">
                                        <div className="w-full bg-slate-200 dark:bg-gray-600 h-1.5 rounded-full overflow-hidden">
                                            <div
                                                className="bg-indigo-600 h-1.5 rounded-full transition-all duration-500"
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

                {/* 4. Integrated Bento PDF Suite Grid (Centralized in Dashboard) */}
                <div className="bg-white dark:bg-gray-800 rounded-3xl p-6 sm:p-8 border border-slate-200 dark:border-gray-700 shadow-sm">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 mb-6 border-b border-slate-100 dark:border-gray-700">
                        <div>
                            <div className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 text-[11px] font-bold mb-1">
                                🔒 100% In-Browser Engine
                            </div>
                            <h2 className="text-xl font-bold text-slate-800 dark:text-white">
                                All-in-One Bento PDF Suite
                            </h2>
                            <p className="text-xs text-slate-500 mt-0.5">
                                Akses cepat ke seluruh perkakas manipulasi PDF tanpa perlu upload ke server.
                            </p>
                        </div>

                        {/* Search & Category Filter */}
                        <div className="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
                            <div className="flex gap-1 bg-slate-100 dark:bg-gray-700 p-1 rounded-xl">
                                <button
                                    onClick={() => setToolCategory('all')}
                                    className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                                        toolCategory === 'all' ? 'bg-white dark:bg-gray-800 text-blue-600 dark:text-blue-400 shadow-xs' : 'text-slate-500 hover:text-slate-800'
                                    }`}
                                >
                                    Semua ({pdfTools.length})
                                </button>
                                <button
                                    onClick={() => setToolCategory('organize')}
                                    className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                                        toolCategory === 'organize' ? 'bg-white dark:bg-gray-800 text-blue-600 dark:text-blue-400 shadow-xs' : 'text-slate-500 hover:text-slate-800'
                                    }`}
                                >
                                    Tata Letak
                                </button>
                                <button
                                    onClick={() => setToolCategory('convert')}
                                    className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                                        toolCategory === 'convert' ? 'bg-white dark:bg-gray-800 text-blue-600 dark:text-blue-400 shadow-xs' : 'text-slate-500 hover:text-slate-800'
                                    }`}
                                >
                                    Konversi
                                </button>
                                <button
                                    onClick={() => setToolCategory('security')}
                                    className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                                        toolCategory === 'security' ? 'bg-white dark:bg-gray-800 text-blue-600 dark:text-blue-400 shadow-xs' : 'text-slate-500 hover:text-slate-800'
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
                                    placeholder="Cari tool..."
                                    className="w-full sm:w-48 border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl pl-9 pr-3 py-1.5 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                />
                                <svg className="w-4 h-4 text-slate-400 absolute left-3 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    {/* Bento Grid Cards */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        {filteredTools.map((tool) => (
                            <Link
                                key={tool.id}
                                href={tool.href}
                                className="group relative rounded-2xl bg-slate-50/70 dark:bg-gray-700/30 p-5 border border-slate-200/80 dark:border-gray-600/50 hover:bg-white dark:hover:bg-gray-700 hover:shadow-lg hover:-translate-y-1 transition-all duration-200 flex flex-col justify-between"
                            >
                                <div>
                                    <div className="flex items-center justify-between mb-3">
                                        <div className={`w-10 h-10 rounded-xl bg-gradient-to-tr ${tool.iconBg} text-white flex items-center justify-center shadow-md group-hover:scale-110 transition-transform`}>
                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                {tool.icon}
                                            </svg>
                                        </div>
                                        {tool.badge && (
                                            <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${tool.badgeColor}`}>
                                                {tool.badge}
                                            </span>
                                        )}
                                    </div>
                                    <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                        {tool.categoryLabel}
                                    </span>
                                    <h3 className="text-sm font-bold text-slate-800 dark:text-white mt-0.5 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                        {tool.title}
                                    </h3>
                                    <p className="text-xs text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed line-clamp-2">
                                        {tool.description}
                                    </p>
                                </div>

                                <div className="mt-4 pt-3 border-t border-slate-200/60 dark:border-gray-600/40 flex items-center justify-between text-[11px] font-semibold text-blue-600 dark:text-blue-400 group-hover:translate-x-1 transition-transform">
                                    <span>Buka Tool</span>
                                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                    </svg>
                                </div>
                            </Link>
                        ))}
                    </div>
                </div>

                {/* 5. Recent Signatures List */}
                <div className="bg-white dark:bg-gray-800 rounded-3xl p-6 sm:p-8 border border-slate-200 dark:border-gray-700 shadow-sm">
                    <div className="flex items-center justify-between mb-6">
                        <div>
                            <h3 className="text-lg font-bold text-slate-800 dark:text-white">
                                Riwayat Dokumen Terbaru
                            </h3>
                            <p className="text-xs text-slate-500 mt-0.5">
                                Dokumen PDF yang baru-baru ini Anda tandatangani secara elektronik.
                            </p>
                        </div>
                        <Link
                            href={route('history.index')}
                            className="text-xs font-semibold text-blue-600 hover:text-blue-700 flex items-center gap-1 hover:underline"
                        >
                            Lihat Semua Riwayat
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </Link>
                    </div>

                    {recentSignatures.length === 0 ? (
                        <div className="text-center py-10 border-2 border-dashed border-slate-200 dark:border-gray-700 rounded-2xl">
                            <svg className="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p className="text-sm font-semibold text-slate-600 dark:text-slate-300">Belum ada dokumen yang ditandatangani.</p>
                            <p className="text-xs text-slate-400 mt-1">Mulai tanda tangani PDF pertama Anda sekarang.</p>
                            <Link
                                href={route('sign.single.create')}
                                className="inline-flex items-center gap-2 mt-4 px-4 py-2 rounded-xl bg-blue-600 text-white text-xs font-semibold hover:bg-blue-700 shadow-xs"
                            >
                                Buat Tanda Tangan
                            </Link>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead>
                                    <tr className="border-b border-slate-100 dark:border-gray-700 text-slate-400 font-bold uppercase tracking-wider">
                                        <th className="pb-3 px-3">Nama Berkas & Perihal</th>
                                        <th className="pb-3 px-3">Nomor Surat</th>
                                        <th className="pb-3 px-3">Kode Verifikasi</th>
                                        <th className="pb-3 px-3">Waktu TTE</th>
                                        <th className="pb-3 px-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-gray-700">
                                    {recentSignatures.map((sig) => (
                                        <tr key={sig.id} className="hover:bg-slate-50 dark:hover:bg-gray-700/50 transition-colors">
                                            <td className="py-3 px-3">
                                                <div className="flex items-center gap-2.5">
                                                    <div className="w-8 h-8 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-600 flex items-center justify-center flex-shrink-0">
                                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                                        </svg>
                                                    </div>
                                                    <div className="max-w-xs truncate">
                                                        <p className="font-bold text-slate-800 dark:text-white truncate">
                                                            {sig.document_name || 'Dokumen TTE'}
                                                        </p>
                                                        <p className="text-[11px] text-slate-400 truncate">
                                                            {sig.document_subject || 'Tanpa perihal'}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="py-3 px-3 text-slate-600 dark:text-slate-300 font-medium">
                                                {sig.document_number || '—'}
                                            </td>
                                            <td className="py-3 px-3">
                                                <button
                                                    onClick={() => copyVerifyCode(sig.verify_code)}
                                                    className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 font-mono font-bold text-[11px] hover:bg-blue-100"
                                                    title="Klik untuk menyalin kode"
                                                >
                                                    {sig.verify_code}
                                                    <svg className="w-3 h-3 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                                    </svg>
                                                </button>
                                            </td>
                                            <td className="py-3 px-3 text-slate-500 text-[11px]">
                                                {new Date(sig.signed_at || sig.created_at).toLocaleDateString('id-ID', {
                                                    day: '2-digit',
                                                    month: 'short',
                                                    year: 'numeric'
                                                })}
                                            </td>
                                            <td className="py-3 px-3 text-right">
                                                <a
                                                    href={route('verify', { code: sig.verify_code })}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-gray-700 hover:bg-blue-600 hover:text-white text-slate-700 dark:text-slate-200 font-medium transition-colors inline-flex items-center gap-1"
                                                >
                                                    Verifikasi
                                                    <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                                    </svg>
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
                    <div className="bg-white dark:bg-gray-800 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl border border-slate-200 dark:border-gray-700">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-lg font-bold text-slate-800 dark:text-white">
                                Atur Prefix Tanda Tangan
                            </h3>
                            <button onClick={() => setShowPrefixModal(false)} className="text-slate-400 hover:text-slate-600">
                                ✕
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
                                    className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl px-4 py-2.5 text-sm uppercase font-mono font-bold focus:ring-2 focus:ring-blue-500 focus:outline-none"
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
                                    className="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-gray-700"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-md shadow-blue-500/20 disabled:opacity-50"
                                >
                                    {processing ? 'Menyimpan...' : 'Simpan Prefix'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
