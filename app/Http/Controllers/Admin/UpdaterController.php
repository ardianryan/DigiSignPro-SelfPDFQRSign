<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Throwable;

/**
 * Database migration status page only.
 * ZIP app updates are deprecated — deploy via CI/CD instead.
 */
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
}
