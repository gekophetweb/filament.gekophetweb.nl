<?php

namespace App\Http\Controllers;

use App\Actions\SubmitPrintRequest;
use App\Models\PrintRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Print requests: visitors send them through any working share link, the owner downloads the STL.
 */
class PrintRequestController extends Controller
{
    public function store(Request $request, string $token, SubmitPrintRequest $submit): RedirectResponse
    {
        $owner = User::where('share_token', $token)->firstOrFail();

        $submit->handle($owner, $request->all());

        return redirect()->to(route('share', $token).'#print-request')
            ->with('print_request_sent', true);
    }

    /**
     * Download the uploaded STL. Always as an attachment, never shown in the browser.
     */
    public function download(PrintRequest $printRequest): StreamedResponse
    {
        Gate::authorize('view', $printRequest);
        abort_unless($printRequest->stl_path && Storage::disk('local')->exists($printRequest->stl_path), 404);

        return Storage::disk('local')->download(
            $printRequest->stl_path,
            $printRequest->stl_original_name ?: "{$printRequest->uuid}.stl",
            ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff'],
        );
    }
}
