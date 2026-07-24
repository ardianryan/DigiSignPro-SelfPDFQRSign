import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Swal from 'sweetalert2';

export default function QrCreate({ auth, step = 1, id = 0, data = null, qrImage = null }) {
    const [formData, setFormData] = useState({
        document_number: '',
        subject: '',
        attachment: '',
        signed_at: new Date().toISOString().split('T')[0],
        pdf_password: '',
    });

    const [uploadFile, setUploadFile] = useState(null);
    const [uploadPassword, setUploadPassword] = useState('');
    const [isProcessing, setIsProcessing] = useState(false);

    const handleCreateSubmit = (e) => {
        e.preventDefault();
        if (!formData.document_number || !formData.subject || !formData.pdf_password) {
            Swal.fire('Peringatan', 'Isian bertanda bintang (*) wajib dilengkapi.', 'warning');
            return;
        }

        router.post(route('sign.qr.store'), {
            step: 1,
            document_number: formData.document_number,
            subject: formData.subject,
            attachment: formData.attachment,
            signed_at: formData.signed_at,
            pdf_password: formData.pdf_password
        });
    };

    const handleUploadSubmit = async (e) => {
        e.preventDefault();
        if (!uploadFile) {
            Swal.fire('Peringatan', 'Pilih file PDF terlebih dahulu.', 'warning');
            return;
        }

        setIsProcessing(true);
        Swal.fire({
            title: 'Mengunggah & Mengamankan PDF',
            text: 'Silakan tunggu sebentar...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const uploadData = new FormData();
        uploadData.append('step', '3');
        uploadData.append('id', id);
        uploadData.append('pdf_file', uploadFile);
        uploadData.append('pdf_password', uploadPassword);

        try {
            const response = await fetch(route('sign.qr.store'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: uploadData
            });

            setIsProcessing(false);
            Swal.close();

            router.get(route('sign.qr.index'), {}, {
                onSuccess: () => Swal.fire('Berhasil!', 'Dokumen manual berhasil diunggah.', 'success')
            });
        } catch (err) {
            setIsProcessing(false);
            Swal.fire('Error', 'Gagal memproses file: ' + err.message, 'error');
        }
    };

    const handleFinish = () => {
        router.get(route('sign.qr.index'));
    };

    const downloadQrCode = () => {
        if (!qrImage) return;
        const link = document.createElement('a');
        link.href = qrImage;
        link.download = `QR_TTE_${data ? data.verify_code : 'code'}.png`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    const copyVerifyCode = () => {
        if (!data) return;
        navigator.clipboard.writeText(data.verify_code);
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Kode verifikasi disalin!',
            showConfirmButton: false,
            timer: 1500
        });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-slate-800 dark:text-slate-200">Buat TTE QR Baru</h2>}
        >
            <Head title="Buat TTE QR" />

            <div className="py-6">
                <div className="mx-auto max-w-2xl">
                    <div className="mb-6">
                        <Link href={route('sign.qr.index')} className="text-sm text-blue-500 hover:underline flex items-center">
                            <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Kembali ke Daftar
                        </Link>
                    </div>

                    {/* Step 1: Form Input */}
                    {step === 1 && (
                        <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700 p-6">
                            <h3 className="text-lg font-bold text-slate-800 dark:text-white mb-6">Langkah 1: Isi Informasi Dokumen</h3>
                            <form onSubmit={handleCreateSubmit} className="space-y-4">
                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">
                                        Nomor Dokumen <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={formData.document_number}
                                        onChange={(e) => setFormData({ ...formData, document_number: e.target.value })}
                                        placeholder="Contoh: 025/KEP/2026"
                                        className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                        required
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">
                                        Perihal / Isi Ringkas <span className="text-red-500">*</span>
                                    </label>
                                    <textarea
                                        value={formData.subject}
                                        onChange={(e) => setFormData({ ...formData, subject: e.target.value })}
                                        placeholder="Tulis ringkasan perihal dokumen..."
                                        rows="3"
                                        className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                        required
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">
                                        Lampiran (Opsional)
                                    </label>
                                    <input
                                        type="text"
                                        value={formData.attachment}
                                        onChange={(e) => setFormData({ ...formData, attachment: e.target.value })}
                                        placeholder="Contoh: 2 Lembar"
                                        className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">
                                        Tanggal Tanda Tangan
                                    </label>
                                    <input
                                        type="date"
                                        value={formData.signed_at}
                                        onChange={(e) => setFormData({ ...formData, signed_at: e.target.value })}
                                        className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">
                                        Password Parafrase <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="password"
                                        value={formData.pdf_password}
                                        onChange={(e) => setFormData({ ...formData, pdf_password: e.target.value })}
                                        placeholder="Masukkan password parafrase..."
                                        className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                        required
                                    />
                                    <p className="text-xs text-slate-400 mt-1">Digunakan untuk proteksi jika nanti Anda mengunggah PDF.</p>
                                </div>

                                <button
                                    type="submit"
                                    className="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition-colors shadow-md text-sm mt-6"
                                >
                                    Buat Kode Verifikasi & QR
                                </button>
                            </form>
                        </div>
                    )}

                    {/* Step 2: Show QR & Upload Options */}
                    {step === 2 && data && (
                        <div className="space-y-6">
                            <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700 p-6 text-center">
                                <h3 className="text-lg font-bold text-slate-800 dark:text-white mb-4">Langkah 2: QR Code Verifikasi Berhasil Dibuat</h3>
                                
                                {qrImage && (
                                    <img src={qrImage} alt="QR Code" className="w-48 h-48 mx-auto border border-slate-200 p-2 rounded-lg bg-white mb-4 shadow-sm" />
                                )}

                                <div className="max-w-md mx-auto bg-slate-50 dark:bg-gray-700/50 p-4 rounded-xl border border-slate-200 dark:border-gray-600 text-left mb-6 space-y-3">
                                    <div className="flex justify-between items-center pb-2 border-b border-slate-200 dark:border-gray-600">
                                        <span className="text-xs text-slate-500 dark:text-slate-400">Kode Verifikasi</span>
                                        <div className="flex items-center gap-1">
                                            <span className="font-mono text-sm font-bold text-slate-800 dark:text-white">{data.verify_code}</span>
                                            <button onClick={copyVerifyCode} className="text-blue-500 hover:text-blue-600">
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div className="text-xs text-slate-700 dark:text-slate-300">
                                        <span className="font-semibold block mb-0.5">Nomor:</span>
                                        {data.document_number}
                                    </div>
                                    <div className="text-xs text-slate-700 dark:text-slate-300">
                                        <span className="font-semibold block mb-0.5">Perihal:</span>
                                        {data.document_subject}
                                    </div>
                                </div>

                                <div className="flex flex-col sm:flex-row justify-center gap-3">
                                    <button
                                        onClick={downloadQrCode}
                                        className="bg-white border border-slate-200 dark:border-gray-600 text-slate-700 dark:text-slate-200 font-semibold py-2 px-6 rounded-lg hover:bg-slate-50 dark:hover:bg-gray-700 transition-colors text-sm shadow-sm flex items-center justify-center gap-2"
                                    >
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                        </svg>
                                        Download Gambar QR
                                    </button>
                                    <button
                                        onClick={handleFinish}
                                        className="bg-slate-800 hover:bg-slate-900 text-white font-bold py-2 px-6 rounded-lg transition-colors text-sm shadow-sm"
                                    >
                                        Selesai (Tanpa Upload PDF)
                                    </button>
                                </div>
                            </div>

                            {/* Option 2: Upload PDF */}
                            <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700 p-6">
                                <h4 className="font-bold text-slate-800 dark:text-white mb-2">Unggah File PDF (Opsional)</h4>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mb-4">
                                    Jika Anda ingin menyimpan salinan PDF yang telah diproteksi password dan melacak verifikasinya langsung, silakan unggah berkas di bawah.
                                </p>

                                <form onSubmit={handleUploadSubmit} className="space-y-4">
                                    <div>
                                        <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">Pilih File PDF</label>
                                        <input
                                            type="file"
                                            accept="application/pdf"
                                            onChange={(e) => setUploadFile(e.target.files[0])}
                                            className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:outline-none"
                                            required
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">Konfirmasi Password Parafrase</label>
                                        <input
                                            type="password"
                                            value={uploadPassword}
                                            onChange={(e) => setUploadPassword(e.target.value)}
                                            placeholder="Masukkan password kembali..."
                                            className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                            required
                                        />
                                    </div>

                                    <button
                                        type="submit"
                                        disabled={isProcessing}
                                        className="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition-colors text-sm shadow-md disabled:opacity-50"
                                    >
                                        Upload & Amankan PDF
                                    </button>
                                </form>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
