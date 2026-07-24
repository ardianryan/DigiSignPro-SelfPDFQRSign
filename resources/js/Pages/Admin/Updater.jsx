import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Swal from 'sweetalert2';

export default function Updater({ current_version, migration, legacy_cutover }) {
    const user = usePage().props.auth?.user;
    const [isMigrating, setIsMigrating] = useState(false);
    const [isCuttingOver, setIsCuttingOver] = useState(false);
    const [password, setPassword] = useState('');
    const [cutoverPassword, setCutoverPassword] = useState('');
    const [cutoverConfirm, setCutoverConfirm] = useState(false);
    const [cutoverDone, setCutoverDone] = useState(!!legacy_cutover?.completed);
    const [migrationState, setMigrationState] = useState(
        migration || { has_pending: false, pending: [] }
    );
    const version = current_version || '2.0.0';
    const showCutover = !cutoverDone && (legacy_cutover?.should_offer ?? true);

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const runDbMigration = async (e) => {
        e?.preventDefault?.();

        if (!password.trim()) {
            Swal.fire('Password wajib', 'Isi ulang password admin untuk menjalankan migrasi.', 'warning');
            return;
        }

        const confirm = await Swal.fire({
            title: 'Jalankan Migrasi Database?',
            html: 'Migrasi <strong>harian</strong> (Laravel migrate + adapt kolom). Bukan cutover legacy. Backup DB di production disarankan.',
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
            text: 'Mohon tunggu...',
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

            if (res.status === 422) {
                Swal.fire('Ditolak', data.errors?.password?.[0] || data.message || 'Password salah.', 'error');
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

    const runLegacyCutover = async (e) => {
        e?.preventDefault?.();

        if (!cutoverPassword.trim()) {
            Swal.fire('Password wajib', 'Isi password admin untuk cutover.', 'warning');
            return;
        }
        if (!cutoverConfirm) {
            Swal.fire('Konfirmasi', 'Centang persetujuan: user harus login ulang.', 'warning');
            return;
        }

        const confirm = await Swal.fire({
            title: 'Cutover Legacy → Laravel (SEKALI)?',
            html: `
                <p class="text-sm text-left">Ini <strong>bukan</strong> update harian. Hanya untuk pindah dari DigiSign PHP native ke Laravel.</p>
                <ul class="text-xs text-left mt-2 list-disc pl-4 text-slate-600">
                  <li>Adapt skema DB + migrate</li>
                  <li>Generate API key user</li>
                  <li>Session lama tidak dipindah → semua user login ulang</li>
                  <li>Setelah sukses, tombol ini hilang selamanya</li>
                </ul>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, cutover sekali',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#dc2626',
        });
        if (!confirm.isConfirmed) return;

        setIsCuttingOver(true);
        Swal.fire({
            title: 'Menjalankan Cutover',
            text: 'Mohon tunggu...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
        });

        try {
            const res = await fetch(route('admin.legacy_cutover.run'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    password: cutoverPassword,
                    confirm: cutoverConfirm,
                }),
            });

            const data = await res.json().catch(() => ({}));
            setIsCuttingOver(false);
            Swal.close();

            if (res.status === 422) {
                const msg =
                    data.errors?.password?.[0] ||
                    data.errors?.confirm?.[0] ||
                    data.message ||
                    'Validasi gagal.';
                Swal.fire('Ditolak', msg, 'error');
                return;
            }

            if (res.status === 409 || data.already_done) {
                setCutoverDone(true);
                Swal.fire('Sudah selesai', data.message || 'Cutover pernah dijalankan.', 'info');
                return;
            }

            if (data.status === 'success') {
                setCutoverDone(true);
                setCutoverPassword('');
                setCutoverConfirm(false);
                const msg = (data.message || 'Cutover selesai.').replace(/</g, '&lt;');
                Swal.fire({
                    title: 'Cutover Berhasil',
                    html: `<pre class="text-left text-xs whitespace-pre-wrap max-h-80 overflow-auto bg-slate-50 p-3 rounded border border-slate-200">${msg}</pre>`,
                    icon: 'success',
                    width: 600,
                });
            } else {
                Swal.fire('Gagal', data.message || 'Cutover gagal.', 'error');
            }
        } catch (err) {
            setIsCuttingOver(false);
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
                        Deploy kode via CI/CD. Di sini hanya skema DB (harian) + cutover legacy (sekali).
                    </p>
                </div>
            }
        >
            <Head title="Migrasi Database" />

            <div className="max-w-3xl mx-auto space-y-6">
                {/* Plan summary */}
                <div className="bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 p-5 text-sm space-y-2">
                    <p className="font-bold text-slate-800 dark:text-white">Rencana update</p>
                    <ul className="text-xs text-slate-600 dark:text-slate-400 space-y-1.5 list-disc pl-4">
                        <li>
                            <strong>Kode aplikasi</strong> → CI/CD (git pull / build / deploy). Bukan ZIP.
                        </li>
                        <li>
                            <strong>Migrasi harian</strong> → form di bawah (password admin). Aman diulang.
                        </li>
                        <li>
                            <strong>Pindah dari DigiSign PHP native</strong> → cutover <em>sekali saja</em> (CLI di luar app, atau form sekali pakai).
                        </li>
                        <li>
                            Session PHP native ≠ session Laravel → setelah cutover, semua user <strong>login ulang</strong>.
                        </li>
                    </ul>
                </div>

                {/* One-time legacy cutover */}
                {showCutover ? (
                    <div className="bg-amber-50 border border-amber-200 rounded-xl p-6 space-y-4">
                        <div className="flex items-start gap-3">
                            <div className="p-2 bg-amber-100 rounded-lg text-amber-700">
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div>
                                <h3 className="text-lg font-bold text-amber-900">Cutover Legacy → Laravel (sekali jalan)</h3>
                                <p className="text-sm text-amber-800 mt-1">
                                    {legacy_cutover?.looks_legacy
                                        ? 'DB terdeteksi mirip DigiSign lama. Jalankan cutover sekali, lalu hilang dari menu.'
                                        : 'Belum pernah cutover. Jika ini install Laravel murni (bukan pindahan), Anda bisa abaikan dan hanya pakai Migrasi harian.'}
                                </p>
                                <p className="text-xs text-amber-700 mt-2 font-mono bg-amber-100/80 px-2 py-1 rounded inline-block">
                                    {legacy_cutover?.cli_command || 'php artisan digisign:legacy-cutover'}
                                </p>
                                <p className="text-[11px] text-amber-700 mt-1">
                                    Disarankan lewat CLI di server (di luar request web). Form di bawah alternatif darurat (tetap butuh password admin).
                                </p>
                            </div>
                        </div>

                        <form onSubmit={runLegacyCutover} className="space-y-3 bg-white/70 dark:bg-gray-900/40 p-4 rounded-lg border border-amber-100">
                            <div>
                                <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">
                                    Password Admin <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="password"
                                    value={cutoverPassword}
                                    onChange={(e) => setCutoverPassword(e.target.value)}
                                    className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500 focus:outline-none"
                                    placeholder="Password admin Anda"
                                    required
                                />
                            </div>
                            <label className="flex items-start gap-2 text-xs text-slate-700 dark:text-slate-300">
                                <input
                                    type="checkbox"
                                    checked={cutoverConfirm}
                                    onChange={(e) => setCutoverConfirm(e.target.checked)}
                                    className="mt-0.5 rounded border-slate-300 text-amber-600 focus:ring-amber-500"
                                />
                                <span>
                                    Saya mengerti: session DigiSign lama tidak dipindah; semua user harus{' '}
                                    <strong>login ulang</strong> di Laravel. Operasi ini hanya sekali.
                                </span>
                            </label>
                            <button
                                type="submit"
                                disabled={isCuttingOver || !cutoverPassword || !cutoverConfirm}
                                className="w-full bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white font-bold py-2.5 rounded-lg text-sm"
                            >
                                {isCuttingOver ? 'Menjalankan cutover...' : 'Jalankan Cutover Sekali'}
                            </button>
                        </form>
                    </div>
                ) : (
                    <div className="bg-slate-50 dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-xl px-4 py-3 text-xs text-slate-600 dark:text-slate-400">
                        Cutover legacy sudah selesai (atau tidak diperlukan). Update berikutnya: <strong>CI/CD</strong> +{' '}
                        <strong>Migrasi harian</strong> di bawah.
                    </div>
                )}

                {migrationState?.has_pending ? (
                    <div className="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-xl text-sm text-red-700">
                        Ada migrasi Laravel pending. Isi password admin lalu jalankan migrasi harian.
                    </div>
                ) : (
                    <div className="bg-green-50 border-l-4 border-green-500 p-4 rounded-r-xl text-sm text-green-800">
                        Tidak ada migrasi pending terdeteksi. Migrasi harian tetap bisa dijalankan ulang (aman).
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
                        <div className="mt-6 p-4 bg-slate-50 dark:bg-gray-900 border border-slate-200 dark:border-gray-700 rounded-lg text-xs text-slate-600 dark:text-slate-400 space-y-2">
                            <p className="font-semibold text-slate-800 dark:text-slate-200">Deploy kode</p>
                            <p className="leading-relaxed">
                                Pipeline CI/CD (git pull / build / deploy). Unggah ZIP update sudah dihapus.
                            </p>
                        </div>
                    </div>

                    <div className="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700">
                        <h3 className="text-lg font-semibold text-slate-700 dark:text-slate-200 mb-1">
                            Migrasi Database (harian)
                        </h3>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mb-5">
                            Setelah deploy CI/CD, jalankan ini bila ada migration baru. Wajib password{' '}
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
                                className="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold py-3 px-4 rounded-xl transition-all shadow-md"
                            >
                                {isMigrating ? 'Menjalankan Migrasi...' : 'Jalankan Migrasi Database'}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
