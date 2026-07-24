import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function QrList({ auth, signatures }) {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Daftar Layanan TTE QR</h2>}
        >
            <Head title="Riwayat TTE QR" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800 p-6 text-gray-900 dark:text-gray-100">
                        <h3 className="text-lg font-bold mb-4">Layanan TTE QR</h3>
                        <p className="text-gray-600 dark:text-gray-400">Komponen riwayat berkas tanda tangan manual menggunakan generator QR Code.</p>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
