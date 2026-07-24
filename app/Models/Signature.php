<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'user_id',
    'batch_id',
    'document_name',
    'document_number',
    'document_subject',
    'document_attachment',
    'file_path',
    'verify_code',
    'signature_type',
    'signed_at',
])]
class Signature extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
