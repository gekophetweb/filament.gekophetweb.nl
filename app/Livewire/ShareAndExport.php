<?php

namespace App\Livewire;

use App\Actions\HandleAccessRequest;
use App\Actions\ImportSpools;
use App\Models\AccessRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The print request share link and who may use it, and exporting or importing your spools as CSV.
 */
#[Layout('layouts.app')]
#[Title('Share & export')]
class ShareAndExport extends Component
{
    use WithFileUploads;

    /** The CSV to import; at most 2 MB. */
    #[Validate(['required', 'file', 'mimes:csv,txt', 'max:2048'])]
    public ?TemporaryUploadedFile $importFile = null;

    /** @var array{created: int, updated: int, unchanged: int, errors: list<string>}|null */
    public ?array $importResult = null;

    /** Who may send print requests through the share link: one email, "@domain" or "*" per line. */
    public string $printRequesterEmails = '';

    public bool $printRequestersSaved = false;

    public function import(ImportSpools $importer): void
    {
        $this->validate();

        try {
            $this->importResult = $importer->handle(Auth::user(), $this->importFile->getRealPath());
        } catch (InvalidArgumentException $e) {
            $this->addError('importFile', $e->getMessage());
        }

        $this->reset('importFile');
    }

    /** Turn the share link on, or replace it (the old link stops working). */
    public function newShareLink(): void
    {
        Auth::user()->rotateShareToken();
    }

    public function stopSharing(): void
    {
        Auth::user()->stopSharing();
    }

    public function mount(): void
    {
        $this->printRequesterEmails = implode("\n", Auth::user()->printRequesterAllowList());
    }

    public function savePrintRequesters(): void
    {
        $this->validate(['printRequesterEmails' => ['nullable', 'string', 'max:10000']]);
        $entries = User::splitAllowList($this->printRequesterEmails);

        $invalid = array_filter($entries, fn (string $entry) => ! self::isValidAllowListEntry($entry));
        if ($invalid !== []) {
            $this->addError('printRequesterEmails', 'Not an email, @domain or *: '.implode(', ', $invalid));

            return;
        }

        Auth::user()->forceFill(['print_requester_emails' => $entries === [] ? null : implode("\n", $entries)])->save();
        $this->printRequesterEmails = implode("\n", $entries);
        $this->printRequestersSaved = true;
    }

    /**
     * Add the person who asked to the requester list and email them that they can request prints now.
     */
    public function approveAccess(int $id, HandleAccessRequest $handle): void
    {
        $handle->approve($this->pendingAccessRequest($id));

        $this->printRequesterEmails = implode("\n", Auth::user()->fresh()->printRequesterAllowList());
    }

    /** Decline quietly: the person sees on the share link that you do not take their requests. */
    public function declineAccess(int $id, HandleAccessRequest $handle): void
    {
        $handle->decline($this->pendingAccessRequest($id));
    }

    /**
     * One of your own pending access requests; anyone else's answers 404.
     */
    private function pendingAccessRequest(int $id): AccessRequest
    {
        return Auth::user()->accessRequests()->pending()->findOrFail($id);
    }

    private static function isValidAllowListEntry(string $entry): bool
    {
        if ($entry === '*') {
            return true;
        }

        $email = str_starts_with($entry, '@') ? 'someone'.$entry : $entry;

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function render(): View
    {
        return view('livewire.share-and-export', [
            'shareUrl' => Auth::user()->shareUrl(),
            'accessRequests' => Auth::user()->accessRequests()->pending()->oldest()->get(),
        ]);
    }
}
