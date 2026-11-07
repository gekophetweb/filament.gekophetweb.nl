<?php

namespace App\Livewire;

use App\Actions\ImportSpools;
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
 * The read-only share link, and exporting or importing your spools as CSV.
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

    /** Turn the read-only share link on, or replace it (the old link stops working). */
    public function newShareLink(): void
    {
        Auth::user()->rotateShareToken();
    }

    public function stopSharing(): void
    {
        Auth::user()->stopSharing();
    }

    public function render(): View
    {
        return view('livewire.share-and-export', [
            'shareUrl' => Auth::user()->shareUrl(),
        ]);
    }
}
