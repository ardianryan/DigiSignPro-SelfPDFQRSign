<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Signature;
use App\Helpers\StorageHelper;
use Inertia\Inertia;

class VerificationController extends Controller
{
    public function verify(Request $request, $code = null)
    {
        $token = $code ?: $request->query('token');

        if (empty($token)) {
            return Inertia::render('Verify', [
                'status' => 'error',
                'message' => 'Token verifikasi tidak valid atau kosong.'
            ]);
        }

        $signature = Signature::with('user')->where('verify_code', $token)->first();

        if (!$signature) {
            return Inertia::render('Verify', [
                'status' => 'error',
                'token' => $token,
                'message' => 'Dokumen dengan kode verifikasi tersebut tidak ditemukan di sistem kami.'
            ]);
        }

        return Inertia::render('Verify', [
            'status' => 'success',
            'token' => $token,
            'signature' => [
                'id' => $signature->id,
                'document_name' => $signature->document_name,
                'document_number' => $signature->document_number,
                'document_subject' => $signature->document_subject,
                'document_attachment' => $signature->document_attachment,
                'verify_code' => $signature->verify_code,
                'signed_at' => $signature->signed_at ? $signature->signed_at->format('d M Y H:i:s') : null,
                'file_url' => $signature->file_path ? StorageHelper::getFileUrl($signature->file_path) : null,
                'user' => $signature->user ? [
                    'name' => $signature->user->name,
                    'position' => $signature->user->position ?? 'Staff',
                ] : null
            ]
        ]);
    }
}
