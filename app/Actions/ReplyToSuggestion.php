<?php

namespace App\Actions;

use App\Models\Suggestion;
use App\Models\SuggestionMessage;
use App\Notifications\SuggestionReplied;
use Illuminate\Support\Facades\Notification;

/**
 * The owner's reply in a suggestion's conversation, emailed to the requester; used by the inbox and the API.
 */
class ReplyToSuggestion
{
    public function handle(Suggestion $suggestion, string $body): SuggestionMessage
    {
        $message = $suggestion->addMessage($body, fromOwner: true);

        Notification::route('mail', [$suggestion->requester_email => $suggestion->requester_name])
            ->notify(new SuggestionReplied($suggestion, $message));

        return $message;
    }
}
