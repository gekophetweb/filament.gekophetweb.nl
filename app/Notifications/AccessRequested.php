<?php

namespace App\Notifications;

use App\Models\AccessRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

/**
 * Tells the owner that someone not on the requester list asked for access through the share link.
 * Follows the same email on/off setting as print requests.
 */
#[DeleteWhenMissingModels]
class AccessRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AccessRequest $accessRequest)
    {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(User $notifiable, string $channel): bool
    {
        return $notifiable->emails_print_requests;
    }

    public function toMail(User $notifiable): MailMessage
    {
        $request = $this->accessRequest;

        $message = (new MailMessage)
            ->subject("{$request->name} asks to request prints")
            ->greeting("{$request->name} ({$request->email}) asks to request prints")
            ->line('They signed in on your share link, but are not on your list of who may request prints.');

        if ($request->message) {
            $message->line("Their note: {$request->message}");
        }

        return $message
            ->replyTo($request->email, $request->name)
            ->action('Approve or decline', route('share-export').'#access-requests')
            ->line('Turn these emails off on the Print requests page.');
    }
}
