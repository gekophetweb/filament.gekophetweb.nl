<?php

namespace App\Http\Controllers;

use App\Models\Suggestion;
use App\Models\User;
use App\Notifications\SuggestionReceived;
use App\Support\PrintRequester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The Suggestions tab of the share link: the same signed-in, allowed requesters who send print requests
 * send the owner ideas or feedback, see their status and the owner's replies, and answer them.
 */
class SuggestionController extends Controller
{
    public function index(string $token): Response
    {
        $owner = User::where('share_token', $token)->firstOrFail();
        $requester = PrintRequester::current();
        $isAllowed = $requester !== null && $owner->allowsPrintRequestsFrom($requester->email);

        return response()
            ->view('share-suggestions', [
                'owner' => $owner,
                'requester' => $requester,
                'isAllowed' => $isAllowed,
                'ownSuggestions' => $isAllowed
                    ? $owner->suggestions()->where('requester_email', $requester->email)->with('messages')->latest('updated_at')->limit(50)->get()
                    : collect(),
            ])
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        [$owner, $requester] = $this->allowedRequester($token);

        if ($requester === null) {
            return redirect()->route('share.suggestions', $token);
        }

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

        return redirect()->route('share.suggestions', $token)->with('suggestion_sent', true);
    }

    /**
     * The requester answers in the conversation on one of their own suggestions.
     */
    public function answer(Request $request, string $token, string $suggestion): RedirectResponse
    {
        [$owner, $requester] = $this->allowedRequester($token);

        if ($requester === null) {
            return redirect()->route('share.suggestions', $token);
        }

        // Only the requester's own suggestions on this link; anything else answers 404.
        $own = $owner->suggestions()->where('uuid', $suggestion)->where('requester_email', $requester->email)->firstOrFail();

        $data = $request->validateWithBag("answer-{$own->uuid}", [
            'body' => ['required', 'string', 'max:'.Suggestion::MAX_LENGTH],
        ], ['body.required' => 'Write your answer first.']);

        $answer = $own->addMessage($data['body'], fromOwner: false);

        if ($owner->emails_print_requests && $owner->email) {
            $owner->notify(new SuggestionReceived($own, $answer));
        }

        return redirect()->route('share.suggestions', $token)->withFragment("suggestion-{$own->uuid}")->with('answer_sent', $own->uuid);
    }

    /**
     * The link's owner and the signed-in requester (null when not signed in). 404 for a link that takes nothing,
     * 403 for a requester who is not on the owner's list.
     *
     * @return array{0: User, 1: ?PrintRequester}
     */
    private function allowedRequester(string $token): array
    {
        $owner = User::where('share_token', $token)->firstOrFail();
        abort_unless($owner->takesPrintRequests(), 404);
        $requester = PrintRequester::current();

        if ($requester !== null) {
            abort_unless($owner->allowsPrintRequestsFrom($requester->email), 403);
        }

        return [$owner, $requester];
    }
}
