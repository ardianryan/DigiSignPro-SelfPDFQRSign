<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Signature;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $mySignaturesCount = Signature::where('user_id', $user->id)->count();

        $stats = [
            'my_signatures_count' => $mySignaturesCount,
        ];

        if ($user->role === 'admin') {
            $stats['total_signatures_count'] = Signature::count();
            $stats['total_users_count'] = User::count();
        }

        $appSettings = AppSetting::first();

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'settings' => $appSettings,
        ]);
    }

    public function updatePrefix(Request $request): RedirectResponse
    {
        $settings = AppSetting::first();
        $maxLen = $settings?->max_prefix_length ?: 3;
        if ($maxLen < 2) {
            $maxLen = 2;
        }
        if ($maxLen > 9) {
            $maxLen = 9;
        }

        $validated = $request->validate([
            'signature_prefix' => [
                'required',
                'string',
                'min:2',
                'max:'.$maxLen,
                'regex:/^[A-Za-z]+$/',
            ],
        ], [
            'signature_prefix.regex' => "Prefix harus 2-{$maxLen} huruf kapital (A-Z).",
            'signature_prefix.min' => "Prefix harus 2-{$maxLen} huruf kapital (A-Z).",
            'signature_prefix.max' => "Prefix harus 2-{$maxLen} huruf kapital (A-Z).",
        ]);

        $prefix = strtoupper(trim($validated['signature_prefix']));

        $request->user()->update([
            'signature_prefix' => $prefix,
        ]);

        return redirect()->back()->with('success', 'Prefix tanda tangan berhasil diperbarui.');
    }
}
