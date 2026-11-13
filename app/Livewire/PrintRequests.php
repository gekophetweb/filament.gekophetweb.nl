<?php

namespace App\Livewire;

use App\Enums\PrintRequestStatus;
use App\Models\PrintRequest;
use App\Notifications\PrintRequestReplied;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Inbox of print requests visitors sent through your share link. Opening one marks it read.
 */
#[Layout('layouts.app')]
#[Title('Print requests')]
class PrintRequests extends Component
{
    use WithPagination;

    /** Folders: "open" requests still to handle, "unread", or "all". */
    public const FOLDERS = ['open' => 'Open', 'unread' => 'Unread', 'all' => 'All'];

    #[Url]
    public string $show = 'open';

    /** UUID of the opened request. */
    #[Url(as: 'request')]
    public ?string $selected = null;

    /** Reply on the opened request; the requester sees it on the share link and gets it by email when it is new or changed. */
    #[Validate(['nullable', 'string', 'max:2000'])]
    public string $reply = '';

    /** Note on the opened request that only the owner sees. */
    #[Validate(['nullable', 'string', 'max:2000'])]
    public string $privateNote = '';

    public bool $commentsSaved = false;

    /** Where the last saved reply was emailed, if it was new or changed. */
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
        $printRequest = $this->find($uuid);
        Gate::authorize('view', $printRequest);

        $printRequest->markRead();
        $this->selected = $printRequest->uuid;
        $this->reply = (string) $printRequest->reply;
        $this->privateNote = (string) $printRequest->private_note;
        $this->commentsSaved = false;
        $this->replyEmailedTo = null;
        $this->resetValidation();
    }

    public function saveComments(): void
    {
        $printRequest = $this->find((string) $this->selected);
        Gate::authorize('update', $printRequest);
        $this->validate();

        $reply = trim($this->reply) === '' ? null : trim($this->reply);
        $isNewReply = $reply !== null && $reply !== $printRequest->reply;

        $printRequest->forceFill([
            'reply' => $reply,
            'private_note' => trim($this->privateNote) === '' ? null : trim($this->privateNote),
        ])->save();

        $this->replyEmailedTo = null;
        if ($isNewReply && $printRequest->requester_email) {
            Notification::route('mail', [$printRequest->requester_email => $printRequest->requester_name])
                ->notify(new PrintRequestReplied($printRequest, $reply));
            $this->replyEmailedTo = $printRequest->requester_email;
        }

        $this->commentsSaved = true;
    }

    public function close(): void
    {
        $this->selected = null;
    }

    public function markUnread(string $uuid): void
    {
        $printRequest = $this->find($uuid);
        Gate::authorize('update', $printRequest);

        $printRequest->markUnread();
        $this->selected = null;
    }

    public function setStatus(string $uuid, string $status): void
    {
        $newStatus = PrintRequestStatus::tryFrom($status) ?? abort(422);

        $printRequest = $this->find($uuid);
        Gate::authorize('update', $printRequest);
        $printRequest->update(['status' => $newStatus]);
    }

    public function delete(string $uuid): void
    {
        $printRequest = $this->find($uuid);
        Gate::authorize('delete', $printRequest);
        $printRequest->delete();

        if ($this->selected === $uuid) {
            $this->selected = null;
        }
    }

    /** Turn the email for each new request on or off. */
    public function toggleEmails(): void
    {
        $user = Auth::user();
        $user->forceFill(['emails_print_requests' => ! $user->emails_print_requests])->save();
    }

    private function find(string $uuid): PrintRequest
    {
        return PrintRequest::where('uuid', $uuid)->firstOrFail();
    }

    public function render(): View
    {
        $user = Auth::user();
        $requests = $user->printRequests()
            ->when($this->show === 'open', fn (Builder $query) => $query->whereIn('status', PrintRequestStatus::toHandle()))
            ->when($this->show === 'unread', fn (Builder $query) => $query->unread())
            ->with('colors')
            ->latest()
            ->paginate(25);

        $opened = $this->selected
            ? $user->printRequests()->where('uuid', $this->selected)->with('colors.material')->first()
            : null;

        return view('livewire.print-requests', [
            'requests' => $requests,
            'opened' => $opened,
            'counts' => [
                'open' => $user->printRequests()->whereIn('status', PrintRequestStatus::toHandle())->count(),
                'unread' => $user->printRequests()->unread()->count(),
            ],
            'shareUrl' => $user->shareUrl(),
            'pendingAccessRequests' => $user->accessRequests()->pending()->count(),
            'emailsOn' => $user->emails_print_requests,
            'email' => $user->email,
        ]);
    }
}
