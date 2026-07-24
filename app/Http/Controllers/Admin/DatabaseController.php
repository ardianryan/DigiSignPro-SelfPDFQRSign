<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Artisan;

class DatabaseController extends Controller
{
    public function migrate()
    {
        try {
            // Run Artisan migration
            $exitCode = Artisan::call('migrate', [
                '--force' => true
            ]);
            
            $output = Artisan::output();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Migrasi database berhasil dijalankan.',
                'output' => $output,
                'exit_code' => $exitCode
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menjalankan migrasi: ' . $e->getMessage()
            ], 500);
        }
    }
}
