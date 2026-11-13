<?php

namespace App\Livewire;

use App\Models\Suggestion;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Inbox of suggestions sent through your share link's suggestion box. Opening one marks it read.
 */
#[Layout('layouts.app')]
#[Title('Suggestions')]
class Suggestions extends Component
{
    use WithPagination;

    /** Folders: "unread" or "all". */
    public const FOLDERS = ['unread' => 'Unread', 'all' => 'All'];

    #[Url]
    public string $show = 'all';

    /** UUID of the opened suggestion. */
    #[Url(as: 'suggestion')]
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
        $suggestion = $this->find($uuid);
        $suggestion->markRead();
        $this->selected = $suggestion->uuid;
    }

    public function close(): void
    {
        $this->selected = null;
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
                ->when($this->show === 'unread', fn (Builder $query) => $query->unread())
                ->latest()
                ->paginate(25),
            'opened' => $this->selected ? $user->suggestions()->where('uuid', $this->selected)->first() : null,
            'unreadCount' => $user->suggestions()->unread()->count(),
            'shareUrl' => $user->shareUrl(),
            'takesRequests' => $user->takesPrintRequests(),
        ]);
    }
}
