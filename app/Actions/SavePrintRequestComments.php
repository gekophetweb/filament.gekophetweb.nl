<?php

namespace App\Actions;

use App\Models\PrintRequest;
use App\Notifications\PrintRequestReplied;
use Illuminate\Support\Facades\Notification;

/**
 * The owner's reply (shown to the requester) and private note on a print request.
 * A new or changed reply is emailed to the requester; used by the inbox and the API.
 */
class SavePrintRequestComments
{
    /**
     * Empty text clears a field.
     *
     * @return ?string The address the reply was emailed to, or null when no email went out.
     */
    public function handle(PrintRequest $printRequest, ?string $reply, ?string $privateNote): ?string
    {
        $reply = self::clean($reply);
        $isNewReply = $reply !== null && $reply !== $printRequest->reply;

        $printRequest->forceFill(['reply' => $reply, 'private_note' => self::clean($privateNote)])->save();

        if (! $isNewReply || ! $printRequest->requester_email) {
            return null;
        }

        Notification::route('mail', [$printRequest->requester_email => $printRequest->requester_name])
            ->notify(new PrintRequestReplied($printRequest, $reply));

        return $printRequest->requester_email;
    }

    private static function clean(?string $text): ?string
    {
        $text = trim((string) $text);

        return $text === '' ? null : $text;
    }
}
