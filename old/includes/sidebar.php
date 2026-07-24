<?php
$current_page = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Fetch App Settings for Logo
if (!isset($settings)) {
    $s_sql = "SELECT * FROM app_settings WHERE id = 1";
    $s_result = $conn->query($s_sql);
    $settings = $s_result->fetch_assoc();
}
?>
<!-- Mobile Backdrop -->
<div x-show="sidebarOpen" @click="sidebarOpen = false" x-transition:enter="transition-opacity ease-linear duration-300"
    x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
    x-transition:leave="transition-opacity ease-linear duration-300" x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0" class="fixed inset-0 bg-slate-900 bg-opacity-50 z-20 md:hidden"
    style="display: none;"></div>

<aside
    class="fixed inset-y-0 left-0 z-30 w-64 bg-slate-900 text-white flex flex-col transition-transform duration-300 transform md:relative md:translate-x-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
    <div class="p-6 border-b border-slate-800 flex items-center justify-between md:justify-center">
        <?php if (!empty($settings['app_logo']) && file_exists(__DIR__ . '/../public/' . $settings['app_logo'])): ?>
        <img src="<?php echo BASE_URL . '/' . $settings['app_logo']; ?>" alt="<?php echo $settings['app_name']; ?>"
            class="h-10 w-auto object-contain">
        <?php
else: ?>
        <h1 class="text-2xl font-bold tracking-wider text-blue-500">DIGI<span class="text-white">SIGN</span></h1>
        <?php
