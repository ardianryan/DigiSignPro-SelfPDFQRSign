<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SignatureHistoryController;
use App\Http\Controllers\SingleSignController;
use App\Http\Controllers\BulkSignController;
use App\Http\Controllers\QrSignController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\StorageController as AdminStorageController;
use App\Http\Controllers\Admin\BackupController as AdminBackupController;
use App\Http\Controllers\Admin\DatabaseController as AdminDatabaseController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// 1. Guest routes
Route::get('/', function () {
    return redirect()->route('login');
});

// Public verify route
Route::get('/verify/{code?}', [VerificationController::class, 'verify'])->name('verify');

// 2. Authenticated user routes
Route::middleware(['auth'])->group(function () {
    
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Signature History
    Route::get('/history', [SignatureHistoryController::class, 'index'])->name('history.index');
    Route::delete('/history/{id}', [SignatureHistoryController::class, 'destroy'])->name('history.destroy');
    
    // Profile settings
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/signature', [ProfileController::class, 'uploadSignatureSpecimen'])->name('profile.signature');

    // Tanda Tangan Single PDF
    Route::get('/sign/single', [SingleSignController::class, 'create'])->name('sign.single.create');
    Route::post('/sign/single', [SingleSignController::class, 'store'])->name('sign.single.store');

    // Tanda Tangan Bulk PDF ZIP
    Route::get('/sign/bulk', [BulkSignController::class, 'create'])->name('sign.bulk.create');
    Route::post('/sign/preview-bulk', [BulkSignController::class, 'previewBulk'])->name('sign.bulk.preview');
    Route::post('/sign/bulk', [BulkSignController::class, 'store'])->name('sign.bulk.store');

    // TTE QR (Manual QR Code Signatures)
    Route::get('/sign/qr-manual', [QrSignController::class, 'index'])->name('sign.qr.index');
    Route::get('/sign/qr-manual/create', [QrSignController::class, 'create'])->name('sign.qr.create');
    Route::post('/sign/qr-manual', [QrSignController::class, 'store'])->name('sign.qr.store');
    
    // 3. Admin-only routes
    Route::middleware(['can:admin-only'])->group(function () {
        Route::get('/admin/users', [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::post('/admin/users', [AdminUserController::class, 'store'])->name('admin.users.store');
        Route::patch('/admin/users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
        Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
        Route::get('/admin/settings', [AdminSettingController::class, 'edit'])->name('admin.settings.edit');
        Route::put('/admin/settings', [AdminSettingController::class, 'update'])->name('admin.settings.update');
        Route::delete('/admin/settings/logo', [AdminSettingController::class, 'deleteLogo'])->name('admin.settings.delete_logo');
        Route::post('/admin/settings/test-s3', [AdminSettingController::class, 'testS3'])->name('admin.settings.test_s3');
        Route::post('/admin/settings/clear-temp', [AdminSettingController::class, 'clearTemp'])->name('admin.settings.clear_temp');
        Route::get('/admin/storage', [AdminStorageController::class, 'index'])->name('admin.storage.index');
        Route::delete('/admin/storage', [AdminStorageController::class, 'destroy'])->name('admin.storage.destroy');
        Route::get('/admin/backup', [AdminBackupController::class, 'index'])->name('admin.backup.index');
        Route::post('/admin/backup', [AdminBackupController::class, 'run'])->name('admin.backup.run');
        Route::post('/admin/backup/restore', [AdminBackupController::class, 'restore'])->name('admin.backup.restore');
        Route::post('/admin/database/migrate', [AdminDatabaseController::class, 'migrate'])->name('admin.database.migrate');
    });
});

require __DIR__.'/auth.php';
