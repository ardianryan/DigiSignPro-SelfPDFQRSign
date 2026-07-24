<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'app_name',
    'app_logo',
    'maintenance_mode',
    'registration_open',
    'max_upload_size',
    'max_upload_size_bulk',
    'max_prefix_length',
    'timezone',
    'storage_mode',
    's3_endpoint',
    's3_region',
    's3_bucket',
    's3_access_key',
    's3_secret_key',
    's3_directory',
    's3_public_url',
])]
class AppSetting extends Model
{
    protected $table = 'app_settings';

    protected function casts(): array
    {
        return [
            'maintenance_mode' => 'boolean',
            'registration_open' => 'boolean',
            'max_upload_size' => 'integer',
            'max_upload_size_bulk' => 'integer',
            'max_prefix_length' => 'integer',
        ];
    }
}
