<?php

namespace App\Http\Controllers;

use App\Support\ApiDocumentation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Public API reference: a readable page and the OpenAPI document it is built from.
 * Served outside the web middleware group, so no IP allow-list, session or setup gate.
 */
class ApiDocsController extends Controller
{
    public function page(ApiDocumentation $docs): Response
    {
        return response()->view('docs.api', [
            'spec' => $docs->spec(),
            'sections' => $docs->operationsByTag(),
            'limits' => $docs->limits(),
            'docs' => $docs,
        ]);
    }

    public function openApi(ApiDocumentation $docs): JsonResponse
    {
        return response()->json($docs->spec(), options: JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ->header('Access-Control-Allow-Origin', '*');
    }
}
