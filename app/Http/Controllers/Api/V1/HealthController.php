<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AppSetting;

class HealthController extends BaseApiController
{
    public function show()
    {
        $settings = AppSetting::first();

        return $this->ok([
            'status' => 'ok',
            'app' => $settings?->app_name ?? 'DigiSign Pro',
            'time' => now()->toIso8601String(),
            'api_version' => 'v1',
        ], 'API healthy');
    }
}
