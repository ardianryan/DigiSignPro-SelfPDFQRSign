<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

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
    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
