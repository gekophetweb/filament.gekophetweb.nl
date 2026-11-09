<?php

namespace App\Notifications;

use App\Models\PrintRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Support\Str;

/**
 * Tells the owner that someone sent a print request through their share link.
 * Sent through the queue so a slow mail server never holds up the visitor.
 */
#[DeleteWhenMissingModels]
class PrintRequestReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public PrintRequest $printRequest)
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

    /**
     * Stop if emails were turned off before the queue got to it (a deleted request drops the job).
     */
    public function shouldSend(User $notifiable, string $channel): bool
    {
        return $notifiable->emails_print_requests;
    }

    public function toMail(User $notifiable): MailMessage
    {
        $request = $this->printRequest->loadMissing('colors.material');

        $message = (new MailMessage)
            ->subject("Print request from {$request->requester_name}")
            ->greeting("{$request->requester_name} sent you a print request")
            ->line($request->stl_path
                ? "STL file: {$request->stl_original_name}"
                : 'No STL file, only a link.');

        if ($request->model_url) {
            $message->line("Model: {$request->model_url}");
        }

        $message->line($request->colors->isNotEmpty()
            ? 'Colors: '.$request->colors->map(fn ($color) => "{$color->article_name} ({$color->material->name})")->join(', ')
            : 'No colors picked.');

        if ($request->requester_contact) {
            $message->line("Contact: {$request->requester_contact}");
        }

        if ($request->notes) {
            $message->line('Notes: '.Str::limit($request->notes, 500));
        }

        if (filter_var($request->requester_contact, FILTER_VALIDATE_EMAIL)) {
            $message->replyTo($request->requester_contact, $request->requester_name);
        }

        return $message
            ->action('Open in your inbox', route('print-requests', ['request' => $request->uuid]))
            ->line('Turn these emails off on the Print requests page.');
    }
}
