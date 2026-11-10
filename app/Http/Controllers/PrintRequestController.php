<?php

namespace App\Http\Controllers;

use App\Actions\SubmitPrintRequest;
use App\Models\FilamentProduct;
use App\Models\PrintRequest;
use App\Models\User;
use App\Support\PrintRequester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Print requests: signed-in requesters the owner allows send them through the share link, the owner downloads the STL.
 */
class PrintRequestController extends Controller
{
    /**
     * The share link page: a sign-in button, or the form for an allowed requester with their own earlier
     * requests (status and the owner's reply). Colors to pick are the owner's in-stock filaments.
     */
    public function create(string $token): Response
    {
        $owner = User::where('share_token', $token)->firstOrFail();
        $requester = PrintRequester::current();
        $isAllowed = $requester !== null && $owner->allowsPrintRequestsFrom($requester->email);

        return response()
            ->view('share-print-request', [
                'owner' => $owner,
                'requester' => $requester,
                'isAllowed' => $isAllowed,
                'products' => $isAllowed
                    ? FilamentProduct::query()->inStockFor($owner)->with('material')->orderBy('article_number')->get()
                    : collect(),
                'ownRequests' => $isAllowed
                    ? $owner->printRequests()->where('requester_email', $requester->email)->with('colors')->latest()->limit(50)->get()
                    : collect(),
            ])
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * The print request page used to be a tab next to the filament list; old links land on the share link.
     */
    public function redirectToShare(string $token): RedirectResponse
    {
        return redirect()->route('share', $token, 301);
    }

    /**
     * The verified email of the signed-in requester is the contact; it cannot be typed in.
     */
    public function store(Request $request, string $token, SubmitPrintRequest $submit): RedirectResponse
    {
        $owner = $this->ownerTakingRequests($token);
        $requester = PrintRequester::current();

        if ($requester === null) {
            return redirect()->route('share', $token);
        }
        abort_unless($owner->allowsPrintRequestsFrom($requester->email), 403);

        $submit->handle($owner, $request->except('requester_contact'), $requester->email);

        return redirect()->route('share', $token)->with('print_request_sent', true);
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

    /**
     * The owner of a working share link that takes print requests; 404 otherwise, so a link that takes none shows no sign of it.
     */
    private function ownerTakingRequests(string $token): User
    {
        $owner = User::where('share_token', $token)->firstOrFail();
        abort_unless($owner->takesPrintRequests(), 404);

        return $owner;
    }
}
