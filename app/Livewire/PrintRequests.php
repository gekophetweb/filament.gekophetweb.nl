<?php

namespace App\Livewire;

use App\Enums\PrintRequestStatus;
use App\Models\PrintRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
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
            ->when($this->show === 'open', fn (Builder $query) => $query->where('status', PrintRequestStatus::Open))
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
                'open' => $user->printRequests()->where('status', PrintRequestStatus::Open)->count(),
                'unread' => $user->printRequests()->unread()->count(),
            ],
            'shareUrl' => $user->shareUrl(),
            'emailsOn' => $user->emails_print_requests,
            'email' => $user->email,
        ]);
    }
}
