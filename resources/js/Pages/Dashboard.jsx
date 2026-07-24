import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import Swal from 'sweetalert2';

export default function Dashboard({ auth, stats = {}, settings = {} }) {
    const user = auth?.user || usePage().props.auth.user;
    const isAdmin = user.role === 'admin';
    const maxPrefixLen = settings?.max_prefix_length || 3;

    const signedCount = isAdmin
        ? (stats.total_signatures_count ?? stats.my_signatures_count ?? 0)
        : (stats.my_signatures_count ?? 0);

    const { data, setData, post, processing, errors, recentlySuccessful } = useForm({
        signature_prefix: user.signature_prefix || 'DS',
    });

    const updatePrefix = (e) => {
        e.preventDefault();
        post(route('dashboard.prefix'), {
            preserveScroll: true,
            onSuccess: () => Swal.fire('Berhasil!', 'Prefix tanda tangan berhasil diperbarui.', 'success'),
            onError: (err) => Swal.fire('Gagal', Object.values(err).join('<br>'), 'error'),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />

            <div className="mb-8">
                <h2 className="text-3xl font-bold text-slate-800 dark:text-white">
                    Selamat Datang, {user.name} 👋
                </h2>
                <p className="text-slate-500 dark:text-slate-400 mt-1">
                    Anda login sebagai{' '}
                    <span className="font-semibold text-blue-600 uppercase">{user.role}</span>
                    {user.position ? ` — ${user.position}` : ''}
                </p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div className="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700">
                    <div className="flex items-center">
                        <div className="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <p className="text-sm text-slate-500 dark:text-slate-400 font-medium">
                                {isAdmin ? 'Total Dokumen Ditandatangani' : 'Dokumen Ditandatangani'}
                            </p>
                            <p className="text-2xl font-bold text-slate-800 dark:text-white">{signedCount}</p>
                        </div>
                    </div>
                </div>

                {isAdmin && (
                    <div className="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700">
                        <div className="flex items-center">
                            <div className="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                                <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <p className="text-sm text-slate-500 dark:text-slate-400 font-medium">Total Pengguna</p>
                                <p className="text-2xl font-bold text-slate-800 dark:text-white">
                                    {stats.total_users_count ?? 0}
                                </p>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            <div className="mt-8 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700 p-6">
                <h3 className="text-lg font-bold text-slate-800 dark:text-white mb-4">Akses Cepat</h3>
                <div className="flex flex-wrap gap-4 items-start">
                    {isAdmin ? (
                        <>
                            <Link
                                href={route('admin.users.index')}
                                className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors font-medium"
                            >
                                Kelola User
                            </Link>
                            <Link
                                href={route('admin.settings.edit')}
                                className="bg-white dark:bg-gray-700 border border-slate-300 dark:border-gray-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-gray-600 px-4 py-2 rounded-lg transition-colors font-medium"
                            >
                                Pengaturan Sistem
                            </Link>
                            <Link
                                href={route('history.index')}
                                className="bg-white dark:bg-gray-700 border border-slate-300 dark:border-gray-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-gray-600 px-4 py-2 rounded-lg transition-colors font-medium"
                            >
                                Semua Riwayat
                            </Link>
                        </>
                    ) : (
                        <>
                            <Link
                                href={route('sign.single.create')}
                                className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors font-medium"
                            >
                                Buat Tanda Tangan Baru
                            </Link>
                            <Link
                                href={route('history.index')}
                                className="bg-white dark:bg-gray-700 border border-slate-300 dark:border-gray-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-gray-600 px-4 py-2 rounded-lg transition-colors font-medium"
                            >
                                Lihat Riwayat
                            </Link>
                        </>
                    )}
                </div>

                <div className="mt-6 bg-slate-50 dark:bg-gray-900/50 p-4 rounded-lg border border-slate-200 dark:border-gray-700 max-w-md">
                    <h4 className="text-sm font-bold text-slate-700 dark:text-slate-200 mb-1">
                        Prefix Tanda Tangan
                    </h4>
                    <p className="text-xs text-slate-500 dark:text-slate-400 mb-3">
                        Awalan kode verifikasi dokumen Anda (2–{maxPrefixLen} huruf kapital). Contoh: DS-20260724-ABC123
                    </p>
                    <form onSubmit={updatePrefix} className="flex gap-2">
                        <input
                            type="text"
                            value={data.signature_prefix}
                            onChange={(e) => setData('signature_prefix', e.target.value.toUpperCase())}
                            maxLength={maxPrefixLen}
                            minLength={2}
                            required
                            className="border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm uppercase flex-1 focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                            placeholder="DS"
                        />
                        <button
                            type="submit"
                            disabled={processing}
                            className="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                        >
                            Update
                        </button>
                    </form>
                    {errors.signature_prefix && (
                        <p className="text-xs text-red-600 mt-2">{errors.signature_prefix}</p>
                    )}
                    {recentlySuccessful && (
                        <p className="text-xs text-green-600 mt-2">Prefix berhasil disimpan.</p>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