endif; ?>

        <!-- Mobile Close Button -->
        <button @click="sidebarOpen = false" class="md:hidden text-slate-400 hover:text-white focus:outline-none">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    <?php
    // Determine which menu should be open based on current page
    $initial_open = "''";
    if (in_array($current_page, ['users', 'history'])) $initial_open = "'management'";
    if (in_array($current_page, ['single', 'bulk', 'qr_list', 'qr_create'])) $initial_open = "'services'";
    if (in_array($current_page, ['settings', 'storage', 'updater', 'backup'])) $initial_open = "'system'";
    if (in_array($current_page, ['profile'])) $initial_open = "'account'";
    ?>

    <nav class="flex-1 overflow-y-auto py-4 sidebar-scroll" x-data="{ 
        openMenus: [<?php echo $initial_open; ?>],
        toggleMenu(menu) {
            if (this.openMenus.includes(menu)) {
                this.openMenus = this.openMenus.filter(m => m !== menu);
            } else {
                this.openMenus = [menu]; // Accordion behavior: only one open at a time
            }
        }
    }">
        <ul class="space-y-2 px-3">
            <!-- Dashboard -->
            <li>
                <a href="<?php echo BASE_URL; ?>/dashboard"
                    class="flex items-center px-4 py-3 rounded-lg hover:bg-slate-800 <?php echo $current_page == 'dashboard' ? 'bg-blue-600 text-white' : 'text-slate-400'; ?> transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z">
                        </path>
                    </svg>
                    Dashboard
                </a>
            </li>

            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <!-- Group: Manajemen -->
            <li class="space-y-1">
                <button @click="toggleMenu('management')" 
                    class="w-full flex items-center justify-between px-4 py-3 rounded-lg hover:bg-slate-800 transition-colors <?php echo in_array($current_page, ['users', 'history']) ? 'text-white' : 'text-slate-400'; ?>">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <span class="text-sm font-medium">Manajemen</span>
                    </div>
                    <svg class="w-4 h-4 transition-transform duration-200" :class="openMenus.includes('management') ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>
                <div x-show="openMenus.includes('management')" x-collapse x-cloak class="pl-12 space-y-1">
                    <a href="<?php echo BASE_URL; ?>/admin/users" class="block py-2 text-sm <?php echo $current_page == 'users' ? 'text-blue-500 font-medium' : 'text-slate-500 hover:text-white'; ?> transition-colors">Manajemen User</a>
                    <a href="<?php echo BASE_URL; ?>/history" class="block py-2 text-sm <?php echo $current_page == 'history' ? 'text-blue-500 font-medium' : 'text-slate-500 hover:text-white'; ?> transition-colors">Semua Riwayat</a>
                </div>
            </li>
            <?php endif; ?>

            <!-- Group: Layanan TTE -->
            <li class="space-y-1">
                <button @click="toggleMenu('services')" 
                    class="w-full flex items-center justify-between px-4 py-3 rounded-lg hover:bg-slate-800 transition-colors <?php echo in_array($current_page, ['single', 'bulk', 'qr_list', 'qr_create']) ? 'text-white' : 'text-slate-400'; ?>">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="text-sm font-medium">Layanan TTE</span>
                    </div>
                    <svg class="w-4 h-4 transition-transform duration-200" :class="openMenus.includes('services') ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>
                <div x-show="openMenus.includes('services')" x-collapse x-cloak class="pl-12 space-y-1">
                    <a href="<?php echo BASE_URL; ?>/sign/single" class="block py-2 text-sm <?php echo $current_page == 'single' ? 'text-blue-500 font-medium' : 'text-slate-500 hover:text-white'; ?> transition-colors">Single Sign</a>
                    <a href="<?php echo BASE_URL; ?>/sign/bulk" class="block py-2 text-sm <?php echo $current_page == 'bulk' ? 'text-blue-500 font-medium' : 'text-slate-500 hover:text-white'; ?> transition-colors">Bulk Sign (Massal)</a>
                    <a href="<?php echo BASE_URL; ?>/sign/qr_list" class="block py-2 text-sm <?php echo in_array($current_page, ['qr_list', 'qr_create']) ? 'text-blue-500 font-medium' : 'text-slate-500 hover:text-white'; ?> transition-colors">TTE QR (Manual)</a>
                </div>
            </li>

            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <!-- Group: Sistem & Tools -->
            <li class="space-y-1">
                <button @click="toggleMenu('system')" 
                    class="w-full flex items-center justify-between px-4 py-3 rounded-lg hover:bg-slate-800 transition-colors <?php echo in_array($current_page, ['settings', 'storage', 'updater', 'backup']) ? 'text-white' : 'text-slate-400'; ?>">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span class="text-sm font-medium">Sistem & Tools</span>
                    </div>
                    <svg class="w-4 h-4 transition-transform duration-200" :class="openMenus.includes('system') ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>
                <div x-show="openMenus.includes('system')" x-collapse x-cloak class="pl-12 space-y-1">
                    <a href="<?php echo BASE_URL; ?>/admin/settings" class="block py-2 text-sm <?php echo $current_page == 'settings' ? 'text-blue-500 font-medium' : 'text-slate-500 hover:text-white'; ?> transition-colors">Pengaturan</a>
                    <a href="<?php echo BASE_URL; ?>/admin/storage" class="block py-2 text-sm <?php echo $current_page == 'storage' ? 'text-blue-500 font-medium' : 'text-slate-500 hover:text-white'; ?> transition-colors">Manajemen Storage</a>
                    <a href="<?php echo BASE_URL; ?>/admin/updater" class="block py-2 text-sm <?php echo $current_page == 'updater' ? 'text-blue-500 font-medium' : 'text-slate-500 hover:text-white'; ?> transition-colors">Update App</a>
                    <a href="<?php echo BASE_URL; ?>/admin/backup" class="block py-2 text-sm <?php echo $current_page == 'backup' ? 'text-blue-500 font-medium' : 'text-slate-500 hover:text-white'; ?> transition-colors">Backup & Restore</a>
                </div>
            </li>
            <?php endif; ?>

            <!-- Group: Akun Saya -->
            <li class="space-y-1">
                <button @click="toggleMenu('account')" 
                    class="w-full flex items-center justify-between px-4 py-3 rounded-lg hover:bg-slate-800 transition-colors <?php echo in_array($current_page, ['profile', 'history']) && (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') ? 'text-white' : 'text-slate-400'; ?>">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        <span class="text-sm font-medium">Akun Saya</span>
                    </div>
                    <svg class="w-4 h-4 transition-transform duration-200" :class="openMenus.includes('account') ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>
                <div x-show="openMenus.includes('account')" x-collapse x-cloak class="pl-12 space-y-1">
                    <a href="<?php echo BASE_URL; ?>/profile" class="block py-2 text-sm <?php echo $current_page == 'profile' ? 'text-blue-500 font-medium' : 'text-slate-500 hover:text-white'; ?> transition-colors">Profil Saya</a>
                    <?php if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'): ?>
                    <a href="<?php echo BASE_URL; ?>/history" class="block py-2 text-sm <?php echo $current_page == 'history' ? 'text-blue-500 font-medium' : 'text-slate-500 hover:text-white'; ?> transition-colors">Riwayat Saya</a>
                    <?php endif; ?>
                </div>
            </li>
        </ul>
    </nav>

    <div class="p-4 border-t border-slate-800">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-slate-700 flex items-center justify-center text-white font-bold">
                <?php echo strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1)); ?>
            </div>
            <div class="overflow-hidden">
                <p class="text-sm font-medium text-white truncate">
                    <?php echo $_SESSION['name'] ?? 'User'; ?>
                </p>
                <p class="text-xs text-slate-400 truncate">
                    <?php echo $_SESSION['position'] ?? 'Staff'; ?>
                </p>
            </div>
        </div>
        <a href="<?php echo BASE_URL; ?>/logout"
            class="mt-4 block w-full py-2 px-4 bg-red-600 hover:bg-red-700 text-white text-center rounded-lg text-sm transition-colors">Logout</a>
    </div>
</aside>
<main class="flex-1 h-screen overflow-y-auto bg-slate-50 p-4 md:p-8">
    <!-- Mobile Header -->
    <div
        class="md:hidden flex items-center justify-between mb-6 bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <button @click="sidebarOpen = !sidebarOpen" class="text-slate-500 hover:text-slate-600 focus:outline-none">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16">
                </path>
            </svg>
        </button>
        <span class="font-bold text-lg text-slate-700">
            <?php echo $settings['app_name']; ?>
        </span>
        <div class="w-6"></div>
    </div>