<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;

class MeController extends BaseApiController
{
    public function show(Request $request)
    {
        $user = $this->apiUser($request);

        return $this->ok([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'position' => $user->position,
            'signature_prefix' => $user->signature_prefix ?: 'DS',
            'api_key_created_at' => $user->api_key_created_at?->toIso8601String(),
        ], 'Profil API user');
    }
}
