<?php

namespace App\Http\Controllers;

use App\Enums\AccessRequestStatus;
use App\Models\AccessRequest;
use App\Models\User;
use App\Notifications\AccessRequested;
use App\Support\PrintRequester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Someone signed in on a share link but not on the owner's requester list asks to be added.
 * One request per email: asking again while it is pending changes nothing, and a declined one stays declined.
 */
class AccessRequestController extends Controller
{
    public function store(Request $request, string $token): RedirectResponse
    {
        $owner = User::where('share_token', $token)->firstOrFail();
        abort_unless($owner->takesPrintRequests(), 404);
        $requester = PrintRequester::current();
        $back = redirect()->route(PrintRequester::pageRoute($request->input('return')), $token);

        if ($requester === null || $owner->allowsPrintRequestsFrom($requester->email)) {
            return $back;
        }

        $data = $request->validateWithBag('accessRequest', [
            'message' => ['nullable', 'string', 'max:'.AccessRequest::MAX_MESSAGE_LENGTH],
        ]);
        $email = Str::lower($requester->email);

        if ($owner->accessRequests()->where('email', $email)->exists()) {
            return $back;
        }

        $accessRequest = new AccessRequest([
            'email' => $email,
            'name' => Str::limit($requester->name ?: $email, 100, ''),
            'message' => filled($data['message'] ?? null) ? trim($data['message']) : null,
        ]);
        $accessRequest->status = AccessRequestStatus::Pending;
        $accessRequest->user()->associate($owner)->save();

        if ($owner->emails_print_requests && $owner->email) {
            $owner->notify(new AccessRequested($accessRequest));
        }

        return $back->with('access_requested', true);
    }
}
