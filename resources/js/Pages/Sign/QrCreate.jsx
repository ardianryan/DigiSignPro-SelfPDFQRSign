import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function QrCreate({ auth }) {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Buat TTE QR Baru</h2>}
        >
            <Head title="Buat TTE QR" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800 p-6 text-gray-900 dark:text-gray-100">
                        <h3 className="text-lg font-bold mb-4">Buat TTE QR Baru</h3>
                        <p className="text-gray-600 dark:text-gray-400">Komponen pembuatan QR Code verifikasi dokumen fisik secara dinamis.</p>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
