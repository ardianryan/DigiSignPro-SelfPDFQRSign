import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function Single({ auth }) {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Tanda Tangan Single PDF</h2>}
        >
            <Head title="Tanda Tangan Single PDF" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800 p-6 text-gray-900 dark:text-gray-100">
                        <h3 className="text-lg font-bold mb-4">Tanda Tangan Single PDF</h3>
                        <p className="text-gray-600 dark:text-gray-400">Komponen drag-and-drop file PDF untuk digambar QR Code / tanda tangan digital di koordinat tertentu.</p>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
