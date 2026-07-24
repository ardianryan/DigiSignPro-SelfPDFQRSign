import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    className = '',
}) {
    const user = usePage().props.auth.user;
    const settings = usePage().props.settings || {};
    const maxPrefixLen = settings.max_prefix_length || 3;

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name || '',
            email: user.email || '',
            position: user.position || '',
            signature_prefix: user.signature_prefix || 'DS',
        });

    const submit = (e) => {
        e.preventDefault();
        patch(route('profile.update'));
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-slate-900 dark:text-gray-100">
                    Informasi Dasar
                </h2>

                <p className="mt-1 text-sm text-slate-600 dark:text-gray-400">
                    Kelola informasi akun dan preferensi tanda tangan Anda.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div>
                    <InputLabel htmlFor="name" value="Nama Lengkap" />

                    <TextInput
                        id="name"
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        isFocused
                        autoComplete="name"
                    />

                    <InputError className="mt-2" message={errors.name} />
                </div>

                <div>
                    <InputLabel htmlFor="email" value="Email" />

                    <TextInput
                        id="email"
                        type="email"
                        className="mt-1 block w-full"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                    />

                    <InputError className="mt-2" message={errors.email} />
                </div>

                <div>
                    <InputLabel htmlFor="position" value="Jabatan / Posisi" />

                    <TextInput
                        id="position"
                        className="mt-1 block w-full"
                        value={data.position}
                        onChange={(e) => setData('position', e.target.value)}
                        autoComplete="organization-title"
                    />

                    <InputError className="mt-2" message={errors.position} />
                </div>

                <div>
                    <InputLabel htmlFor="role" value="Role" />
                    <TextInput
                        id="role"
                        className="mt-1 block w-full bg-slate-50 dark:bg-gray-700"
                        value={user.role ? user.role.charAt(0).toUpperCase() + user.role.slice(1) : 'User'}
                        disabled
                    />
                </div>

                <div className="pt-4 border-t border-slate-100 dark:border-gray-700">
                    <InputLabel
                        htmlFor="signature_prefix"
                        value={`Prefix Tanda Tangan (2-${maxPrefixLen} Huruf)`}
                    />
                    <p className="mt-1 text-xs text-slate-500 dark:text-gray-400 mb-2">
                        Digunakan sebagai awalan kode verifikasi dokumen Anda (Contoh: DS-2024...).
                    </p>
                    <TextInput
                        id="signature_prefix"
                        className="mt-1 block w-full uppercase"
                        value={data.signature_prefix}
                        onChange={(e) =>
                            setData('signature_prefix', e.target.value.toUpperCase())
                        }
                        maxLength={maxPrefixLen}
                        minLength={2}
                        required
                    />
                    <InputError className="mt-2" message={errors.signature_prefix} />
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div>
                        <p className="mt-2 text-sm text-gray-800 dark:text-gray-200">
                            Email Anda belum diverifikasi.
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100 dark:focus:ring-offset-gray-800"
                            >
                                Kirim ulang email verifikasi.
                            </Link>
                        </p>

                        {status === 'verification-link-sent' && (
                            <div className="mt-2 text-sm font-medium text-green-600 dark:text-green-400">
                                Link verifikasi baru telah dikirim ke email Anda.
                            </div>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <PrimaryButton disabled={processing}>Simpan Perubahan</PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            Tersimpan.
                        </p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
