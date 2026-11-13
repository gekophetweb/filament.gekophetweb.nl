<?php

namespace App\Notifications;

use App\Models\AccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

/**
 * Tells someone who asked for access that the owner approved it, with a link to the share link.
 */
#[DeleteWhenMissingModels]
class AccessGranted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AccessRequest $accessRequest)
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
        $owner = $this->accessRequest->loadMissing('user')->user;

        $message = (new MailMessage)
            ->subject("{$owner->name} gave you access to request prints")
            ->greeting("Hi {$this->accessRequest->name},")
            ->line("{$owner->name} added you, so you can now send print requests and suggestions with {$this->accessRequest->email}.");

        if ($shareUrl = $owner->shareUrl()) {
            $message->action('Request a print', $shareUrl);
        }

        return $message->line("Replying to this email does not reach {$owner->name}.");
    }
}
