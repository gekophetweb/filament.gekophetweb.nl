<?php

namespace App\Http\Controllers;

use App\Models\Suggestion;
use App\Models\User;
use App\Notifications\SuggestionReceived;
use App\Support\PrintRequester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The suggestion box on the share link: the same signed-in, allowed requesters who send print requests
 * can send the owner an idea or feedback.
 */
class SuggestionController extends Controller
{
    public function store(Request $request, string $token): RedirectResponse
    {
        $owner = User::where('share_token', $token)->firstOrFail();
        abort_unless($owner->takesPrintRequests(), 404);
        $requester = PrintRequester::current();

        if ($requester === null) {
            return redirect()->route('share', $token);
        }
        abort_unless($owner->allowsPrintRequestsFrom($requester->email), 403);

        $data = $request->validateWithBag('suggestion', [
            'body' => ['required', 'string', 'max:'.Suggestion::MAX_LENGTH],
        ], ['body.required' => 'Write your suggestion first.']);

        $suggestion = new Suggestion([
            'requester_name' => $requester->name ?: $requester->email,
            'requester_email' => $requester->email,
            'body' => trim($data['body']),
        ]);
        $suggestion->user()->associate($owner)->save();

        if ($owner->emails_print_requests && $owner->email) {
            $owner->notify(new SuggestionReceived($suggestion));
        }

        return redirect()->route('share', $token)->withFragment('suggestion-box')->with('suggestion_sent', true);
    }
}
