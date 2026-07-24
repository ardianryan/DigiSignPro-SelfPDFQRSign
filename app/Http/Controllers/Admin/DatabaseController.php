<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\LegacyDigisignSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Throwable;

class DatabaseController extends Controller
{
    /**
     * Run schema adaptation + Laravel migrations.
     * Requires re-entering the current admin password.
     */
    public function migrate(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string'],
        ], [
            'password.required' => 'Password admin wajib diisi untuk menjalankan migrasi.',
        ]);

        $user = $request->user();

        if (! $user || $user->role !== 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Hanya admin yang dapat menjalankan migrasi.',
            ], 403);
        }

        if (! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Password admin tidak cocok. Migrasi dibatalkan.',
            ]);
        }

        try {
            $legacySteps = (new LegacyDigisignSchema)->adapt();

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
