<?php

namespace App\Livewire;

use App\Actions\SearchCatalog;
use App\Livewire\Concerns\ManagesInventory;
use App\Models\FilamentProduct;
use App\Models\Material;
use App\Support\Color;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Session;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Catalog')]
class Catalog extends Component
{
    use ManagesInventory, WithPagination;

    /** @var list<string> */
    public const DISPLAYS = ['list', 'blocks'];

    /** List or blocks; remembered in the session so it sticks while browsing. */
    #[Session]
    public string $display = 'list';

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $material = '';

    #[Url(except: '')]
    public string $line = '';

    #[Url(except: '')]
    public string $color = '';

    #[Url(except: '')]
    public string $owned = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['q', 'material', 'line', 'color', 'owned'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('q', 'material', 'line', 'color', 'owned');
        $this->resetPage();
    }

    public function render(SearchCatalog $search): View
    {
        $filters = [
            'q' => mb_substr($this->q, 0, 100),
            'material' => $this->material,
            'line' => $this->line,
            'color' => $this->color,
            'owned' => in_array($this->owned, SearchCatalog::OWNED_FILTERS, true) ? $this->owned : '',
        ];

        return view('livewire.catalog', [
            'display' => in_array($this->display, self::DISPLAYS, true) ? $this->display : 'list',
            'products' => $search->paginate(Auth::user(), $filters, 50),
            'materials' => Material::orderBy('name')->pluck('name'),
            'lines' => FilamentProduct::whereNotNull('line')->distinct()->orderBy('line')->pluck('line'),
            'families' => Color::families(),
        ]);
    }
}
