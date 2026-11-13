<?php

namespace App\Notifications;

use App\Models\Suggestion;
use App\Models\SuggestionMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Support\Str;

/**
 * Tells the owner that someone used the suggestion box on their share link, or answered the owner's reply
 * on a suggestion ($answer). Follows the same email on/off setting as print requests.
 */
#[DeleteWhenMissingModels]
class SuggestionReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Suggestion $suggestion, public ?SuggestionMessage $answer = null)
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
        $name = $this->suggestion->requester_name;

        return (new MailMessage)
            ->subject($this->answer ? "{$name} answered on their suggestion" : "Suggestion from {$name}")
            ->greeting($this->answer ? "{$name} answered on their suggestion" : "{$name} sent you a suggestion")
            ->line(Str::limit($this->answer->body ?? $this->suggestion->body, 1000))
            ->replyTo($this->suggestion->requester_email, $name)
            ->action('Open your suggestions', route('suggestions', ['suggestion' => $this->suggestion->uuid]))
            ->line('Turn these emails off on the Print requests page.');
    }
}
