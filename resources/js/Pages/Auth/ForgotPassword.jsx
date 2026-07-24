import InputError from '@/Components/InputError';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

export default function ForgotPassword({ status }) {
    const settings = usePage().props.settings || {};
    const appName = settings.app_name || 'DigiSign Pro';
    const appLogo = settings.app_logo || null;

    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <div className="bg-white min-h-screen overflow-hidden flex flex-col lg:flex-row dark:bg-gray-900">
            <Head title="Lupa Password" />

            <div className="flex w-full lg:w-1/2 h-40 lg:h-auto bg-slate-900 text-white flex-col justify-center px-8 lg:px-16 relative shrink-0">
                <div className="absolute inset-0 bg-gradient-to-br from-blue-900 to-slate-900 opacity-50"></div>
                <div className="relative z-10">
                    {appLogo ? (
                        <img src={`/${appLogo}`} alt={appName} className="h-12 w-auto object-contain mb-4" />
                    ) : (
                        <h1 className="text-3xl font-bold tracking-tight mb-2">
                            DIGI<span className="text-blue-500">SIGN</span>
                        </h1>
                    )}
                    <p className="text-slate-400 hidden lg:block">Reset password akun Anda dengan aman.</p>
                </div>
            </div>

            <div className="flex-1 flex items-center justify-center p-8 bg-slate-50 dark:bg-gray-800">
                <div className="w-full max-w-md">
                    <h3 className="text-2xl font-bold text-slate-900 dark:text-white mb-2">Lupa Password?</h3>
                    <p className="text-sm text-slate-500 dark:text-slate-400 mb-6">
                        Masukkan email akun Anda. Kami akan mengirim tautan untuk mengatur ulang password.
                    </p>

                    {status && (
                        <div className="mb-4 text-sm font-medium text-green-600 dark:text-green-400 bg-green-50 border border-green-100 rounded-lg p-3">
                            {status}
                        </div>
                    )}

                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">Email</label>
                            <input
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                required
                                autoFocus
                            />
                            <InputError message={errors.email} className="mt-1" />
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-semibold py-2.5 rounded-lg transition-colors"
                        >
                            Kirim Link Reset Password
                        </button>

                        <p className="text-center text-sm text-slate-500">
                            <Link href={route('login')} className="text-blue-600 hover:underline font-medium">
                                &larr; Kembali ke Login
                            </Link>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    );
}
