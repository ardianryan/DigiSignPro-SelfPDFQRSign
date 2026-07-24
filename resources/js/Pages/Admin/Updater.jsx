import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Swal from 'sweetalert2';

export default function Updater({ current_version, migration }) {
    const user = usePage().props.auth?.user;
    const [isMigrating, setIsMigrating] = useState(false);
    const [password, setPassword] = useState('');
    const [migrationState, setMigrationState] = useState(
        migration || { has_pending: false, pending: [] }
    );
    const version = current_version || '2.0.0';

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const runDbMigration = async (e) => {
        e?.preventDefault?.();

        if (!password.trim()) {
            Swal.fire('Password wajib', 'Isi ulang password admin untuk menjalankan migrasi.', 'warning');
            return;
        }

        const confirm = await Swal.fire({
            title: 'Jalankan Migrasi Database?',
            html: 'Operasi ini menyesuaikan struktur database (termasuk skema DigiSign lama). Pastikan sudah backup jika di production.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, jalankan migrasi',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#2563eb',
        });
        if (!confirm.isConfirmed) return;

        setIsMigrating(true);
        Swal.fire({
            title: 'Menjalankan Migrasi',
            text: 'Mohon tunggu, jangan tutup halaman ini...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
        });

        try {
            const res = await fetch(route('admin.database.migrate'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ password }),
            });

            const data = await res.json().catch(() => ({}));
            setIsMigrating(false);
            Swal.close();

            // Laravel validation errors
            if (res.status === 422) {
                const msg =
                    data.errors?.password?.[0] ||
                    data.message ||
                    'Validasi gagal. Periksa password admin.';
                Swal.fire('Ditolak', msg, 'error');
                return;
            }

            if (data.status === 'success') {
                setMigrationState({ has_pending: false, pending: [] });
                setPassword('');
                const msg = (data.message || 'Migrasi selesai.').replace(/</g, '&lt;');
                Swal.fire({
                    title: 'Migrasi Berhasil',
                    html: `<pre class="text-left text-xs whitespace-pre-wrap max-h-80 overflow-auto bg-slate-50 p-3 rounded border border-slate-200">${msg}</pre>`,
                    icon: 'success',
                    width: 560,
                });
            } else {
                Swal.fire('Gagal', data.message || 'Migrasi gagal.', 'error');
            }
        } catch (err) {
            setIsMigrating(false);
            Swal.close();
            Swal.fire('Error', err.message, 'error');
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="text-2xl font-bold text-slate-800 dark:text-white">Migrasi Database</h2>
                    <p className="text-slate-500 dark:text-slate-400">
                        Sesuaikan struktur database aplikasi. Deploy kode aplikasi dilakukan via CI/CD.
                    </p>
                </div>
            }
        >
            <Head title="Migrasi Database" />

            <div className="max-w-3xl mx-auto space-y-6">
                {migrationState?.has_pending ? (
                    <div className="bg-red-50 border-l-4 border-red-500 p-6 rounded-r-xl shadow-sm">
                        <div className="flex items-start gap-4">
                            <div className="p-2 bg-red-100 rounded-lg text-red-600">
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                            </div>
                            <div className="flex-1">
                                <h3 className="text-lg font-bold text-red-800">Database Belum Sinkron</h3>
                                <p className="text-sm text-red-600 mt-1">
                                    Ada migrasi pending. Isi password admin di bawah lalu jalankan migrasi.
                                </p>
                                {migrationState.pending?.length > 0 && (
                                    <details className="mt-3 text-xs text-red-700">
                                        <summary className="font-semibold cursor-pointer hover:underline">
                                            Lihat detail ({migrationState.pending.length} item)
                                        </summary>
                                        <ul className="list-disc list-inside mt-2 space-y-1 bg-red-100/50 p-2.5 rounded-lg border border-red-200">
                                            {migrationState.pending.map((item, i) => (
                                                <li key={i}>{item}</li>
                                            ))}
                                        </ul>
                                    </details>
                                )}
                            </div>
                        </div>
                    </div>
                ) : (
                    <div className="bg-green-50 border-l-4 border-green-500 p-4 rounded-r-xl shadow-sm">
                        <div className="flex items-start gap-3">
                            <svg className="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <span className="text-sm font-semibold text-green-800 block">
                                    Struktur database terlihat sinkron
                                </span>
                                <p className="text-xs text-green-700 mt-1">
                                    Anda tetap bisa menjalankan ulang migrasi (aman diulang) setelah deploy CI/CD
                                    atau import data DigiSign lama — wajib isi password admin.
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700 h-fit">
                        <h3 className="text-lg font-semibold text-slate-700 dark:text-slate-200 mb-2">Versi Aplikasi</h3>
                        <div className="flex items-center gap-3">
                            <div className="text-4xl font-bold text-blue-600">{version}</div>
                            <span className="px-3 py-1 bg-slate-100 dark:bg-gray-700 text-slate-600 dark:text-slate-300 text-xs rounded-full font-semibold">
                                Production
                            </span>
                        </div>
                        <div className="mt-6 p-4 bg-slate-50 dark:bg-gray-900 border border-slate-200 dark:border-gray-700 rounded-lg text-sm text-slate-600 dark:text-slate-400 space-y-2">
                            <p className="font-semibold text-slate-800 dark:text-slate-200">Deploy kode aplikasi</p>
                            <p className="text-xs leading-relaxed">
                                Update file aplikasi (kode, asset, dependency) dilakukan lewat pipeline CI/CD
                                (git pull / build / deploy). Unggah paket ZIP sudah tidak didukung.
                            </p>
                        </div>
                    </div>

                    <div className="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700">
                        <h3 className="text-lg font-semibold text-slate-700 dark:text-slate-200 mb-1">
                            Jalankan Migrasi Database
                        </h3>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mb-5">
                            Untuk keamanan, konfirmasikan identitas admin dengan mengisi ulang password akun{' '}
                            <span className="font-semibold text-slate-700 dark:text-slate-200">{user?.email || 'admin'}</span>.
                        </p>

                        <form onSubmit={runDbMigration} className="space-y-4">
                            <div>
                                <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">
                                    Password Admin <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="password"
                                    value={password}
                                    onChange={(e) => setPassword(e.target.value)}
                                    autoComplete="current-password"
                                    placeholder="Masukkan password admin Anda"
                                    className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2.5 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                    required
                                />
                            </div>

                            <button
                                type="submit"
                                disabled={isMigrating || !password}
                                className="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold py-3 px-4 rounded-xl transition-all shadow-md shadow-blue-200/40"
                            >
                                {isMigrating ? 'Menjalankan Migrasi...' : 'Jalankan Migrasi Database'}
                            </button>
                        </form>

                        <div className="mt-5 p-3 bg-amber-50 border border-amber-100 rounded-lg text-xs text-amber-800 leading-relaxed">
                            <strong>Catatan:</strong> Migrasi tidak menghapus data. Disarankan backup DB di production
                            sebelum menjalankan di server live.
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
