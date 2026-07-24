import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function AuthenticatedLayout({ header, children }) {
    const user = usePage().props.auth.user;
    const settings = usePage().props.settings || {};
    const appName = settings.app_name || 'DigiSign Pro';
    const appLogo = settings.app_logo || null;

    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [openMenus, setOpenMenus] = useState(() => {
        const path = window.location.pathname;
        const initial = [];
        if (path.startsWith('/admin/users') || path === '/history') initial.push('management');
        if (path.startsWith('/sign/')) initial.push('services');
        if (path.startsWith('/admin/settings') || path.startsWith('/admin/storage') || path.startsWith('/admin/backup')) initial.push('system');
        if (path === '/profile') initial.push('account');
        return initial;
    });

    const toggleMenu = (menu) => {
        if (openMenus.includes(menu)) {
            setOpenMenus(openMenus.filter((m) => m !== menu));
        } else {
            setOpenMenus([menu]);
        }
    };

    const isCurrent = (routeNames) => {
        return routeNames.some(name => route().current(name));
    };

    return (
        <div className="flex h-screen bg-slate-50 dark:bg-gray-900 overflow-hidden">
            {/* Mobile Backdrop */}
            {sidebarOpen && (
                <div
                    onClick={() => setSidebarOpen(false)}
                    className="fixed inset-0 bg-slate-900 bg-opacity-50 z-20 md:hidden transition-opacity duration-300"
                ></div>
            )}

            {/* Sidebar */}
            <aside
                className={`fixed inset-y-0 left-0 z-30 w-64 bg-slate-900 text-white flex flex-col transition-transform duration-300 transform md:relative md:translate-x-0 ${
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                {/* Sidebar Header / Logo */}
                <div className="p-6 border-b border-slate-800 flex items-center justify-between md:justify-center">
                    {appLogo ? (
                        <img src={`/${appLogo}`} alt={appName} className="h-10 w-auto object-contain" />
                    ) : (
                        <h1 className="text-2xl font-bold tracking-wider text-blue-500">
                            DIGI<span className="text-white">SIGN</span>
                        </h1>
                    )}

                    {/* Mobile Close Button */}
                    <button onClick={() => setSidebarOpen(false)} className="md:hidden text-slate-400 hover:text-white focus:outline-none">
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                {/* Sidebar Navigation */}
                <nav className="flex-1 overflow-y-auto py-4 scrollbar-thin">
                    <ul className="space-y-2 px-3">
                        {/* Dashboard */}
                        <li>
                            <Link
                                href={route('dashboard')}
                                className={`flex items-center px-4 py-3 rounded-lg hover:bg-slate-800 transition-colors ${
                                    route().current('dashboard') ? 'bg-blue-600 text-white' : 'text-slate-400 hover:text-white'
                                }`}
                            >
                                <svg className="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                                </svg>
                                Dashboard
                            </Link>
                        </li>

                        {/* Group: Manajemen (Admin only) */}
                        {user.role === 'admin' && (
                            <li className="space-y-1">
                                <button
                                    onClick={() => toggleMenu('management')}
                                    className={`w-full flex items-center justify-between px-4 py-3 rounded-lg hover:bg-slate-800 transition-colors ${
                                        isCurrent(['admin.users.index', 'history.index']) ? 'text-white bg-slate-800' : 'text-slate-400 hover:text-white'
                                    }`}
                                >
                                    <div className="flex items-center">
                                        <svg className="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                        </svg>
                                        <span className="text-sm font-medium">Manajemen</span>
                                    </div>
                                    <svg
                                        className={`w-4 h-4 transition-transform duration-200 ${openMenus.includes('management') ? 'rotate-180' : ''}`}
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                                {openMenus.includes('management') && (
                                    <div className="pl-12 space-y-1 transition-all duration-200">
                                        <Link
                                            href={route('admin.users.index')}
                                            className={`block py-2 text-sm transition-colors ${
                                                route().current('admin.users.index') ? 'text-blue-500 font-medium' : 'text-slate-500 hover:text-white'
                                            }`}
                                        >
                                            Manajemen User
                                        </Link>
                                        <Link
                                            href={route('history.index')}
                                            className={`block py-2 text-sm transition-colors ${
                                                route().current('history.index') ? 'text-blue-500 font-medium' : 'text-slate-500 hover:text-white'
                                            }`}
                                        >
                                            Semua Riwayat
                                        </Link>
                                    </div>
                                )}
                            </li>
                        )}

                        {/* Group: Layanan TTE */}
                        <li className="space-y-1">
                            <button
                                onClick={() => toggleMenu('services')}
                                className={`w-full flex items-center justify-between px-4 py-3 rounded-lg hover:bg-slate-800 transition-colors ${
                                    isCurrent(['sign.single.create', 'sign.bulk.create', 'sign.qr.index', 'sign.qr.create']) ? 'text-white bg-slate-800' : 'text-slate-400 hover:text-white'
                                }`}
                            >
                                <div className="flex items-center">
                                    <svg className="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <span className="text-sm font-medium">Layanan TTE</span>
                                </div>
                                <svg
                                    className={`w-4 h-4 transition-transform duration-200 ${openMenus.includes('services') ? 'rotate-180' : ''}`}
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            {openMenus.includes('services') && (
                                <div className="pl-12 space-y-1">
                                    <Link
                                        href={route('sign.single.create')}
                                        className={`block py-2 text-sm transition-colors ${
                                            route().current('sign.single.create') ? 'text-blue-500 font-medium' : 'text-slate-500 hover:text-white'
                                        }`}
                                    >
                                        Single Sign
                                    </Link>
                                    <Link
                                        href={route('sign.bulk.create')}
                                        className={`block py-2 text-sm transition-colors ${
                                            route().current('sign.bulk.create') ? 'text-blue-500 font-medium' : 'text-slate-500 hover:text-white'
                                        }`}
                                    >
                                        Bulk Sign (Massal)
                                    </Link>
                                    <Link
                                        href={route('sign.qr.index')}
                                        className={`block py-2 text-sm transition-colors ${
                                            route().current('sign.qr.index') || route().current('sign.qr.create') ? 'text-blue-500 font-medium' : 'text-slate-500 hover:text-white'
                                        }`}
                                    >
                                        TTE QR (Manual)
                                    </Link>
                                </div>
                            )}
                        </li>

                        {/* Group: Sistem & Tools (Admin only) */}
                        {user.role === 'admin' && (
                            <li className="space-y-1">
                                <button
                                    onClick={() => toggleMenu('system')}
                                    className={`w-full flex items-center justify-between px-4 py-3 rounded-lg hover:bg-slate-800 transition-colors ${
                                        isCurrent(['admin.settings.edit', 'admin.storage.index', 'admin.backup.index']) ? 'text-white bg-slate-800' : 'text-slate-400 hover:text-white'
                                    }`}
                                >
                                    <div className="flex items-center">
                                        <svg className="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                        <span className="text-sm font-medium">Sistem & Tools</span>
                                    </div>
                                    <svg
                                        className={`w-4 h-4 transition-transform duration-200 ${openMenus.includes('system') ? 'rotate-180' : ''}`}
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                                {openMenus.includes('system') && (
                                    <div className="pl-12 space-y-1">
                                        <Link
                                            href={route('admin.settings.edit')}
                                            className={`block py-2 text-sm transition-colors ${
                                                route().current('admin.settings.edit') ? 'text-blue-500 font-medium' : 'text-slate-500 hover:text-white'
                                            }`}
                                        >
                                            Pengaturan
                                        </Link>
                                        <Link
                                            href={route('admin.storage.index')}
                                            className={`block py-2 text-sm transition-colors ${
                                                route().current('admin.storage.index') ? 'text-blue-500 font-medium' : 'text-slate-500 hover:text-white'
                                            }`}
                                        >
                                            Manajemen Storage
                                        </Link>
                                        <Link
                                            href={route('admin.backup.index')}
                                            className={`block py-2 text-sm transition-colors ${
                                                route().current('admin.backup.index') ? 'text-blue-500 font-medium' : 'text-slate-500 hover:text-white'
                                            }`}
                                        >
                                            Backup & Restore
                                        </Link>
                                    </div>
                                )}
                            </li>
                        )}

                        {/* Group: Akun Saya */}
                        <li className="space-y-1">
                            <button
                                onClick={() => toggleMenu('account')}
                                className={`w-full flex items-center justify-between px-4 py-3 rounded-lg hover:bg-slate-800 transition-colors ${
                                    isCurrent(['profile.edit', 'history.index']) && user.role !== 'admin' ? 'text-white bg-slate-800' : 'text-slate-400 hover:text-white'
                                }`}
                            >
                                <div className="flex items-center">
                                    <svg className="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    <span className="text-sm font-medium">Akun Saya</span>
                                </div>
                                <svg
                                    className={`w-4 h-4 transition-transform duration-200 ${openMenus.includes('account') ? 'rotate-180' : ''}`}
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            {openMenus.includes('account') && (
                                <div className="pl-12 space-y-1">
                                    <Link
                                        href={route('profile.edit')}
                                        className={`block py-2 text-sm transition-colors ${
                                            route().current('profile.edit') ? 'text-blue-500 font-medium' : 'text-slate-500 hover:text-white'
                                        }`}
                                    >
                                        Profil Saya
                                    </Link>
                                    {user.role !== 'admin' && (
                                        <Link
                                            href={route('history.index')}
                                            className={`block py-2 text-sm transition-colors ${
                                                route().current('history.index') ? 'text-blue-500 font-medium' : 'text-slate-500 hover:text-white'
                                            }`}
                                        >
                                            Riwayat Saya
                                        </Link>
                                    )}
                                </div>
                            )}
                        </li>
                    </ul>
                </nav>

                {/* Sidebar Footer User Info */}
                <div className="p-4 border-t border-slate-800">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-full bg-slate-700 flex items-center justify-center text-white font-bold">
                            {user.name ? user.name.charAt(0).toUpperCase() : 'U'}
                        </div>
                        <div className="overflow-hidden">
                            <p className="text-sm font-medium text-white truncate">{user.name}</p>
                            <p className="text-xs text-slate-400 truncate">{user.position || 'Staff'}</p>
                        </div>
                    </div>
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="mt-4 block w-full py-2 px-4 bg-red-600 hover:bg-red-700 text-white text-center rounded-lg text-sm transition-colors font-semibold"
                    >
                        Logout
                    </Link>
                </div>
            </aside>

            {/* Main Content Area */}
            <main className="flex-1 h-screen overflow-y-auto bg-slate-50 p-4 md:p-8 dark:bg-gray-900 dark:text-gray-100">
                {/* Mobile Header */}
                <div className="md:hidden flex items-center justify-between mb-6 bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-slate-200 dark:border-gray-700">
                    <button onClick={() => setSidebarOpen(!sidebarOpen)} className="text-slate-500 dark:text-gray-300 hover:text-slate-600 focus:outline-none">
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    <span className="font-bold text-lg text-slate-700 dark:text-white">{appName}</span>
                    <div className="w-6"></div>
                </div>

                {header && (
                    <header className="mb-6">
                        <div className="max-w-7xl mx-auto">{header}</div>
                    </header>
                )}

                <div className="max-w-7xl mx-auto">{children}</div>
            </main>
        </div>
    );
}
