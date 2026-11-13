<?php

namespace App\Notifications;

use App\Models\Suggestion;
use App\Models\SuggestionMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

/**
 * Emails the owner's reply on a suggestion to the requester's sign-in email, with a link to answer it.
 */
#[DeleteWhenMissingModels]
class SuggestionReplied extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Suggestion $suggestion, public SuggestionMessage $reply)
    {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(AnonymousNotifiable $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(AnonymousNotifiable $notifiable): MailMessage
    {
        $suggestion = $this->suggestion->loadMissing('user');
        $ownerName = $suggestion->user->name;

        $message = (new MailMessage)
            ->subject("{$ownerName} replied to your suggestion")
            ->greeting("Hi {$suggestion->requester_name},")
            ->line("{$ownerName} replied to your suggestion:");

        foreach (preg_split('/\R{2,}/', $this->reply->body) as $paragraph) {
            $message->line($paragraph);
        }

        $message->line('Status: '.$suggestion->status->label().'.');

        if ($suggestion->user->share_token) {
            $message->action('Answer on the website', route('share.suggestions', $suggestion->user->share_token));
        }

        return $message->line('Replying to this email does not reach '.$ownerName.'.');
    }
}
