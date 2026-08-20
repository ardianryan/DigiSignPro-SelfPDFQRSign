<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\StorageHelper;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Throwable;

class StorageController extends Controller
{
    public function index()
    {
        StorageHelper::init();
        $settings = AppSetting::first();

        $storageMode = $settings->storage_mode ?? 'local';
        $bucket = $settings->s3_bucket ?? '';
        $directory = $settings->s3_directory ?? 'digisign/';
        $region = $settings->s3_region ?? 'us-east-1';

        $stats = [
            'count' => 0,
            'size' => 0,
            'bucket' => $bucket ?: '-',
            'region' => $region,
            'mode' => $storageMode,
            'directory' => $directory ?: '/',
        ];
        $files = [];

        if (in_array($storageMode, ['s3', 'both'], true) && ! empty($settings?->s3_access_key)) {
            try {
                $prefix = rtrim((string) $directory, '/').'/';
                $allFiles = Storage::disk('s3')->allFiles($prefix);
                $totalSize = 0;
                $s3Files = [];

                foreach ($allFiles as $file) {
                    if (str_ends_with($file, '/')) {
                        continue;
                    }

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
                        'PublicUrl' => StorageHelper::getFileUrl('s3://'.$file),
                        'source' => 's3',
                    ];
                }

                usort($s3Files, fn ($a, $b) => strtotime($b['LastModified']) <=> strtotime($a['LastModified']));

                $stats['count'] = count($s3Files);
                $stats['size'] = $totalSize;
                $files = $s3Files;
            } catch (Throwable $e) {
                Log::error('S3 Storage list error: '.$e->getMessage());
                $stats['error'] = 'Gagal memuat data dari S3: '.$e->getMessage();
            }
        } elseif ($storageMode === 'local' || $storageMode === 'both') {
            // Local signatures listing (public disk)
            try {
                $localFiles = [];
                $totalSize = 0;
                $disk = Storage::disk('public');
                $prefix = 'uploads/signatures';

                if ($disk->exists($prefix)) {
                    foreach ($disk->allFiles($prefix) as $file) {
                        try {
                            $size = $disk->size($file);
                            $lastModified = $disk->lastModified($file);
                        } catch (Throwable $e) {
                            $size = 0;
                            $lastModified = time();
                        }
                        $totalSize += $size;
                        $localFiles[] = [
                            'Key' => $file,
                            'Size' => $size,
                            'LastModified' => date('Y-m-d H:i:s', $lastModified),
                            'PublicUrl' => StorageHelper::getFileUrl('storage/'.$file),
                            'source' => 'local',
                        ];
                    }
                }

                usort($localFiles, fn ($a, $b) => strtotime($b['LastModified']) <=> strtotime($a['LastModified']));

                // When both modes, append local after S3 if S3 already filled
                if ($storageMode === 'both' && ! empty($files)) {
                    $stats['count'] += count($localFiles);
                    $stats['size'] += $totalSize;
                    $files = array_merge($files, $localFiles);
                } else {
                    $stats['count'] = count($localFiles);
                    $stats['size'] = $totalSize;
                    $stats['bucket'] = 'local';
                    $stats['directory'] = $prefix;
                    $files = $localFiles;
                }
            } catch (Throwable $e) {
                Log::error('Local Storage list error: '.$e->getMessage());
                $stats['error'] = 'Gagal memuat data storage lokal: '.$e->getMessage();
            }
        }

        return Inertia::render('Admin/Storage', [
            'stats' => $stats,
            'files' => $files,
            'storageMode' => $storageMode,
        ]);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'key' => 'required|string',
        ]);

        StorageHelper::init();
        $key = $request->input('key');
        $settings = AppSetting::first();
        $mode = $settings->storage_mode ?? 'local';

        try {
            // Prefer S3 delete when key looks like object storage path / mode is s3
            if (in_array($mode, ['s3', 'both'], true) && ! str_starts_with($key, 'uploads/')) {
                Storage::disk('s3')->delete($key);
            } else {
                $clean = str_starts_with($key, 'storage/') ? substr($key, 8) : $key;
                Storage::disk('public')->delete($clean);
            }

            return redirect()->back()->with('success', 'File berhasil dihapus.');
        } catch (Throwable $e) {
            return redirect()->back()->withErrors([
                'error' => 'Gagal menghapus file: '.$e->getMessage(),
            ]);
        }
    }
}
