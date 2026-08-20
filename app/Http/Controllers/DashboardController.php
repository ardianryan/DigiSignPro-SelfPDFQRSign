<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Signature;
use App\Models\ToolUsage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $now = now();

        $mySignaturesCount = Signature::where('user_id', $user->id)->count();
        $mySignaturesThisMonth = Signature::where('user_id', $user->id)
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();
        $mySignaturesToday = Signature::where('user_id', $user->id)
            ->whereDate('created_at', $now->toDateString())
            ->count();

        $recentSignatures = Signature::where('user_id', $user->id)
            ->latest('id')
            ->limit(5)
            ->get(['id', 'document_name', 'document_number', 'document_subject', 'verify_code', 'signed_at', 'created_at']);

        $stats = [
            'my_signatures_count' => $mySignaturesCount,
            'my_signatures_this_month' => $mySignaturesThisMonth,
            'my_signatures_today' => $mySignaturesToday,
        ];

        $adminAnalytics = null;
        if ($user->role === 'admin') {
            $totalSignatures = Signature::count();
            $totalUsers = User::count();
            $totalToolUsages = ToolUsage::count();
            $totalToolFiles = (int) ToolUsage::sum('files_count');
            $totalFilesProcessed = $totalSignatures + $totalToolFiles;

            $toolNames = [
                'merge' => ['name' => 'Merge PDF', 'color' => 'indigo'],
                'split' => ['name' => 'Split PDF', 'color' => 'purple'],
                'organize' => ['name' => 'Organize & Rotate', 'color' => 'emerald'],
                'image_to_pdf' => ['name' => 'Image to PDF', 'color' => 'amber'],
                'watermark' => ['name' => 'Watermark PDF', 'color' => 'cyan'],
                'page_number' => ['name' => 'Page Numbering', 'color' => 'violet'],
                'protect' => ['name' => 'Protect & Encrypt', 'color' => 'rose'],
            ];

            $usageByTool = ToolUsage::selectRaw('tool_name, count(*) as usage_count, sum(files_count) as total_files')
                ->groupBy('tool_name')
                ->get()
                ->keyBy('tool_name');

            $breakdown = [];
            foreach ($toolNames as $key => $info) {
                $count = isset($usageByTool[$key]) ? (int) $usageByTool[$key]->usage_count : 0;
                $files = isset($usageByTool[$key]) ? (int) $usageByTool[$key]->total_files : 0;
                $percentage = $totalToolUsages > 0 ? round(($count / $totalToolUsages) * 100) : 0;

                $breakdown[] = [
                    'key' => $key,
                    'title' => $info['name'],
                    'color' => $info['color'],
                    'usage_count' => $count,
                    'files_count' => $files,
                    'percentage' => $percentage,
                ];
            }

            $stats['total_signatures_count'] = $totalSignatures;
            $stats['total_users_count'] = $totalUsers;
            $stats['total_files_processed'] = $totalFilesProcessed;
            $stats['total_tools_used_count'] = $totalToolUsages;

            $adminAnalytics = [
                'total_signatures' => $totalSignatures,
                'total_users' => $totalUsers,
                'total_files_processed' => $totalFilesProcessed,
                'total_tools_used' => $totalToolUsages,
                'tools_breakdown' => $breakdown,
                'today_signatures' => Signature::whereDate('created_at', $now->toDateString())->count(),
                'monthly_signatures' => Signature::whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count(),
            ];
        }

        $appSettings = AppSetting::first();

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentSignatures' => $recentSignatures,
            'adminAnalytics' => $adminAnalytics,
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
