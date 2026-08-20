<?php

namespace App\Http\Controllers;

use App\Models\ToolUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PdfToolController extends Controller
{
    /**
     * Display the Bento Grid PDF Tools Hub.
     */
    public function index(): Response
    {
        return Inertia::render('Tools/Index');
    }

    /**
     * Merge PDF Tool workspace.
     */
    public function merge(): Response
    {
        return Inertia::render('Tools/Merge');
    }

    /**
     * Split PDF Tool workspace.
     */
    public function split(): Response
    {
        return Inertia::render('Tools/Split');
    }

    /**
     * Organize & Rotate PDF Tool workspace.
     */
    public function organize(): Response
    {
        return Inertia::render('Tools/Organize');
    }

    /**
     * Image to PDF Tool workspace.
     */
    public function imageToPdf(): Response
    {
        return Inertia::render('Tools/ImageToPdf');
    }

    /**
     * Watermark PDF Tool workspace.
     */
    public function watermark(): Response
    {
        return Inertia::render('Tools/Watermark');
    }

    /**
     * Page Numbering Tool workspace.
     */
    public function pageNumber(): Response
    {
        return Inertia::render('Tools/PageNumber');
    }

    /**
     * Protect PDF Tool workspace.
     */
    public function protect(): Response
    {
        return Inertia::render('Tools/Protect');
    }

    /**
     * Track usage telemetry for PDF Tools.
     */
    public function trackUsage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tool' => 'required|string|in:merge,split,organize,image_to_pdf,watermark,page_number,protect',
            'files_count' => 'nullable|integer|min:1|max:500',
        ]);

        ToolUsage::create([
            'user_id' => $request->user()?->id,
            'tool_name' => $validated['tool'],
            'files_count' => $validated['files_count'] ?? 1,
        ]);

        return response()->json(['status' => 'success']);
    }
}
