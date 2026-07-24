<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\AppSetting;
use App\Helpers\StorageHelper;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Throwable;

class StorageController extends Controller
{
    public function index()
    {
        StorageHelper::init();
        $settings = AppSetting::first();
        
        $storageMode = $settings ? $settings->storage_mode : 'local';
        $bucket = $settings ? $settings->s3_bucket : '';
        $directory = $settings ? $settings->s3_directory : '';

        $stats = null;
        $files = [];

        if ($storageMode !== 'local' && !empty($settings->s3_access_key)) {
            try {
                $s3Files = [];
                $prefix = rtrim($directory, '/') . '/';
                
                $allFiles = Storage::disk('s3')->allFiles($prefix);
                $totalSize = 0;

                foreach ($allFiles as $file) {
                    if (substr($file, -1) === '/') continue;

                    try {
                        $size = Storage::disk('s3')->size($file);
                        $lastModified = Storage::disk('s3')->lastModified($file);
                    } catch (Throwable $e) {
                        $size = 0;
                        $lastModified = time();
                    }

                    $totalSize += $size;
                    
                    $s3Files[] = [
                        'Key' => $file,
                        'Size' => $size,
                        'LastModified' => date('Y-m-d H:i:s', $lastModified),
                        'PublicUrl' => StorageHelper::getFileUrl('s3://' . $file)
                    ];
                }

                usort($s3Files, function ($a, $b) {
                    return strtotime($b['LastModified']) <=> strtotime($a['LastModified']);
                });

                $stats = [
                    'count' => count($s3Files),
                    'size' => $totalSize,
                    'bucket' => $bucket,
                    'region' => $settings->s3_region ?? 'us-east-1',
                    'mode' => $storageMode,
                    'directory' => $directory
                ];

                $files = $s3Files;

            } catch (Throwable $e) {
                \Illuminate\Support\Facades\Log::error("S3 Storage list error: " . $e->getMessage());
                $stats = [
                    'error' => 'Gagal memuat data dari S3: ' . $e->getMessage(),
                    'bucket' => $bucket,
                    'mode' => $storageMode
                ];
            }
        }

        return Inertia::render('Admin/Storage', [
            'stats' => $stats,
            'files' => $files,
            'storageMode' => $storageMode
        ]);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'key' => 'required|string'
        ]);

        StorageHelper::init();

        try {
            $key = $request->input('key');
            Storage::disk('s3')->delete($key);
            return redirect()->back()->with('success', 'File berhasil dihapus dari S3.');
        } catch (Throwable $e) {
            return redirect()->back()->withErrors(['error' => 'Gagal menghapus file dari S3: ' . $e->getMessage()]);
        }
    }
}
