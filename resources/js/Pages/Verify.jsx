import { Head } from '@inertiajs/react';

export default function Verify({ document, isValid }) {
    return (
        <div className="min-h-screen bg-gray-100 dark:bg-gray-900 flex flex-col justify-center items-center p-6 text-gray-900 dark:text-gray-100">
            <Head title="Verifikasi Dokumen" />

            <div className="w-full max-w-md bg-white dark:bg-gray-800 shadow-lg rounded-2xl p-8 border border-gray-200 dark:border-gray-700">
                <h2 className="text-2xl font-bold text-center mb-6">Verifikasi Tanda Tangan Digital</h2>
                
                {isValid ? (
                    <div className="text-center text-green-600 dark:text-green-400">
                        <svg className="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <p className="font-semibold text-lg">Dokumen Terverifikasi Asli</p>
                        <p className="text-sm mt-2 text-gray-500">Berkas ini ditandatangani secara sah di dalam sistem DigiSign.</p>
                    </div>
                ) : (
                    <div className="text-center text-red-600 dark:text-red-400">
                        <svg className="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <p className="font-semibold text-lg">Dokumen Tidak Terverifikasi</p>
                        <p className="text-sm mt-2 text-gray-500">Kode verifikasi salah atau berkas tidak tercatat di dalam sistem.</p>
                    </div>
                )}
            </div>
        </div>
    );
}
