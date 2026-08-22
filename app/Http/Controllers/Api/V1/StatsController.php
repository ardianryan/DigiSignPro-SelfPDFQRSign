<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Signature;
use App\Models\ToolUsage;
use Illuminate\Http\Request;

class StatsController extends BaseApiController
{
    public function show(Request $request)
    {
        $user = $this->apiUser($request);

        $query = Signature::query();
        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        $totalSignatures = (clone $query)->count();
        $digitalCount = (clone $query)->where('signature_type', 'digital')->count();
        $manualQrCount = (clone $query)->where('signature_type', 'qr_manual')->count();

        // Tool usage counters
        $toolQuery = ToolUsage::query();
        if ($user->role !== 'admin') {
            $toolQuery->where('user_id', $user->id);
        }

        $toolUsages = (clone $toolQuery)
            ->selectRaw('tool_name, COUNT(*) as count, SUM(files_count) as total_files')
            ->groupBy('tool_name')
            ->get()
            ->keyBy('tool_name');

        $toolStats = [
            'editor' => [
                'name' => 'Visual PDF Editor',
                'uses' => (int) ($toolUsages['editor']->count ?? 0),
                'files' => (int) ($toolUsages['editor']->total_files ?? 0),
            ],
            'merge' => [
                'name' => 'Merge PDF',
                'uses' => (int) ($toolUsages['merge']->count ?? 0),
                'files' => (int) ($toolUsages['merge']->total_files ?? 0),
            ],
            'split' => [
                'name' => 'Split PDF',
                'uses' => (int) ($toolUsages['split']->count ?? 0),
                'files' => (int) ($toolUsages['split']->total_files ?? 0),
            ],
            'organize' => [
                'name' => 'Organize & Rotate',
                'uses' => (int) ($toolUsages['organize']->count ?? 0),
                'files' => (int) ($toolUsages['organize']->total_files ?? 0),
            ],
            'watermark' => [
                'name' => 'Watermark PDF',
                'uses' => (int) ($toolUsages['watermark']->count ?? 0),
                'files' => (int) ($toolUsages['watermark']->total_files ?? 0),
            ],
            'page_number' => [
                'name' => 'Page Numbering',
                'uses' => (int) ($toolUsages['page_number']->count ?? 0),
                'files' => (int) ($toolUsages['page_number']->total_files ?? 0),
            ],
            'protect' => [
                'name' => 'Protect & Encrypt',
                'uses' => (int) ($toolUsages['protect']->count ?? 0),
                'files' => (int) ($toolUsages['protect']->total_files ?? 0),
            ],
            'image_to_pdf' => [
                'name' => 'Image to PDF',
                'uses' => (int) ($toolUsages['image_to_pdf']->count ?? 0),
                'files' => (int) ($toolUsages['image_to_pdf']->total_files ?? 0),
            ],
        ];

        return $this->ok([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'signature_prefix' => $user->signature_prefix ?: 'DS',
            ],
            'signatures' => [
                'total' => $totalSignatures,
                'digital' => $digitalCount,
                'qr_manual' => $manualQrCount,
            ],
            'pdf_tools' => $toolStats,
        ], 'Statistik akun dan PDF tools');
    }
}
