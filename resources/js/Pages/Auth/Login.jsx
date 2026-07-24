import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status, canResetPassword, settings }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const appName = settings?.app_name || 'DigiSign Pro';
    const appLogo = settings?.app_logo || null;
    const maintenanceMode = settings?.maintenance_mode == 1;
    const registrationOpen = settings?.registration_open !== undefined ? settings.registration_open == 1 : true;

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <div className="bg-white h-screen overflow-hidden flex flex-col lg:flex-row dark:bg-gray-900">
            <Head title="Login" />

            {/* Left Side: Branding */}
            <div className="flex w-full lg:w-1/2 h-1/3 lg:h-full bg-slate-900 text-white flex-col justify-center px-8 lg:px-16 relative shrink-0">
                <div className="absolute inset-0 bg-gradient-to-br from-blue-900 to-slate-900 opacity-50"></div>
                <div className="relative z-10 flex flex-col items-center lg:items-start text-center lg:text-left">
                    {appLogo ? (
                        <img src={`/${appLogo}`} alt={appName} className="h-12 lg:h-20 w-auto object-contain mb-2 lg:mb-6" />
                    ) : (
                        <h1 className="text-3xl lg:text-5xl font-bold tracking-tight mb-2 lg:mb-4">DIGI<span className="text-blue-500">SIGN</span></h1>
                    )}
                    <h2 className="text-xl lg:text-3xl font-light mb-0 lg:mb-6">{appName}</h2>
                    
                    <div className="hidden lg:block">
                        <p className="text-slate-400 text-lg leading-relaxed max-w-md">
                            Solusi aman dan terpercaya untuk manajemen dokumen digital Anda. 
                            Tanda tangani dokumen penting di mana saja, kapan saja, dengan validitas tinggi.
                        </p>
                        
                        <div className="mt-12 flex gap-4">
                            <div className="flex items-center gap-2 text-slate-400">
                                <svg className="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span>Secure</span>
                            </div>
                            <div className="flex items-center gap-2 text-slate-400">
                                <svg className="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                <span>Fast</span>
                            </div>
                            <div className="flex items-center gap-2 text-slate-400">
                                <svg className="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                <span>Mobile Friendly</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Right Side: Login Form */}
            <div className="w-full lg:w-1/2 flex-1 lg:h-full flex items-center justify-center p-8 bg-slate-50 dark:bg-gray-800 overflow-y-auto">
                <div className="w-full max-w-md">
                    <div className="text-center lg:text-left mb-8">
                        <h3 className="text-2xl font-bold text-slate-900 dark:text-white">Selamat Datang Kembali</h3>
                        <p className="text-slate-500 dark:text-slate-400 mt-2">Masuk ke akun Anda untuk melanjutkan.</p>
                    </div>

                    {maintenanceMode && (
                        <div className="bg-yellow-50 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 rounded-r flex items-start" role="alert">
                            <svg className="w-6 h-6 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            <div>
                                <p className="font-bold">Mode Maintenance Aktif</p>
                                <p className="text-sm mt-1">Sistem sedang dalam perbaikan. Hanya Administrator yang dapat login saat ini.</p>
                            </div>
                        </div>
                    )}

                    {status && (
                        <div className="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-r" role="alert">
                            <p className="font-bold">Info</p>
                            <p>{status}</p>
                        </div>
                    )}

                    <form onSubmit={submit} className="space-y-6">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1" htmlFor="email">Email Address</label>
                            <input
                                className="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                id="email"
                                name="email"
                                type="email"
                                value={data.email}
                                placeholder="nama@perusahaan.com"
                                autoComplete="username"
                                onChange={(e) => setData('email', e.target.value)}
                                required
                            />
                            <InputError message={errors.email} className="mt-2" />
                        </div>
                        
                        <div>
                            <div className="flex items-center justify-between mb-1">
                                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300" htmlFor="password">Password</label>
                                {canResetPassword && (
                                    <Link href={route('password.request')} className="text-sm text-blue-600 hover:text-blue-500">
                                        Lupa password?
                                    </Link>
                                )}
                            </div>
                            <input
                                className="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                id="password"
                                name="password"
                                type="password"
                                value={data.password}
                                placeholder="••••••••"
                                autoComplete="current-password"
                                onChange={(e) => setData('password', e.target.value)}
                                required
                            />
                            <InputError message={errors.password} className="mt-2" />
                        </div>

                        <div className="flex items-center">
                            <Checkbox
                                name="remember"
                                checked={data.remember}
                                onChange={(e) => setData('remember', e.target.checked)}
                            />
                            <span className="ms-2 text-sm text-gray-600 dark:text-gray-400">
                                Ingat saya
                            </span>
                        </div>

                        <button
                            className="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all transform hover:scale-[1.01]"
                            type="submit"
                            disabled={processing}
                        >
                            Masuk ke Dashboard
                        </button>
                    </form>
                    
                    {registrationOpen ? (
                        <p className="mt-8 text-center text-slate-600 dark:text-slate-400">
                            Belum punya akun?{' '}
                            <Link href={route('register')} className="font-medium text-blue-600 hover:text-blue-500 hover:underline">
                                Daftar Sekarang
                            </Link>
                        </p>
                    ) : (
                        <p className="mt-8 text-center text-xs text-slate-400">
                            Pendaftaran User Baru Ditutup oleh Admin.
                        </p>
                    )}
                    
                    <div className="mt-12 pt-6 border-t border-slate-200 dark:border-gray-700 text-center text-xs text-slate-400">
                        &copy; {new Date().getFullYear()} {appName}. All rights reserved.
                    </div>
                </div>
            </div>
        </div>
    );
}
