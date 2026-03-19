<?php
// includes/Storage.php

require_once __DIR__ . '/../vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class Storage {
    private static $s3Client = null;
    private static $settings = null;

    /**
     * Initialize settings and S3 client if needed
     */
    private static function init($conn) {
        if (self::$settings === null) {
            $result = $conn->query("SELECT * FROM app_settings WHERE id = 1");
            self::$settings = $result->fetch_assoc();
        }
    }

    /**
     * Get S3 Client instance
     */
    public static function getS3Client($conn) {
        self::init($conn);
        if (self::$s3Client === null && !empty(self::$settings['s3_access_key'])) {
            try {
                $config = [
                    'version' => 'latest',
                    'region'  => self::$settings['s3_region'] ?? 'us-east-1',
                    'credentials' => [
                        'key'    => self::$settings['s3_access_key'],
                        'secret' => self::$settings['s3_secret_key'],
                    ],
                ];

                if (!empty(self::$settings['s3_endpoint'])) {
                    $config['endpoint'] = self::$settings['s3_endpoint'];
                    $config['use_path_style_endpoint'] = true;
                }

                self::$s3Client = new S3Client($config);
            } catch (Exception $e) {
                error_log("Failed to initialize S3 Client: " . $e->getMessage());
            }
        }
        return self::$s3Client;
    }

    /**
     * Upload file to configured storage(s)
     * returns relative path or S3 key
     */
    public static function upload($conn, $tempSource, $filename, $mimeType = 'application/pdf') {
        self::init($conn);
        $mode = self::$settings['storage_mode'] ?? 'local';
        $finalPath = '';

        // 1. Local Storage
        if ($mode === 'local' || $mode === 'both') {
            $targetDir = __DIR__ . '/../public/uploads/signatures/';
            if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
            
            $targetFile = $targetDir . $filename;
            if (copy($tempSource, $targetFile)) {
                $finalPath = 'uploads/signatures/' . $filename;
            }
        }

        // 2. S3 Storage
        if ($mode === 's3' || $mode === 'both') {
            $client = self::getS3Client($conn);
            if ($client) {
                try {
                    $directory = self::$settings['s3_directory'] ?? 'digisign/';
                    $key = rtrim($directory, '/') . '/' . $filename;
                    
                    $client->putObject([
                        'Bucket' => self::$settings['s3_bucket'],
                        'Key'    => $key,
                        'SourceFile' => $tempSource,
                        'ContentType' => $mimeType,
                        // 'ACL' => 'public-read' // R2 might not need this if bucket is public
                    ]);
                    
                    // If mode is s3 only, path in DB is the S3 Key
                    if ($mode === 's3') {
                        $finalPath = 's3://' . $key;
                    }
                } catch (AwsException $e) {
                    error_log("S3 Upload Error: " . $e->getMessage());
                    if ($mode === 's3') throw new Exception("Gagal upload ke S3: " . $e->getMessage());
                }
            } elseif ($mode === 's3') {
                throw new Exception("S3 Client tidak terkonfigurasi dengan benar.");
            }
        }

        return $finalPath;
    }

    /**
     * Get public URL for a file
     */
    public static function getFileUrl($conn, $filePath) {
        self::init($conn);
        
        if (strpos($filePath, 's3://') === 0) {
            $key = substr($filePath, 5);
            $endpoint = rtrim(self::$settings['s3_endpoint'], '/');
            $bucket = self::$settings['s3_bucket'];
            
            // Cloudflare R2 structure or standard S3
            // If endpoint already contains bucket name (like user's example)
            if (strpos($endpoint, $bucket) !== false) {
                return $endpoint . '/' . $key;
            }
            
            return $endpoint . '/' . $bucket . '/' . $key;
        }
        
        // Local path
        return BASE_URL . '/' . $filePath;
    }

    /**
     * Delete file from configured storage
     */
    public static function delete($conn, $filePath) {
        self::init($conn);
        
        if (strpos($filePath, 's3://') === 0) {
            $client = self::getS3Client($conn);
            if ($client) {
                try {
                    $key = substr($filePath, 5);
                    $client->deleteObject([
                        'Bucket' => self::$settings['s3_bucket'],
                        'Key'    => $key,
                    ]);
                } catch (AwsException $e) {
                    error_log("S3 Delete Error: " . $e->getMessage());
                }
            }
        } else {
            $fullPath = __DIR__ . '/../public/' . $filePath;
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }
    }

    /**
     * List files in S3 bucket
     */
    public static function listFiles($conn, $prefix = '') {
        self::init($conn);
        $client = self::getS3Client($conn);
        if (!$client) return [];

        try {
            $dir = self::$settings['s3_directory'] ?: '';
            $fullPrefix = $dir . $prefix;
            
            $results = $client->listObjectsV2([
                'Bucket' => self::$settings['s3_bucket'],
                'Prefix' => $fullPrefix,
            ]);

            return $results['Contents'] ?? [];
        } catch (AwsException $e) {
            error_log("S3 List Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get S3 Bucket Stats
     */
    public static function getStats($conn) {
        self::init($conn);
        if (self::$settings['storage_mode'] === 'local') return null;

        $files = self::listFiles($conn);
        $totalSize = 0;
        foreach ($files as $file) {
            $totalSize += $file['Size'];
        }

        return [
            'count' => count($files),
            'size' => $totalSize,
            'bucket' => self::$settings['s3_bucket'],
            'region' => self::$settings['s3_region'],
            'mode' => self::$settings['storage_mode']
        ];
    }

    /**
     * Test S3/R2 connection
     */
    public static function testConnection($conn, $customSettings = null) {
        $settings = $customSettings;
        if (!$settings) {
            self::init($conn);
            $settings = self::$settings;
        }

        if (empty($settings['s3_access_key']) || empty($settings['s3_secret_key']) || empty($settings['s3_bucket']) || empty($settings['s3_endpoint'])) {
            return ['status' => 'error', 'message' => 'Kredensial S3 tidak lengkap (Key, Secret, Bucket, Endpoint wajib diisi)'];
        }

        try {
            if (!class_exists('Aws\S3\S3Client')) {
                return ['status' => 'error', 'message' => 'AWS SDK untuk PHP tidak ditemukan di folder vendor. Coba jalankan "composer update" di server.'];
            }

            $config = [
                'version' => 'latest',
                'region'  => $settings['s3_region'] ?? 'us-east-1',
                'credentials' => [
                    'key'    => $settings['s3_access_key'],
                    'secret' => $settings['s3_secret_key'],
                ],
            ];

            if (!empty($settings['s3_endpoint'])) {
                $config['endpoint'] = $settings['s3_endpoint'];
                $config['use_path_style_endpoint'] = true;
            }

            $client = new S3Client($config);
            
            // Try to list objects (minimal) to verify access
            $client->listObjectsV2([
                'Bucket' => $settings['s3_bucket'],
                'MaxKeys' => 1,
            ]);

            return ['status' => 'success', 'message' => 'Koneksi ke S3/R2 berhasil!'];
        } catch (AwsException $e) {
            $msg = $e->getAwsErrorMessage() ?: $e->getMessage();
            return ['status' => 'error', 'message' => 'Koneksi S3 Gagal: ' . $msg];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Koneksi S3 Gagal: ' . $e->getMessage()];
        }
    }
}
