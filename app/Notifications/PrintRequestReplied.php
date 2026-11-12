<?php

namespace App\Notifications;

use App\Models\PrintRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

/**
 * Emails the owner's reply on a print request to the requester's sign-in email.
 * The reply is kept as it was when saved, so a later edit gets its own email.
 */
#[DeleteWhenMissingModels]
class PrintRequestReplied extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public PrintRequest $printRequest, public string $reply)
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
        $request = $this->printRequest->loadMissing('user');
        $ownerName = $request->user->name;
        $what = $request->stl_original_name ?: $request->model_url;

        $message = (new MailMessage)
            ->subject("{$ownerName} replied to your print request")
            ->greeting("Hi {$request->requester_name},")
            ->line("{$ownerName} replied to your print request".($what ? " ({$what})" : '').':');

        foreach (preg_split('/\R{2,}/', $this->reply) as $paragraph) {
            $message->line($paragraph);
        }

        $message->line('Status: '.$request->status->label().'.');

        if ($shareUrl = $request->user->shareUrl()) {
            $message->action('See your requests', $shareUrl);
        }

        return $message->line('Replying to this email does not reach '.$ownerName.'.');
    }
}
