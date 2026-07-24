<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\User;
use App\Models\Signature;
use App\Models\AppSetting;
use Inertia\Inertia;
use ZipArchive;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Throwable;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class BackupController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Backup');
    }

    public function run(Request $request)
    {
        $backupDb = $request->boolean('backup_db', true);
        $backupMedia = $request->boolean('backup_media', true);

        if (!$backupDb && !$backupMedia) {
            return redirect()->back()->withErrors(['error' => 'Pilih minimal satu opsi backup.']);
        }

        $zip = new ZipArchive();
        $filename = 'backup_digisign_' . date('Y-m-d_H-i-s') . '.zip';
        $tempDir = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }
        $tempFile = $tempDir . '/' . $filename;

        if ($zip->open($tempFile, ZipArchive::CREATE) !== TRUE) {
            return redirect()->back()->withErrors(['error' => 'Gagal membuat file ZIP.']);
        }

        if ($backupDb) {
            $tables = ['users', 'signatures', 'app_settings'];
            foreach ($tables as $table) {
                if (Schema::hasTable($table)) {
                    $rows = DB::table($table)->get()->map(function ($row) {
                        return (array) $row;
                    })->toArray();

                    $jsonContent = json_encode($rows, JSON_PRETTY_PRINT);
                    $zip->addFromString("database/{$table}.json", $jsonContent);
                }
            }
        }

        if ($backupMedia) {
            $uploadsDir = storage_path('app/public/uploads');
            if (is_dir($uploadsDir)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $name => $file) {
                    if (strpos($file->getFilename(), 'temp') !== false) {
                        continue;
                    }
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen(realpath($uploadsDir)) + 1);
                    $zip->addFile($filePath, "uploads/" . $relativePath);
                }
            }
        }

        $zip->close();

        return response()->download($tempFile)->deleteFileAfterSend(true);
    }

    public function restore(Request $request)
    {
        $request->validate([
            'backup_file' => 'required|file',
            'restore_db' => 'nullable|boolean',
            'restore_media' => 'nullable|boolean',
        ]);

        $restoreDb = $request->boolean('restore_db');
        $restoreMedia = $request->boolean('restore_media');

        if (!$restoreDb && !$restoreMedia) {
            return response()->json(['status' => 'error', 'message' => 'Pilih minimal satu opsi restore.']);
        }

        $tempDir = storage_path('app/temp/restore_' . time());
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($request->file('backup_file')->getRealPath()) === TRUE) {
            $zip->extractTo($tempDir);
            $zip->close();
        } else {
            return response()->json(['status' => 'error', 'message' => 'Gagal mengekstrak ZIP backup.']);
        }

        try {
            DB::beginTransaction();

            if ($restoreDb && is_dir($tempDir . '/database')) {
                Schema::disableForeignKeyConstraints();

                $tables = ['users', 'signatures', 'app_settings'];
                foreach ($tables as $table) {
                    $jsonFile = $tempDir . "/database/{$table}.json";
                    if (file_exists($jsonFile)) {
                        $data = json_decode(file_get_contents($jsonFile), true);
                        if ($data !== null) {
                            DB::table($table)->truncate();
                            foreach ($data as $row) {
                                DB::table($table)->insert($row);
                            }
                        }
                    }
                }

                Schema::enableForeignKeyConstraints();
            }

            DB::commit();

            if ($restoreMedia && is_dir($tempDir . '/uploads')) {
                $targetDir = storage_path('app/public/uploads');
                if (!file_exists($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }

                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($tempDir . '/uploads', RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $name => $file) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen(realpath($tempDir . '/uploads')) + 1);
                    $destPath = $targetDir . '/' . $relativePath;

                    $destDir = dirname($destPath);
                    if (!file_exists($destDir)) {
                        mkdir($destDir, 0777, true);
                    }

                    copy($filePath, $destPath);
                }
            }

            $this->cleanupDir($tempDir);

            return response()->json(['status' => 'success', 'message' => 'Restore berhasil dipulihkan.']);

        } catch (Throwable $e) {
            DB::rollBack();
            Log::error("Restore Database error: " . $e->getMessage());
            $this->cleanupDir($tempDir);
            return response()->json(['status' => 'error', 'message' => 'Restore gagal: ' . $e->getMessage()]);
        }
    }

    private function cleanupDir($dir)
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->cleanupDir("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }
}
