<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\StorageHelper;
use App\Models\Signature;
use Illuminate\Http\Request;

class VerifyController extends BaseApiController
{
    /**
     * Public verification (no API key required).
     */
    public function show(Request $request, string $code)
    {
        $signature = Signature::with('user:id,name,position')
            ->where('verify_code', $code)
            ->first();

        if (! $signature) {
            return $this->fail(
                'Dokumen dengan kode verifikasi tersebut tidak ditemukan.',
                404,
                'not_found'
            );
        }

        return $this->ok([
            'valid' => true,
            'verify_code' => $signature->verify_code,
            'document_name' => $signature->document_name,
            'document_number' => $signature->document_number,
            'document_subject' => $signature->document_subject,
            'document_attachment' => $signature->document_attachment,
            'signature_type' => $signature->signature_type,
            'signed_at' => $signature->signed_at?->toIso8601String(),
            'file_url' => $signature->file_path ? StorageHelper::getFileUrl($signature->file_path) : null,
            'signer' => [
                'name' => $signature->user?->name,
                'position' => $signature->user?->position ?? 'Staff',
            ],
        ], 'Dokumen terverifikasi');
    }
}
