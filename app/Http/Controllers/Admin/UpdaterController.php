<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Throwable;
use ZipArchive;

class UpdaterController extends Controller
{
    private function versionPath(): string
    {
        return base_path('version.lock');
    }

    private function currentVersion(): string
    {
        $path = $this->versionPath();
        if (file_exists($path)) {
            return trim((string) file_get_contents($path)) ?: '2.0.0';
        }

        return '2.0.0';
    }

    private function tempBase(): string
    {
        $dir = storage_path('app/temp/updates');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private function cleanup(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->cleanup($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    private function recursiveCopy(string $src, string $dst, array $blocked = []): void
    {
        if (!is_dir($src)) {
            return;
        }

        if (!is_dir($dst)) {
            @mkdir($dst, 0775, true);
        }

        $dir = opendir($src);
        if ($dir === false) {
            return;
        }

        while (false !== ($file = readdir($dir))) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $from = $src . DIRECTORY_SEPARATOR . $file;
            $to = $dst . DIRECTORY_SEPARATOR . $file;

            // Block dangerous paths at top-level of project root destination
            $relative = ltrim(str_replace(base_path(), '', $to), DIRECTORY_SEPARATOR);
            foreach ($blocked as $block) {
                if ($relative === $block || str_starts_with($relative, $block . DIRECTORY_SEPARATOR) || str_starts_with($relative, $block . '/')) {
                    continue 2;
                }
            }

            if (is_dir($from)) {
                $this->recursiveCopy($from, $to, $blocked);
            } else {
                @copy($from, $to);
            }
        }

        closedir($dir);
    }

    private function migrationStatus(): array
    {
        try {
            Artisan::call('migrate:status');
            $output = Artisan::output();
            $pending = [];
            foreach (preg_split('/\r\n|\r|\n/', $output) as $line) {
                if (stripos($line, 'Pending') !== false || preg_match('/\sPending\s/i', $line)) {
                    $pending[] = trim($line);
                }
            }

            return [
                'has_pending' => count($pending) > 0,
                'pending' => array_slice($pending, 0, 50),
                'raw' => $output,
            ];
        } catch (Throwable $e) {
            return [
                'has_pending' => false,
                'pending' => [],
                'raw' => $e->getMessage(),
                'error' => true,
            ];
        }
    }

    public function index()
    {
        return Inertia::render('Admin/Updater', [
            'current_version' => $this->currentVersion(),
            'migration' => $this->migrationStatus(),
        ]);
    }

    public function analyze(Request $request)
    {
        $request->validate([
            'update_file' => 'required|file|mimes:zip|max:102400', // 100MB
        ]);

        $file = $request->file('update_file');
        $tempId = 'upd_' . bin2hex(random_bytes(8));
        $tempDir = $this->tempBase() . DIRECTORY_SEPARATOR . $tempId;

        if (!mkdir($tempDir, 0775, true) && !is_dir($tempDir)) {
            return response()->json(['status' => 'error', 'message' => 'Gagal membuat direktori temp.'], 500);
        }

        $zip = new ZipArchive();
        if ($zip->open($file->getRealPath()) !== true) {
            $this->cleanup($tempDir);
            return response()->json(['status' => 'error', 'message' => 'Gagal membuka file ZIP.'], 422);
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            if ($entryName === false) {
                continue;
            }
            if (str_contains($entryName, '..') || str_starts_with($entryName, '/') || str_starts_with($entryName, '\\')) {
                $zip->close();
                $this->cleanup($tempDir);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Keamanan: Nama file di dalam ZIP tidak valid (path traversal).',
                ], 422);
            }
        }

        if (!$zip->extractTo($tempDir)) {
            $zip->close();
            $this->cleanup($tempDir);
            return response()->json(['status' => 'error', 'message' => 'Gagal mengekstrak ZIP.'], 422);
        }
        $zip->close();

        $manifestPath = $tempDir . DIRECTORY_SEPARATOR . 'manifest.json';
        if (!file_exists($manifestPath)) {
            $this->cleanup($tempDir);
            return response()->json([
                'status' => 'error',
                'message' => 'manifest.json tidak ditemukan dalam paket update.',
            ], 422);
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (!is_array($manifest) || empty($manifest['version'])) {
            $this->cleanup($tempDir);
            return response()->json(['status' => 'error', 'message' => 'Format manifest.json tidak valid.'], 422);
        }

        // Build file list for preview
        $filesList = [];
        $filesDir = $tempDir . DIRECTORY_SEPARATOR . 'files';
        if (is_dir($filesDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($filesDir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $path) {
                if ($path->isFile()) {
                    $rel = ltrim(str_replace($filesDir, '', $path->getPathname()), DIRECTORY_SEPARATOR);
                    $filesList[] = str_replace('\\', '/', $rel);
                }
            }
        }

        if (empty($manifest['files']) || !is_array($manifest['files'])) {
            $manifest['files'] = $filesList;
        }

        return response()->json([
            'status' => 'success',
            'temp_id' => $tempId,
            'manifest' => [
                'version' => $manifest['version'] ?? '',
                'release_date' => $manifest['release_date'] ?? '',
                'description' => $manifest['description'] ?? '',
                'author' => $manifest['author'] ?? '',
                'files' => array_values(array_slice($manifest['files'], 0, 500)),
            ],
        ]);
    }

    public function execute(Request $request)
    {
        $request->validate([
            'temp_id' => ['required', 'string', 'regex:/^upd_[a-f0-9]+$/'],
        ]);

        $tempId = $request->input('temp_id');
        $tempDir = $this->tempBase() . DIRECTORY_SEPARATOR . $tempId;

        if (!is_dir($tempDir)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sesi update kadaluarsa atau tidak ditemukan. Silakan upload ulang.',
            ], 422);
        }

        $manifestPath = $tempDir . DIRECTORY_SEPARATOR . 'manifest.json';
        if (!file_exists($manifestPath)) {
            $this->cleanup($tempDir);
            return response()->json(['status' => 'error', 'message' => 'manifest.json hilang.'], 422);
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true) ?: [];
        $newVersion = $manifest['version'] ?? 'Unknown';

        try {
            set_time_limit(600);

            // 1. Optional SQL scripts
            $sqlFiles = $manifest['sql_files'] ?? [];
            if (file_exists($tempDir . DIRECTORY_SEPARATOR . 'update.sql')) {
                $sqlFiles[] = 'update.sql';
            }

            foreach (array_unique($sqlFiles) as $sqlFile) {
                if (str_contains($sqlFile, '..') || str_starts_with($sqlFile, '/') || str_starts_with($sqlFile, '\\')) {
                    throw new \RuntimeException('Security Error: Path file SQL tidak valid.');
                }
                $sqlPath = $tempDir . DIRECTORY_SEPARATOR . $sqlFile;
                if (!file_exists($sqlPath)) {
                    continue;
                }
                $sqlContent = trim((string) file_get_contents($sqlPath));
                if ($sqlContent === '') {
                    continue;
                }
                DB::unprepared($sqlContent);
            }

            // 2. Copy files from package (if present)
            $sourceFiles = $tempDir . DIRECTORY_SEPARATOR . 'files';
            $blocked = [
                '.env',
                '.git',
                'vendor',
                'node_modules',
                'storage/logs',
                'storage/framework',
                'bootstrap/cache',
            ];

            if (is_dir($sourceFiles)) {
                $this->recursiveCopy($sourceFiles, base_path(), $blocked);
            }

            // 3. Prefer Laravel migrations if package includes them under files/database/migrations
            try {
                Artisan::call('migrate', ['--force' => true]);
            } catch (Throwable $e) {
                Log::warning('Updater migrate: ' . $e->getMessage());
            }

            // 4. Update version lock
            file_put_contents($this->versionPath(), $newVersion);

            // 5. Cleanup
            $this->cleanup($tempDir);

            return response()->json([
                'status' => 'success',
                'message' => "Aplikasi berhasil diupdate ke versi {$newVersion}",
                'version' => $newVersion,
            ]);
        } catch (Throwable $e) {
            Log::error('Update execute failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
