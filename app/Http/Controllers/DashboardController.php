<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Signature;
use App\Models\User;
use App\Models\AppSetting;
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
}
