<?php

namespace App\Http\Controllers;

use App\Actions\SubmitPrintRequest;
use App\Models\FilamentProduct;
use App\Models\PrintRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Print requests: visitors send them through any working share link, the owner downloads the STL.
 */
class PrintRequestController extends Controller
{
    /**
     * The print request tab of a share link; colors to pick are the filaments the link shows.
     */
    public function create(string $token): Response
    {
        $owner = User::where('share_token', $token)->firstOrFail();

        return response()
            ->view('share-print-request', [
                'owner' => $owner,
                'products' => FilamentProduct::query()->inStockFor($owner)->with('material')->orderBy('article_number')->get(),
            ])
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function store(Request $request, string $token, SubmitPrintRequest $submit): RedirectResponse
    {
        $owner = User::where('share_token', $token)->firstOrFail();

        $submit->handle($owner, $request->all());

        return redirect()->route('share.print-request', $token)->with('print_request_sent', true);
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
