<?php

namespace App\Http\Controllers;

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
}
