import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import ApiKeyForm from './Partials/ApiKeyForm';

export default function Edit({
    mustVerifyEmail,
    status,
    apiKey,
    apiKeyCreatedAt,
    apiBaseUrl,
    quickapiUrl,
    publicQuickapiUrl,
}) {
    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="text-3xl font-bold text-slate-800 dark:text-white">Profil Saya</h2>
                    <p className="text-slate-500 dark:text-slate-400 mt-1">
                        Kelola informasi akun, preferensi tanda tangan, dan API key integrasi.
                    </p>
                </div>
            }
        >
            <Head title="Profil Saya" />

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700 p-6">
                    <UpdateProfileInformationForm
                        mustVerifyEmail={mustVerifyEmail}
                        status={status}
                    />
                </div>

                <div className="space-y-8">
                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700 p-6 h-fit">
                        <UpdatePasswordForm />
                    </div>

                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700 p-6 h-fit">
                        <ApiKeyForm
                            apiKey={apiKey}
                            apiKeyCreatedAt={apiKeyCreatedAt}
                            apiBaseUrl={apiBaseUrl}
                            quickapiUrl={quickapiUrl}
                            publicQuickapiUrl={publicQuickapiUrl}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
