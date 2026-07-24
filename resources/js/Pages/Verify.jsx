import { Head, Link } from '@inertiajs/react';

export default function Verify({ status, signature, token, message }) {
    const isValid = status === 'success' && signature;

    return (
        <div className="bg-slate-50 min-h-screen flex items-center justify-center p-4">
            <Head title="Verifikasi Dokumen" />

            <div
                className={`max-w-md w-full bg-white rounded-xl shadow-lg overflow-hidden border-t-4 ${
                    isValid ? 'border-green-500' : 'border-red-500'
                }`}
            >
                {isValid ? (
                    <>
                        <div className="bg-green-50 p-6 text-center border-b border-green-100">
                            <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 text-green-600 mb-3 shadow-sm">
                                <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <h1 className="text-xl font-bold text-green-800">Dokumen Valid</h1>
                            <p className="text-green-600 text-xs mt-1">Terverifikasi oleh Sistem DigiSign</p>
                        </div>

                        <div className="p-6 space-y-5">
                            <div className="flex items-start space-x-3 bg-slate-50 p-4 rounded-lg border border-slate-100">
                                <div className="flex-shrink-0">
                                    <div className="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold">
                                        {(signature.user?.name || 'D').charAt(0).toUpperCase()}
                                    </div>
                                </div>
                                <div>
                                    <p className="text-xs text-slate-500 uppercase tracking-wider font-semibold">
                                        Ditandatangani secara elektronik oleh
                                    </p>
                                    <p className="font-bold text-slate-800">{signature.user?.name || '—'}</p>
                                    <p className="text-sm text-slate-600">{signature.user?.position || 'Staff'}</p>
                                </div>
                            </div>

                            <div className="space-y-3">
                                {signature.document_number && (
                                    <div className="pb-3 border-b border-slate-100">
                                        <p className="text-xs text-slate-400 uppercase tracking-wider font-semibold">
                                            Nomor Dokumen
                                        </p>
                                        <p className="font-medium text-slate-800">{signature.document_number}</p>
                                    </div>
                                )}

                                {(signature.document_subject || signature.document_name) && (
                                    <div className="pb-3 border-b border-slate-100">
                                        <p className="text-xs text-slate-400 uppercase tracking-wider font-semibold">
                                            Perihal
                                        </p>
                                        <p className="font-medium text-slate-800">
                                            {signature.document_subject || signature.document_name}
                                        </p>
                                    </div>
                                )}

                                {signature.document_attachment && (
                                    <div className="pb-3 border-b border-slate-100">
                                        <p className="text-xs text-slate-400 uppercase tracking-wider font-semibold">
                                            Lampiran
                                        </p>
                                        <p className="font-medium text-slate-800">{signature.document_attachment}</p>
                                    </div>
                                )}

                                {signature.signed_at && (
                                    <div>
                                        <p className="text-xs text-slate-400 uppercase tracking-wider font-semibold">
                                            Waktu Penandatanganan
                                        </p>
                                        <p className="font-medium text-slate-800">{signature.signed_at}</p>
                                    </div>
                                )}

                                <div className="pt-2">
                                    <p className="text-xs text-slate-400 uppercase tracking-wider font-semibold">
                                        Kode Verifikasi
                                    </p>
                                    <code className="bg-slate-100 px-2 py-1 rounded text-sm font-mono text-slate-600 block mt-1 w-fit">
                                        {signature.verify_code || token}
                                    </code>
                                </div>
                            </div>

                            {signature.file_url && (
                                <div className="mt-6 pt-4 border-t border-slate-100">
                                    <a
                                        href={signature.file_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="flex items-center justify-center w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors shadow-md hover:shadow-lg"
                                    >
                                        <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                        </svg>
                                        Lihat Dokumen Asli
                                    </a>
                                </div>
                            )}
                        </div>
                    </>
                ) : (
                    <>
                        <div className="bg-red-50 p-6 text-center border-b border-red-100">
                            <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-100 text-red-600 mb-3 shadow-sm">
                                <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </div>
                            <h1 className="text-xl font-bold text-red-800">Dokumen Tidak Valid</h1>
                            <p className="text-red-600 text-xs mt-1">Data tidak ditemukan di sistem kami</p>
                        </div>

                        <div className="p-8 text-center">
                            <p className="text-slate-600 mb-6">
                                {message ||
                                    'Kode verifikasi yang Anda masukkan tidak valid atau dokumen telah dihapus dari database kami.'}
                            </p>
                            {token && <p className="text-xs text-slate-400 mb-6">Kode: {token}</p>}
                            <Link href={route('login')} className="text-blue-600 hover:text-blue-800 font-medium text-sm hover:underline">
                                &larr; Kembali ke Beranda
                            </Link>
                        </div>
                    </>
                )}

                <div className="bg-slate-50 p-4 text-center border-t border-slate-100">
                    <p className="text-xs text-slate-400">
                        &copy; {new Date().getFullYear()} DigiSign. Verifikasi Dokumen Elektronik.
                    </p>
                </div>
            </div>
        </div>
    );
}
