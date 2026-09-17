import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { getPdfTools } from '@/Constants/pdfTools';

export default function ToolsIndex({ auth }) {
    const [filterCategory, setFilterCategory] = useState('all');
    const [searchQuery, setSearchQuery] = useState('');

    const tools = getPdfTools();

    const filteredTools = tools.filter(tool => {
        const matchesCategory = filterCategory === 'all' || tool.category === filterCategory;
        const matchesSearch = tool.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
                              tool.description.toLowerCase().includes(searchQuery.toLowerCase());
        return matchesCategory && matchesSearch;
    });

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-slate-800 dark:text-slate-200">Perkakas Dokumen PDF</h2>}
        >
            <Head title="Perkakas Dokumen PDF" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl">
                    {/* Hero Banner with Privacy Guarantee */}
                    <div className="rounded-2xl bg-slate-900 p-8 text-white shadow-sm mb-8 border border-slate-800">
                        <div className="max-w-3xl">
                            <div className="inline-flex items-center gap-2 rounded-md bg-slate-800 px-3 py-1 text-xs font-semibold text-emerald-400 border border-slate-700 mb-4">
                                <svg className="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                                Pemrosesan Langsung di Peramban
                            </div>
                            <h1 className="text-2xl sm:text-3xl font-bold tracking-tight">
                                Kelola dan Manipulasi Dokumen PDF
                            </h1>
                            <p className="mt-2 text-slate-300 text-sm leading-relaxed">
                                Gabungkan, pisahkan, susun ulang halaman, konversi gambar, bubuhkan watermark, serta amankan PDF Anda. Seluruh proses manipulasi berjalan langsung di memori browser Anda untuk menjaga kerahasiaan berkas.
                            </p>
                        </div>
                    </div>

                    {/* Filter & Search Controls */}
                    <div className="flex flex-col sm:flex-row justify-between items-center gap-4 mb-8">
                        <div className="flex flex-wrap gap-2 w-full sm:w-auto">
                            <button
                                onClick={() => setFilterCategory('all')}
                                className={`px-4 py-2 rounded-xl text-xs font-semibold transition-all ${
                                    filterCategory === 'all'
                                        ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20'
                                        : 'bg-white dark:bg-gray-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-gray-700 hover:bg-slate-50 dark:hover:bg-gray-700'
                                }`}
                            >
                                Semua Tools ({tools.length})
                            </button>
                            <button
                                onClick={() => setFilterCategory('organize')}
                                className={`px-4 py-2 rounded-xl text-xs font-semibold transition-all ${
                                    filterCategory === 'organize'
                                        ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20'
                                        : 'bg-white dark:bg-gray-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-gray-700 hover:bg-slate-50 dark:hover:bg-gray-700'
                                }`}
                            >
                                Tata Letak & Halaman
                            </button>
                            <button
                                onClick={() => setFilterCategory('convert')}
                                className={`px-4 py-2 rounded-xl text-xs font-semibold transition-all ${
                                    filterCategory === 'convert'
                                        ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20'
                                        : 'bg-white dark:bg-gray-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-gray-700 hover:bg-slate-50 dark:hover:bg-gray-700'
                                }`}
                            >
                                Konversi Gambar
                            </button>
                            <button
                                onClick={() => setFilterCategory('security')}
                                className={`px-4 py-2 rounded-xl text-xs font-semibold transition-all ${
                                    filterCategory === 'security'
                                        ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20'
                                        : 'bg-white dark:bg-gray-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-gray-700 hover:bg-slate-50 dark:hover:bg-gray-700'
                                }`}
                            >
                                Keamanan & Watermark
                            </button>
                        </div>

                        {/* Search Box */}
                        <div className="w-full sm:w-72 relative">
                            <input
                                type="text"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Cari tool PDF..."
                                className="w-full border border-slate-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white rounded-xl pl-10 pr-4 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            />
                            <svg className="w-4 h-4 text-slate-400 absolute left-3.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>

                    {/* Bento Grid Tools Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {filteredTools.map((tool) => (
                            <Link
                                key={tool.id}
                                href={tool.href}
                                className={`group relative rounded-2xl bg-white dark:bg-gray-800 p-6 border border-slate-200 dark:border-gray-700 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-200 flex flex-col justify-between ${
                                    tool.isFeatured ? 'md:col-span-1 lg:col-span-1 ring-1 ring-blue-500/20' : ''
                                }`}
                            >
                                <div>
                                    <div className="flex items-center justify-between mb-4">
                                        <div className={`w-12 h-12 rounded-xl bg-gradient-to-tr ${tool.iconBg} text-white flex items-center justify-center shadow-md group-hover:scale-105 transition-transform`}>
                                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={tool.iconPath}></path>
                                            </svg>
                                        </div>
                                        {tool.badge && (
                                            <span className={`text-[10px] font-bold px-2.5 py-1 rounded-full ${tool.badgeColor}`}>
                                                {tool.badge}
                                            </span>
                                        )}
                                    </div>
                                    <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                                        {tool.categoryLabel}
                                    </span>
                                    <h3 className="text-lg font-bold text-slate-800 dark:text-white mt-1 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                        {tool.title}
                                    </h3>
                                    <p className="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">
                                        {tool.description}
                                    </p>
                                </div>

                                <div className="mt-6 pt-4 border-t border-slate-100 dark:border-gray-700/60 flex items-center justify-between text-xs font-semibold text-blue-600 dark:text-blue-400">
                                    <span>Gunakan Perkakas</span>
                                </div>
                            </Link>
                        ))}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
