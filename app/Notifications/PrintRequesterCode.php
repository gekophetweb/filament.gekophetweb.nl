<?php

namespace App\Notifications;

use App\Http\Controllers\Auth\PrintRequesterEmailCodeController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The sign-in code for someone who wants to send a print request through a share link without SSO.
 */
class PrintRequesterCode extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $code, public string $ownerName) {}

    /**
     * @return array<int, string>
     */
    public function via(AnonymousNotifiable $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(AnonymousNotifiable $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your code to request a print from {$this->ownerName}")
            ->greeting('Your code: '.$this->code)
            ->line("Type it in on {$this->ownerName}'s print request page to send your request.")
            ->line('It works for '.PrintRequesterEmailCodeController::CODE_MINUTES.' minutes. If you did not ask for it, you can ignore this email.');
    }
}
