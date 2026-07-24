<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\AppSetting;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Throwable;

class SettingController extends Controller
{
    private function tempDirectories(): array
    {
        return array_filter([
            storage_path('app/temp'),
            storage_path('app/public/uploads/temp'),
            public_path('uploads/temp'),
        ], fn ($dir) => is_dir($dir));
    }

    private function scanTempStats(): array
    {
        $fileCount = 0;
        $totalBytes = 0;

        $walk = function (string $dir) use (&$walk, &$fileCount, &$totalBytes) {
            $items = @scandir($dir) ?: [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                if (is_dir($path)) {
                    $walk($path);
                } elseif (is_file($path)) {
                    $fileCount++;
                    $totalBytes += (int) filesize($path);
                }
            }
        };

        foreach ($this->tempDirectories() as $dir) {
            $walk($dir);
        }

        $mb = $totalBytes / (1024 * 1024);

        return [
            'file_count' => $fileCount,
            'size_bytes' => $totalBytes,
            'size_human' => $mb >= 1
                ? number_format($mb, 2) . ' MB'
                : number_format($totalBytes / 1024, 1) . ' KB',
            'paths' => $this->tempDirectories(),
        ];
    }

    public function edit()
    {
        return Inertia::render('Admin/Settings', [
            'settings' => AppSetting::first(),
            'temp_stats' => $this->scanTempStats(),
        ]);
    }

    public function update(Request $request)
    {
        $settings = AppSetting::first() ?: new AppSetting();

        $request->validate([
            'app_name' => 'required|string|max:255',
            'app_logo' => 'nullable|image|max:2048', // 2MB max
            'maintenance_mode' => 'required|boolean',
            'registration_open' => 'required|boolean',
            'max_upload_size_mb' => 'required|integer|min:1',
            'max_upload_size_bulk_mb' => 'required|integer|min:1',
            'max_prefix_length' => 'required|integer|min:2|max:9',
            'timezone' => 'required|string|in:Asia/Jakarta,Asia/Makassar,Asia/Jayapura,UTC',
            'storage_mode' => 'required|in:local,s3,both',
            's3_bucket' => 'nullable|string',
            's3_region' => 'nullable|string',
            's3_access_key' => 'nullable|string',
            's3_secret_key' => 'nullable|string',
            's3_endpoint' => 'nullable|string',
            's3_public_url' => 'nullable|string',
            's3_directory' => 'nullable|string',
        ]);

        $settings->app_name = $request->input('app_name');
        $settings->maintenance_mode = $request->input('maintenance_mode');
        $settings->registration_open = $request->input('registration_open');
        $settings->max_upload_size = $request->input('max_upload_size_mb') * 1024 * 1024;
        $settings->max_upload_size_bulk = $request->input('max_upload_size_bulk_mb') * 1024 * 1024;
        $settings->max_prefix_length = (int) $request->input('max_prefix_length');
        $settings->timezone = $request->input('timezone');
        $settings->storage_mode = $request->input('storage_mode');
        
        $settings->s3_bucket = $request->input('s3_bucket');
        $settings->s3_region = $request->input('s3_region');
        $settings->s3_access_key = $request->input('s3_access_key');
        $settings->s3_secret_key = $request->input('s3_secret_key');
        $settings->s3_endpoint = $request->input('s3_endpoint');
        $settings->s3_public_url = $request->input('s3_public_url');
        $settings->s3_directory = $request->input('s3_directory');

        if ($request->hasFile('app_logo')) {
            if ($settings->app_logo && Storage::disk('public')->exists(str_replace('storage/', '', $settings->app_logo))) {
                Storage::disk('public')->delete(str_replace('storage/', '', $settings->app_logo));
            }
            $path = $request->file('app_logo')->store('uploads/logo', 'public');
            $settings->app_logo = 'storage/' . $path;
        }

        $settings->save();

        return redirect()->back()->with('success', 'Pengaturan aplikasi berhasil disimpan.');
    }

    public function deleteLogo()
    {
        $settings = AppSetting::first();
        if ($settings && $settings->app_logo) {
            $cleanPath = str_replace('storage/', '', $settings->app_logo);
            if (Storage::disk('public')->exists($cleanPath)) {
                Storage::disk('public')->delete($cleanPath);
            }
            $settings->app_logo = null;
            $settings->save();
        }
        return redirect()->back()->with('success', 'Logo berhasil dihapus.');
    }

    public function testS3(Request $request)
    {
        $request->validate([
            's3_bucket' => 'required|string',
            's3_region' => 'required|string',
            's3_access_key' => 'required|string',
            's3_secret_key' => 'required|string',
            's3_endpoint' => 'nullable|string',
        ]);

        try {
            config([
                'filesystems.disks.s3_test.driver' => 's3',
                'filesystems.disks.s3_test.key' => $request->input('s3_access_key'),
                'filesystems.disks.s3_test.secret' => $request->input('s3_secret_key'),
                'filesystems.disks.s3_test.region' => $request->input('s3_region'),
                'filesystems.disks.s3_test.bucket' => $request->input('s3_bucket'),
                'filesystems.disks.s3_test.endpoint' => $request->input('s3_endpoint') ?: null,
                'filesystems.disks.s3_test.use_path_style_endpoint' => !empty($request->input('s3_endpoint')),
            ]);

            $testFilename = 's3_test_' . uniqid() . '.txt';
            Storage::disk('s3_test')->put($testFilename, 'Connection Test Success');
            Storage::disk('s3_test')->delete($testFilename);

            return response()->json(['status' => 'success', 'message' => 'Koneksi S3 berhasil terhubung!']);
        } catch (Throwable $e) {
            Log::error("S3 Test Connection Failed: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal terhubung ke S3: ' . $e->getMessage()]);
        }
    }

    public function clearTemp()
    {
        $deleteTree = function (string $dir) use (&$deleteTree) {
            if (!is_dir($dir)) {
                return;
            }
            $items = @scandir($dir) ?: [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                if (is_dir($path)) {
                    $deleteTree($path);
                    @rmdir($path);
                } else {
                    @unlink($path);
                }
            }
        };

        foreach ($this->tempDirectories() as $dir) {
            $deleteTree($dir);
            // recreate empty temp root
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }

        // Also ensure storage/app/temp exists after clean
        if (!is_dir(storage_path('app/temp'))) {
            @mkdir(storage_path('app/temp'), 0775, true);
        }

        return redirect()->back()->with('success', 'Folder temp berhasil dibersihkan.');
    }
}
