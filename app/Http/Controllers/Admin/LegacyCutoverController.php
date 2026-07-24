<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\LegacyCutover;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * One-time web trigger for legacy→Laravel cutover.
 * Hidden after completion. Requires admin password re-entry.
 */
class LegacyCutoverController extends Controller
{
    public function run(Request $request, LegacyCutover $cutover)
    {
        if ($cutover->isCompleted() && ! $request->boolean('force')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cutover legacy sudah pernah dijalankan. Gunakan Migrasi Database biasa untuk update skema berikutnya.',
                'already_done' => true,
            ], 409);
        }

        $request->validate([
            'password' => ['required', 'string'],
            'confirm' => ['accepted'],
        ], [
            'password.required' => 'Password admin wajib diisi.',
            'confirm.accepted' => 'Anda harus menyetujui bahwa user akan login ulang.',
        ]);

        $user = $request->user();
        if (! $user || $user->role !== 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Hanya admin yang dapat menjalankan cutover.',
            ], 403);
        }

        if (! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Password admin tidak cocok. Cutover dibatalkan.',
            ]);
        }

        try {
            $result = $cutover->run(
                force: $request->boolean('force'),
                triggeredBy: 'web:'.$user->email
            );

            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'already_done' => $result['already_done'],
                'steps' => $result['steps'],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cutover gagal: '.$e->getMessage(),
            ], 500);
        }
    }
}
