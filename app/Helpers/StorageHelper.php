<?php

namespace App\Helpers;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class StorageHelper
{
    private static $settings = null;

    private static $initialized = false;

    /**
     * Initialize dynamic configuration at runtime
     */
    public static function init()
    {
        if (self::$initialized) {
            return;
        }

        $settings = AppSetting::first();
        self::$settings = $settings ? $settings->toArray() : [];

        // Apply sane defaults
        self::$settings = array_merge([
            'storage_mode' => 'local',
            's3_bucket' => null,
            's3_region' => 'us-east-1',
            's3_directory' => 'digisign/',
        ], self::$settings);

        $mode = self::$settings['storage_mode'] ?? 'local';

        // Dynamically configure Laravel's s3 disk using database settings
        if (($mode === 's3' || $mode === 'both') && ! empty(self::$settings['s3_access_key'])) {
            config([
                'filesystems.disks.s3.driver' => 's3',
                'filesystems.disks.s3.key' => self::$settings['s3_access_key'],
                'filesystems.disks.s3.secret' => self::$settings['s3_secret_key'],
                'filesystems.disks.s3.region' => self::$settings['s3_region'] ?? 'us-east-1',
                'filesystems.disks.s3.bucket' => self::$settings['s3_bucket'],
                'filesystems.disks.s3.endpoint' => self::$settings['s3_endpoint'] ?: null,
                'filesystems.disks.s3.use_path_style_endpoint' => ! empty(self::$settings['s3_endpoint']),
            ]);
        }

        self::$initialized = true;
    }

    /**
     * Upload file to configured storage(s)
     * Returns the final path or S3 key URI
     */
    public static function upload($tempSource, $filename, $mimeType = 'application/pdf')
    {
        self::init();
        $mode = self::$settings['storage_mode'] ?? 'local';
        $finalPath = '';

        // 1. Local Storage upload
        if ($mode === 'local' || $mode === 'both') {
            $targetPath = 'uploads/signatures/'.$filename;
            // Move file using Laravel Storage disk local
            if (Storage::disk('public')->put($targetPath, file_get_contents($tempSource))) {
                $finalPath = 'storage/'.$targetPath;
            }
        }

        // 2. S3 Storage upload
        if ($mode === 's3' || $mode === 'both') {
            try {
                $directory = self::$settings['s3_directory'] ?? 'digisign/';
                $key = rtrim($directory, '/').'/'.$filename;

                if (Storage::disk('s3')->put($key, file_get_contents($tempSource), [
                    'ContentType' => $mimeType,
                ])) {
                    if ($mode === 's3') {
                        $finalPath = 's3://'.$key;
                    }
                }
            } catch (Throwable $e) {
                Log::error('S3 Upload Error: '.$e->getMessage());
                if ($mode === 's3') {
                    throw new \Exception('Gagal upload ke S3: '.$e->getMessage());
                }
            }
        }

        return $finalPath;
    }

    /**
     * Get public URL for a file
     */
    public static function getFileUrl($filePath)
    {
        if (empty($filePath)) {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $filePath)) {
            return $filePath;
        }

        self::init();

        // Handle S3 prefix
        if (strpos(strtolower($filePath), 's3://') === 0) {
            $key = substr($filePath, 5);
            $publicUrl = rtrim(self::$settings['s3_public_url'] ?? '', '/');

            if (! empty($publicUrl)) {
                return $publicUrl.'/'.ltrim($key, '/');
            }

            $endpoint = rtrim(self::$settings['s3_endpoint'] ?? '', '/');
            $bucket = self::$settings['s3_bucket'] ?? '';

            if (empty($endpoint)) {
                $region = self::$settings['s3_region'] ?? 'us-east-1';
                if (empty($region)) {
                    $region = 'us-east-1';
                }

                return "https://{$bucket}.s3.{$region}.amazonaws.com/".ltrim($key, '/');
            }

            if (strpos($endpoint, $bucket) !== false) {
                return $endpoint.'/'.ltrim($key, '/');
            }

            return $endpoint.'/'.$bucket.'/'.ltrim($key, '/');
        }

        // Local path
        return url($filePath);
    }

    /**
     * Delete file from configured storage
     */
    public static function delete($filePath)
    {
        if (empty($filePath)) {
            return;
        }
        self::init();

        if (strpos(strtolower($filePath), 's3://') === 0) {
            try {
                $key = substr($filePath, 5);
                Storage::disk('s3')->delete(ltrim($key, '/'));
            } catch (Throwable $e) {
                Log::error('S3 Delete Error: '.$e->getMessage());
            }
        } else {
            // Local path cleanup (remove storage/ prefix to find the relative path inside public disk)
            $cleanPath = $filePath;
            if (strpos($filePath, 'storage/') === 0) {
                $cleanPath = substr($filePath, 8);
            }
            if (Storage::disk('public')->exists($cleanPath)) {
                Storage::disk('public')->delete($cleanPath);
            }
        }
    }
}
