import { Head, Link } from '@inertiajs/react';

export default function Welcome({ auth }) {
    return (
        <div className="min-h-screen bg-slate-900 text-slate-100 flex flex-col justify-between">
            <Head title="DigiSign Pro - Layanan Tanda Tangan Elektronik & PDF" />

            {/* Header */}
            <header className="border-b border-slate-800">
                <div className="max-w-6xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
                    <div className="flex items-center space-x-3">
                        <div className="w-9 h-9 rounded-lg bg-blue-600 flex items-center justify-center text-white font-bold text-lg shadow-sm">
                            D
                        </div>
                        <span className="font-bold text-lg text-white tracking-tight">DigiSign Pro</span>
                    </div>
                    <nav className="flex items-center space-x-3">
                        {auth?.user ? (
                            <Link
                                href={route('dashboard')}
                                className="px-4 py-2 text-sm font-semibold rounded-lg bg-blue-600 text-white hover:bg-blue-500 transition-colors shadow-sm"
                            >
                                Buka Dashboard
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={route('verify')}
                                    className="px-3 py-2 text-sm font-medium text-slate-300 hover:text-white transition-colors"
                                >
                                    Verifikasi Dokumen
                                </Link>
                                <Link
                                    href={route('login')}
                                    className="px-4 py-2 text-sm font-semibold rounded-lg bg-blue-600 text-white hover:bg-blue-500 transition-colors shadow-sm"
                                >
                                    Masuk
                                </Link>
                            </>
                        )}
                    </nav>
                </div>
            </header>

            {/* Hero Section */}
            <main className="flex-1 flex flex-col justify-center px-4 sm:px-6 py-16">
                <div className="max-w-4xl mx-auto text-center space-y-6">
                    <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-slate-700 bg-slate-800/80 text-slate-300 text-xs font-medium">
                        <span>Layanan TTE & Pengelolaan PDF Mandiri</span>
                    </div>
                    <h1 className="text-3xl sm:text-5xl font-extrabold text-white tracking-tight leading-tight">
                        Tanda Tangan Elektronik dan Manajemen Dokumen PDF
                    </h1>
                    <p className="text-base sm:text-lg text-slate-400 max-w-2xl mx-auto leading-relaxed">
                        Bubuhkan tanda tangan elektronik berbasis QR Code pada dokumen PDF secara cepat, aman, dan terverifikasi, dilengkapi rangkaian perkakas dokumen peramban.
                    </p>
                    <div className="flex flex-wrap items-center justify-center gap-3 pt-2">
                        {auth?.user ? (
                            <Link
                                href={route('dashboard')}
                                className="px-6 py-3 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm transition-colors shadow-md"
                            >
                                Akses Dashboard
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={route('login')}
                                    className="px-6 py-3 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm transition-colors shadow-md"
                                >
                                    Masuk ke Aplikasi
                                </Link>
                                <Link
                                    href={route('verify')}
                                    className="px-6 py-3 rounded-lg border border-slate-700 bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-sm transition-colors"
                                >
                                    Cek Keaslian Berkas
                                </Link>
                            </>
                        )}
                    </div>

                    {/* Core Features Grid */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-5 text-left pt-12">
                        <div className="p-5 rounded-xl border border-slate-800 bg-slate-800/40">
                            <div className="w-9 h-9 rounded-lg bg-blue-500/10 text-blue-400 flex items-center justify-center mb-3">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <h2 className="font-semibold text-white text-base mb-1">Tanda Tangan Elektronik</h2>
                            <p className="text-slate-400 text-sm leading-relaxed">
                                Penandatanganan dokumen tunggal maupun massal menggunakan kode QR unik yang dapat dilacak dan divalidasi.
                            </p>
                        </div>

                        <div className="p-5 rounded-xl border border-slate-800 bg-slate-800/40">
                            <div className="w-9 h-9 rounded-lg bg-purple-500/10 text-purple-400 flex items-center justify-center mb-3">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                            </div>
                            <h2 className="font-semibold text-white text-base mb-1">Perkakas PDF</h2>
                            <p className="text-slate-400 text-sm leading-relaxed">
                                Fitur gabung, pisah, tata halaman, nomor halaman, tanda air, serta proteksi kata sandi secara langsung di peramban.
                            </p>
                        </div>

                        <div className="p-5 rounded-xl border border-slate-800 bg-slate-800/40">
                            <div className="w-9 h-9 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center mb-3">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <h2 className="font-semibold text-white text-base mb-1">Verifikasi Publik</h2>
                            <p className="text-slate-400 text-sm leading-relaxed">
                                Halaman verifikasi terbuka untuk memeriksa integritas penandatangan, nomor dokumen, dan riwayat penerbitan dokumen.
                            </p>
                        </div>
                    </div>
                </div>
            </main>

            {/* Footer */}
            <footer className="border-t border-slate-800 py-6 text-center text-xs text-slate-500">
                <p>DigiSign Pro · Sistem Tanda Tangan Elektronik Mandiri</p>
            </footer>
        </div>
    );
}
