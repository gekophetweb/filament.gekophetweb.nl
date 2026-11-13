<?php

namespace App\Livewire;

use App\Enums\SuggestionStatus;
use App\Models\Suggestion;
use App\Notifications\SuggestionReplied;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Inbox of suggestions sent through your share link's suggestion box. Opening one marks it read;
 * there you set its status and reply, and the requester sees both on the share link and can answer.
 */
#[Layout('layouts.app')]
#[Title('Suggestions')]
class Suggestions extends Component
{
    use WithPagination;

    /** Folders: "open" suggestions still to handle (open or planned), "unread", or "all". */
    public const FOLDERS = ['open' => 'Open', 'unread' => 'Unread', 'all' => 'All'];

    #[Url]
    public string $show = 'open';

    /** UUID of the opened suggestion. */
    #[Url(as: 'suggestion')]
    public ?string $selected = null;

    /** The reply being written on the opened suggestion. */
    #[Validate(['required', 'string', 'max:'.Suggestion::MAX_LENGTH])]
    public string $reply = '';

    /** Address the last reply was emailed to, for the confirmation. */
    public ?string $replyEmailedTo = null;

    public function mount(): void
    {
        if ($this->selected) {
            $this->open($this->selected);
        }
    }

    public function updatedShow(): void
    {
        $this->resetPage();
    }

    public function open(string $uuid): void
    {
        $suggestion = $this->find($uuid);
        $suggestion->markRead();
        $this->selected = $suggestion->uuid;
        $this->reset('reply', 'replyEmailedTo');
        $this->resetValidation();
    }

    public function close(): void
    {
        $this->selected = null;
    }

    public function setStatus(string $uuid, string $status): void
    {
        $newStatus = SuggestionStatus::tryFrom($status) ?? abort(422);

        $this->find($uuid)->forceFill(['status' => $newStatus])->save();
    }

    /**
     * Add a reply to the opened suggestion and email it to the requester.
     */
    public function sendReply(): void
    {
        $suggestion = $this->find((string) $this->selected);
        $this->validate();

        $message = $suggestion->addMessage($this->reply, fromOwner: true);
        Notification::route('mail', [$suggestion->requester_email => $suggestion->requester_name])
            ->notify(new SuggestionReplied($suggestion, $message));

        $this->reset('reply');
        $this->replyEmailedTo = $suggestion->requester_email;
    }

    public function markUnread(string $uuid): void
    {
        $this->find($uuid)->markUnread();
        $this->selected = null;
    }

    public function delete(string $uuid): void
    {
        $this->find($uuid)->delete();

        if ($this->selected === $uuid) {
            $this->selected = null;
        }
    }

    /**
     * Only your own suggestions; anyone else's answers 404.
     */
    private function find(string $uuid): Suggestion
    {
        return Auth::user()->suggestions()->where('uuid', $uuid)->firstOrFail();
    }

    public function render(): View
    {
        $user = Auth::user();

        return view('livewire.suggestions', [
            'suggestions' => $user->suggestions()
                ->when($this->show === 'open', fn (Builder $query) => $query->toHandle())
                ->when($this->show === 'unread', fn (Builder $query) => $query->unread())
                ->withCount('messages')
                ->latest('updated_at')
                ->paginate(25),
            'opened' => $this->selected ? $user->suggestions()->where('uuid', $this->selected)->with('messages')->first() : null,
            'counts' => [
                'open' => $user->suggestions()->toHandle()->count(),
                'unread' => $user->suggestions()->unread()->count(),
            ],
            'shareUrl' => $user->shareUrl(),
            'takesRequests' => $user->takesPrintRequests(),
        ]);
    }
}
