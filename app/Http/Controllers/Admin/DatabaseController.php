<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\LegacyDigisignSchema;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class DatabaseController extends Controller
{
    /**
     * Run schema adaptation for DigiSign legacy DBs + Laravel migrations.
     * Used from Admin → Update App → "Jalankan Migrasi Database".
     */
    public function migrate()
    {
        try {
            // 1) Always adapt legacy DigiSign schema first (idempotent)
            $legacySteps = (new LegacyDigisignSchema)->adapt();

            // 2) Run pending Laravel migration files
            $exitCode = Artisan::call('migrate', [
                '--force' => true,
            ]);

            $output = Artisan::output();

            $message = "Migrasi database berhasil dijalankan.\n\n"
                ."=== Adaptasi skema DigiSign (legacy-safe) ===\n"
                .'- '.implode("\n- ", $legacySteps)
                ."\n\n=== Laravel migrate ===\n"
                .trim($output ?: 'Nothing to migrate / sudah up-to-date.');

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'legacy_steps' => $legacySteps,
                'output' => $output,
                'exit_code' => $exitCode,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menjalankan migrasi: '.$e->getMessage(),
            ], 500);
        }
    }
}
