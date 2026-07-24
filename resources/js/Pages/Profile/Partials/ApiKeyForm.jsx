import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import Swal from 'sweetalert2';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import PrimaryButton from '@/Components/PrimaryButton';

export default function ApiKeyForm({
    apiKey,
    apiKeyCreatedAt,
    apiBaseUrl,
    quickapiUrl,
    publicQuickapiUrl,
    className = '',
}) {
    const [visible, setVisible] = useState(false);
    const [copied, setCopied] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const copyKey = async () => {
        try {
            await navigator.clipboard.writeText(apiKey || '');
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            Swal.fire('Gagal', 'Tidak dapat menyalin ke clipboard.', 'error');
        }
    };

    const regenerate = (e) => {
        e.preventDefault();
        if (!data.password) {
            Swal.fire('Password wajib', 'Isi password untuk regenerasi API key.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Regenerasi API Key?',
            text: 'Key lama akan langsung nonaktif. Integrasi yang memakai key lama harus diperbarui.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, regenerate',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#dc2626',
        }).then((result) => {
            if (!result.isConfirmed) return;

            post(route('profile.api_key.regenerate'), {
                preserveScroll: true,
                onSuccess: () => {
                    reset('password');
                    Swal.fire('Berhasil', 'API key baru telah dibuat.', 'success');
                },
                onError: () => {
                    Swal.fire('Gagal', 'Periksa password Anda.', 'error');
                },
            });
        });
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-slate-900 dark:text-gray-100">REST API Key</h2>
                <p className="mt-1 text-sm text-slate-600 dark:text-gray-400">
                    Gunakan API key ini untuk integrasi server-to-server. Base URL:{' '}
                    <code className="text-xs bg-slate-100 dark:bg-gray-700 px-1.5 py-0.5 rounded">{apiBaseUrl}</code>
                </p>
            </header>

            <div className="mt-6 space-y-4">
                <div>
                    <InputLabel value="API Key Anda" />
                    <div className="mt-1 flex gap-2">
                        <input
                            type={visible ? 'text' : 'password'}
                            readOnly
                            value={apiKey || '—'}
                            className="flex-1 border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-xs font-mono focus:outline-none"
                        />
                        <button
                            type="button"
                            onClick={() => setVisible((v) => !v)}
                            className="px-3 py-2 text-xs font-semibold rounded-lg border border-slate-300 dark:border-gray-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-gray-700"
                        >
                            {visible ? 'Sembunyi' : 'Tampil'}
                        </button>
                        <button
                            type="button"
                            onClick={copyKey}
                            className="px-3 py-2 text-xs font-semibold rounded-lg bg-slate-800 text-white hover:bg-slate-900"
                        >
                            {copied ? 'Tersalin' : 'Salin'}
                        </button>
                    </div>
                    {apiKeyCreatedAt && (
                        <p className="text-xs text-slate-400 mt-1">Dibuat: {new Date(apiKeyCreatedAt).toLocaleString('id-ID')}</p>
                    )}
                </div>

                <div className="flex flex-wrap gap-2">
                    <a
                        href={quickapiUrl}
                        className="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg"
                    >
                        Unduh quickapi.md
                    </a>
                    <a
                        href={publicQuickapiUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1.5 px-4 py-2 border border-slate-300 dark:border-gray-600 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg hover:bg-slate-50 dark:hover:bg-gray-700"
                    >
                        Buka di tab baru
                    </a>
                </div>

                <div className="p-3 bg-slate-50 dark:bg-gray-900 rounded-lg border border-slate-200 dark:border-gray-700 text-xs text-slate-600 dark:text-slate-400 space-y-1">
                    <p className="font-semibold text-slate-800 dark:text-slate-200">Contoh header</p>
                    <pre className="font-mono whitespace-pre-wrap break-all">Authorization: Bearer {apiKey || '<API_KEY>'}</pre>
                    <pre className="font-mono whitespace-pre-wrap break-all">X-API-Key: {apiKey || '<API_KEY>'}</pre>
                </div>

                <form onSubmit={regenerate} className="pt-4 border-t border-slate-100 dark:border-gray-700 space-y-3">
                    <p className="text-sm font-medium text-slate-800 dark:text-slate-200">Regenerasi API Key</p>
                    <div>
                        <InputLabel htmlFor="api_password" value="Konfirmasi Password" />
                        <TextInput
                            id="api_password"
                            type="password"
                            className="mt-1 block w-full"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            autoComplete="current-password"
                        />
                        <InputError message={errors.password} className="mt-1" />
                    </div>
                    <PrimaryButton disabled={processing} className="bg-red-600 hover:bg-red-700">
                        Regenerasi API Key
                    </PrimaryButton>
                </form>
            </div>
        </section>
    );
}
