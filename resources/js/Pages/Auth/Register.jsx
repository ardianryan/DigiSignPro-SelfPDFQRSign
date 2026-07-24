import InputError from '@/Components/InputError';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

export default function Register({ settings: pageSettings }) {
    const sharedSettings = usePage().props.settings || {};
    const settings = pageSettings || sharedSettings;
    const appName = settings?.app_name || 'DigiSign Pro';
    const appLogo = settings?.app_logo || null;

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        position: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <div className="bg-white h-screen overflow-hidden flex flex-col lg:flex-row dark:bg-gray-900">
            <Head title="Daftar" />

            <div className="flex w-full lg:w-1/2 h-1/3 lg:h-full bg-slate-900 text-white flex-col justify-center px-8 lg:px-16 relative shrink-0">
                <div className="absolute inset-0 bg-gradient-to-br from-blue-900 to-slate-900 opacity-50"></div>
                <div className="relative z-10 flex flex-col items-center lg:items-start text-center lg:text-left">
                    {appLogo ? (
                        <img src={`/${appLogo}`} alt={appName} className="h-12 lg:h-20 w-auto object-contain mb-2 lg:mb-6" />
                    ) : (
                        <h1 className="text-3xl lg:text-5xl font-bold tracking-tight mb-2 lg:mb-4">
                            DIGI<span className="text-blue-500">SIGN</span>
                        </h1>
                    )}
                    <h2 className="text-xl lg:text-3xl font-light mb-0 lg:mb-6">{appName}</h2>
                    <p className="hidden lg:block text-slate-400 text-lg leading-relaxed max-w-md">
                        Buat akun untuk mulai menandatangani dokumen secara elektronik dengan aman dan terverifikasi.
                    </p>
                </div>
            </div>

            <div className="w-full lg:w-1/2 flex-1 lg:h-full flex items-center justify-center p-8 bg-slate-50 dark:bg-gray-800 overflow-y-auto">
                <div className="w-full max-w-md">
                    <div className="text-center lg:text-left mb-8">
                        <h3 className="text-2xl font-bold text-slate-900 dark:text-white">Daftar Akun Baru</h3>
                        <p className="text-slate-500 dark:text-slate-400 mt-2">Lengkapi data di bawah untuk membuat akun.</p>
                    </div>

                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">Nama Lengkap</label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                required
                                autoFocus
                            />
                            <InputError message={errors.name} className="mt-1" />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">Email</label>
                            <input
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                required
                            />
                            <InputError message={errors.email} className="mt-1" />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">Jabatan / Posisi</label>
                            <input
                                type="text"
                                value={data.position}
                                onChange={(e) => setData('position', e.target.value)}
                                className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                placeholder="Contoh: Staff Administrasi"
                            />
                            <InputError message={errors.position} className="mt-1" />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">Password</label>
                            <input
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                required
                            />
                            <InputError message={errors.password} className="mt-1" />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">Konfirmasi Password</label>
                            <input
                                type="password"
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                required
                            />
                            <InputError message={errors.password_confirmation} className="mt-1" />
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-semibold py-2.5 rounded-lg transition-colors"
                        >
                            Daftar
                        </button>

                        <p className="text-center text-sm text-slate-500 dark:text-slate-400">
                            Sudah punya akun?{' '}
                            <Link href={route('login')} className="text-blue-600 hover:underline font-medium">
                                Masuk
                            </Link>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    );
}
