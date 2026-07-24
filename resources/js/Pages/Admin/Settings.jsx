import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Swal from 'sweetalert2';

export default function Settings({ auth, settings, temp_stats }) {
    const initialSettings = settings || {};
    const tempStats = temp_stats || { file_count: 0, size_human: '0 KB' };

    const [activeTab, setActiveTab] = useState('general');
    const [isTestingS3, setIsTestingS3] = useState(false);
    const [logoFile, setLogoFile] = useState(null);
    const [logoPreview, setLogoPreview] = useState(initialSettings.app_logo ? `/${initialSettings.app_logo}` : null);

    const [formData, setFormData] = useState({
        app_name: initialSettings.app_name || 'DigiSign Pro',
        maintenance_mode: initialSettings.maintenance_mode ? 1 : 0,
        registration_open: initialSettings.registration_open ? 1 : 0,
        max_upload_size_mb: initialSettings.max_upload_size ? Math.round(initialSettings.max_upload_size / (1024 * 1024)) : 10,
        max_upload_size_bulk_mb: initialSettings.max_upload_size_bulk ? Math.round(initialSettings.max_upload_size_bulk / (1024 * 1024)) : 50,
        max_prefix_length: initialSettings.max_prefix_length || 3,
        timezone: initialSettings.timezone || 'Asia/Jakarta',
        storage_mode: initialSettings.storage_mode || 'local',
        s3_bucket: initialSettings.s3_bucket || '',
        s3_region: initialSettings.s3_region || 'us-east-1',
        s3_access_key: initialSettings.s3_access_key || '',
        s3_secret_key: initialSettings.s3_secret_key || '',
        s3_endpoint: initialSettings.s3_endpoint || '',
        s3_public_url: initialSettings.s3_public_url || '',
        s3_directory: initialSettings.s3_directory || 'digisign/'
    });

    const handleLogoChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            setLogoFile(file);
            setLogoPreview(URL.createObjectURL(file));
        }
    };

    const handleDeleteLogo = () => {
        Swal.fire({
            title: 'Hapus Logo?',
            text: 'Logo aplikasi akan diubah kembali ke inisial default.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                router.delete(route('admin.settings.delete_logo'), {
                    onSuccess: () => {
                        setLogoFile(null);
                        setLogoPreview(null);
                        Swal.fire('Terhapus!', 'Logo berhasil dihapus.', 'success');
                    }
                });
            }
        });
    };

    const handleClearTemp = () => {
        Swal.fire({
            title: 'Bersihkan Folder Temp?',
            text: 'Semua berkas sisa proses upload/zip yang tertinggal akan dihapus permanen untuk menghemat ruang disk.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, bersihkan!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                router.post(route('admin.settings.clear_temp'), {}, {
                    onSuccess: () => Swal.fire('Bersih!', 'Folder temp berhasil dikosongkan.', 'success')
                });
            }
        });
    };

    const testS3Connection = async () => {
        if (!formData.s3_bucket || !formData.s3_region || !formData.s3_access_key || !formData.s3_secret_key) {
            Swal.fire('Peringatan', 'Lengkapi Bucket, Region, Access Key, dan Secret Key untuk melakukan pengujian.', 'warning');
            return;
        }

        setIsTestingS3(true);
        Swal.fire({
            title: 'Menguji Koneksi S3/R2',
            text: 'Mencoba membuat & menghapus berkas tes...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        try {
            const response = await fetch(route('admin.settings.test_s3'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    s3_bucket: formData.s3_bucket,
                    s3_region: formData.s3_region,
                    s3_access_key: formData.s3_access_key,
                    s3_secret_key: formData.s3_secret_key,
                    s3_endpoint: formData.s3_endpoint
                })
            });

            const result = await response.json();
            setIsTestingS3(false);
            Swal.close();

            if (result.status === 'success') {
                Swal.fire('Sukses!', result.message, 'success');
            } else {
                Swal.fire('Gagal', result.message, 'error');
            }
        } catch (err) {
            setIsTestingS3(false);
            Swal.close();
            Swal.fire('Error', 'Terjadi kesalahan jaringan: ' + err.message, 'error');
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        // Use Form Data for file upload
        const data = new FormData();
        data.append('_method', 'PUT'); // Spoof PUT request for file upload
        data.append('app_name', formData.app_name);
        data.append('maintenance_mode', formData.maintenance_mode);
        data.append('registration_open', formData.registration_open);
        data.append('max_upload_size_mb', formData.max_upload_size_mb);
        data.append('max_upload_size_bulk_mb', formData.max_upload_size_bulk_mb);
        data.append('max_prefix_length', formData.max_prefix_length);
        data.append('timezone', formData.timezone);
        data.append('storage_mode', formData.storage_mode);
        data.append('s3_bucket', formData.s3_bucket);
        data.append('s3_region', formData.s3_region);
        data.append('s3_access_key', formData.s3_access_key);
        data.append('s3_secret_key', formData.s3_secret_key);
        data.append('s3_endpoint', formData.s3_endpoint);
        data.append('s3_public_url', formData.s3_public_url);
        data.append('s3_directory', formData.s3_directory);

        if (logoFile) {
            data.append('app_logo', logoFile);
        }

        router.post(route('admin.settings.update'), data, {
            onSuccess: () => Swal.fire('Berhasil!', 'Pengaturan aplikasi disimpan.', 'success'),
            onError: (err) => Swal.fire('Gagal', Object.values(err).join('<br>'), 'error')
        });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-slate-800 dark:text-slate-200">Pengaturan Sistem</h2>}
        >
            <Head title="Pengaturan Sistem" />

            <div className="py-6">
                <div className="mx-auto max-w-4xl">
                    <div className="mb-6">
                        <p className="text-slate-500 dark:text-slate-400">Atur profil aplikasi, batasan file, dan konfigurasi API Object Storage (S3/Cloudflare R2).</p>
                    </div>

                    {/* Tabs */}
                    <div className="flex border-b border-slate-200 dark:border-gray-700 mb-6 gap-6">
                        <button
                            onClick={() => setActiveTab('general')}
                            className={`pb-3 font-semibold text-sm transition-colors border-b-2 ${
                                activeTab === 'general'
                                    ? 'border-blue-600 text-blue-600 dark:text-blue-400'
                                    : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'
                            }`}
                        >
                            Pengaturan Umum
                        </button>
                        <button
                            onClick={() => setActiveTab('storage')}
                            className={`pb-3 font-semibold text-sm transition-colors border-b-2 ${
                                activeTab === 'storage'
                                    ? 'border-blue-600 text-blue-600 dark:text-blue-400'
                                    : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'
                            }`}
                        >
                            Penyimpanan S3 / R2
                        </button>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Tab General */}
                        {activeTab === 'general' && (
                            <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700 p-6 space-y-6">
                                <h3 className="text-lg font-bold text-slate-800 dark:text-white pb-3 border-b border-slate-100 dark:border-gray-700">Profil Aplikasi</h3>
                                
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">Nama Aplikasi</label>
                                        <input
                                            type="text"
                                            value={formData.app_name}
                                            onChange={(e) => setFormData({ ...formData, app_name: e.target.value })}
                                            className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                            required
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">Logo Aplikasi</label>
                                        <div className="flex items-center gap-4">
                                            {logoPreview ? (
                                                <div className="relative group">
                                                    <img src={logoPreview} alt="App Logo" className="h-12 w-auto object-contain border border-slate-200 rounded p-1 bg-white" />
                                                    <button
                                                        type="button"
                                                        onClick={handleDeleteLogo}
                                                        className="absolute -top-2 -right-2 bg-red-600 text-white rounded-full p-0.5 hover:bg-red-700 shadow-md"
                                                    >
                                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path>
                                                        </svg>
                                                    </button>
                                                </div>
                                            ) : (
                                                <div className="h-12 w-12 border-2 border-dashed border-slate-300 dark:border-gray-600 rounded flex items-center justify-center text-xs text-slate-400">
                                                    No Logo
                                                </div>
                                            )}
                                            <input
                                                type="file"
                                                accept="image/*"
                                                id="logo_input"
                                                className="hidden"
                                                onChange={handleLogoChange}
                                            />
                                            <label
                                                htmlFor="logo_input"
                                                className="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg cursor-pointer"
                                            >
                                                Ganti Logo
                                            </label>
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">Batas Upload Single (MB)</label>
                                        <input
                                            type="number"
                                            value={formData.max_upload_size_mb}
                                            onChange={(e) => setFormData({ ...formData, max_upload_size_mb: e.target.value })}
                                            className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">Batas Upload Bulk ZIP (MB)</label>
                                        <input
                                            type="number"
                                            value={formData.max_upload_size_bulk_mb}
                                            onChange={(e) => setFormData({ ...formData, max_upload_size_bulk_mb: e.target.value })}
                                            className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">Registrasi Akun Baru</label>
                                        <select
                                            value={formData.registration_open}
                                            onChange={(e) => setFormData({ ...formData, registration_open: parseInt(e.target.value) })}
                                            className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                        >
                                            <option value={1}>Aktif (Pengunjung dapat mendaftar sendiri)</option>
                                            <option value={0}>Nonaktif (Hanya Admin yang dapat membuat user)</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">Mode Pemeliharaan (Maintenance)</label>
                                        <select
                                            value={formData.maintenance_mode}
                                            onChange={(e) => setFormData({ ...formData, maintenance_mode: parseInt(e.target.value) })}
                                            className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                        >
                                            <option value={0}>Tidak Aktif (Aplikasi Berjalan Normal)</option>
                                            <option value={1}>Aktif (Hanya Admin yang dapat mengakses sistem)</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">Maks. Panjang Prefix Tanda Tangan</label>
                                        <input
                                            type="number"
                                            min={2}
                                            max={9}
                                            value={formData.max_prefix_length}
                                            onChange={(e) => setFormData({ ...formData, max_prefix_length: parseInt(e.target.value) || 3 })}
                                            className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                        />
                                        <p className="text-xs text-slate-400 mt-1">2–9 huruf. Digunakan pada profil user & kode verifikasi.</p>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">Zona Waktu</label>
                                        <select
                                            value={formData.timezone}
                                            onChange={(e) => setFormData({ ...formData, timezone: e.target.value })}
                                            className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                        >
                                            <option value="Asia/Jakarta">Asia/Jakarta (WIB)</option>
                                            <option value="Asia/Makassar">Asia/Makassar (WITA)</option>
                                            <option value="Asia/Jayapura">Asia/Jayapura (WIT)</option>
                                            <option value="UTC">UTC</option>
                                        </select>
                                    </div>
                                </div>

                                <div className="pt-6 border-t border-slate-100 dark:border-gray-700">
                                    <h4 className="font-bold text-slate-800 dark:text-white mb-2">Pembersihan Disk</h4>
                                    <p className="text-xs text-slate-400 dark:text-gray-400 mb-3">
                                        Hapus folder temporary (upload, update package, preview) untuk melegakan storage server.
                                    </p>
                                    <div className="mb-4 flex flex-wrap gap-3 text-xs">
                                        <span className="inline-flex items-center px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-gray-700 text-slate-700 dark:text-slate-200 font-semibold">
                                            {tempStats.file_count ?? 0} file temp
                                        </span>
                                        <span className="inline-flex items-center px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-gray-700 text-slate-700 dark:text-slate-200 font-semibold">
                                            {tempStats.size_human || '0 KB'}
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={handleClearTemp}
                                        className="py-2 px-4 bg-red-50 text-red-600 dark:bg-red-950/20 dark:text-red-400 border border-red-200 dark:border-red-800 hover:bg-red-100 font-bold rounded-lg text-xs transition-colors"
                                    >
                                        Bersihkan Temp Folder
                                    </button>
                                </div>
                            </div>
                        )}

                        {/* Tab Storage */}
                        {activeTab === 'storage' && (
                            <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700 p-6 space-y-6">
                                <h3 className="text-lg font-bold text-slate-800 dark:text-white pb-3 border-b border-slate-100 dark:border-gray-700">Penyimpanan Object Storage S3</h3>
                                
                                <div className="space-y-4">
                                    <div>
                                        <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">Metode Penyimpanan Aktif</label>
                                        <select
                                            value={formData.storage_mode}
                                            onChange={(e) => setFormData({ ...formData, storage_mode: e.target.value })}
                                            className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 focus:outline-none"
                                        >
                                            <option value="local">Lokal Server Saja (Simpan di public/uploads)</option>
                                            <option value="s3">S3 Cloud Storage Saja (AWS, Cloudflare R2, Minio)</option>
                                            <option value="both">Keduanya (Salinan lokal + S3 Cloud Storage)</option>
                                        </select>
                                    </div>

                                    {formData.storage_mode !== 'local' && (
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-slate-100 dark:border-gray-700">
                                            <div>
                                                <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">S3 Bucket Name</label>
                                                <input
                                                    type="text"
                                                    value={formData.s3_bucket}
                                                    onChange={(e) => setFormData({ ...formData, s3_bucket: e.target.value })}
                                                    className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                                    placeholder="Contoh: digisign-bucket"
                                                />
                                            </div>

                                            <div>
                                                <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">S3 Region</label>
                                                <input
                                                    type="text"
                                                    value={formData.s3_region}
                                                    onChange={(e) => setFormData({ ...formData, s3_region: e.target.value })}
                                                    className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                                    placeholder="Contoh: us-east-1, ap-southeast-1"
                                                />
                                            </div>

                                            <div>
                                                <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">S3 Access Key ID</label>
                                                <input
                                                    type="text"
                                                    value={formData.s3_access_key}
                                                    onChange={(e) => setFormData({ ...formData, s3_access_key: e.target.value })}
                                                    className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                                    placeholder="S3 Access Key"
                                                />
                                            </div>

                                            <div>
                                                <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">S3 Secret Access Key</label>
                                                <input
                                                    type="password"
                                                    value={formData.s3_secret_key}
                                                    onChange={(e) => setFormData({ ...formData, s3_secret_key: e.target.value })}
                                                    className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                                    placeholder="S3 Secret Key"
                                                />
                                            </div>

                                            <div>
                                                <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">Custom Endpoint (Opsional / Wajib untuk R2)</label>
                                                <input
                                                    type="text"
                                                    value={formData.s3_endpoint}
                                                    onChange={(e) => setFormData({ ...formData, s3_endpoint: e.target.value })}
                                                    className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                                    placeholder="Contoh: https://<account_id>.r2.cloudflarestorage.com"
                                                />
                                            </div>

                                            <div>
                                                <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">Custom Public URL (Opsional)</label>
                                                <input
                                                    type="text"
                                                    value={formData.s3_public_url}
                                                    onChange={(e) => setFormData({ ...formData, s3_public_url: e.target.value })}
                                                    className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                                    placeholder="Contoh: https://pub-xxxx.r2.dev"
                                                />
                                            </div>

                                            <div>
                                                <label className="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">Direktori Penyimpanan</label>
                                                <input
                                                    type="text"
                                                    value={formData.s3_directory}
                                                    onChange={(e) => setFormData({ ...formData, s3_directory: e.target.value })}
                                                    className="w-full border border-slate-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                                    placeholder="Contoh: digisign/ atau uploads/signatures/"
                                                />
                                            </div>

                                            <div className="flex items-end">
                                                <button
                                                    type="button"
                                                    onClick={testS3Connection}
                                                    disabled={isTestingS3}
                                                    className="w-full bg-slate-800 dark:bg-blue-600 hover:bg-slate-900 dark:hover:bg-blue-700 text-white font-bold py-2.5 px-4 rounded-lg text-sm transition-colors shadow-sm disabled:opacity-50"
                                                >
                                                    Uji Koneksi S3 / R2
                                                </button>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}

                        <div className="flex justify-end gap-3">
                            <button
                                type="submit"
                                className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-6 rounded-lg text-sm transition-colors shadow-md"
                            >
                                Simpan Pengaturan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
