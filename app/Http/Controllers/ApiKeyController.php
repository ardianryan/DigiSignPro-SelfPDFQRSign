<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    public function regenerate(Request $request)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ], [
            'password.required' => 'Password wajib diisi untuk regenerasi API key.',
            'password.current_password' => 'Password tidak cocok.',
        ]);

        $user = $request->user();
        $user->forceFill([
            'api_key' => 'digi_'.Str::random(48),
            'api_key_created_at' => now(),
        ])->save();

        return redirect()
            ->route('profile.edit')
            ->with('status', 'api-key-regenerated')
            ->with('api_key_plain', $user->api_key);
    }
}
