<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class ApiDocsController extends Controller
{
    private function quickapiPath(): string
    {
        return base_path('docs/quickapi.md');
    }

    private function renderQuickapi(Request $request): string
    {
        $path = $this->quickapiPath();
        if (! File::exists($path)) {
            abort(404, 'quickapi.md tidak ditemukan.');
        }

        $content = File::get($path);
        $baseUrl = rtrim($request->getSchemeAndHttpHost(), '/');

        return str_replace(
            [
                '{{BASE_URL}}',
                '{{API_BASE}}',
                '{{APP_NAME}}',
            ],
            [
                $baseUrl,
                $baseUrl.'/api/v1',
                config('app.name', 'DigiSign Pro'),
            ],
            $content
        );
    }

    /**
     * Authenticated web download (session).
     */
    public function downloadQuickapi(Request $request): Response
    {
        $body = $this->renderQuickapi($request);

        return response($body, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="quickapi.md"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Public download for agents / integrators (rate limited).
     */
    public function quickapiPublic(Request $request): Response
    {
        $body = $this->renderQuickapi($request);

        return response($body, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="quickapi.md"',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
