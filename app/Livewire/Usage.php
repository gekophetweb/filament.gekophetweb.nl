<?php

namespace App\Livewire;

use App\Actions\UsageStats;
use App\Enums\SpoolStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Usage across all of the user's filament: spools added and used up per month, and the most used filaments. */
#[Layout('layouts.app')]
#[Title('Usage')]
class Usage extends Component
{
    public const PERIODS = [6, 12, 24];

    #[Url(except: 12)]
    public int $months = 12;

    public function render(UsageStats $stats): View
    {
        $user = Auth::user();
        $months = in_array($this->months, self::PERIODS, true) ? $this->months : 12;
        $monthly = $stats->monthly($user, $months);

        return view('livewire.usage', [
            'monthly' => $monthly,
            'periods' => self::PERIODS,
            'totals' => [
                'added' => array_sum(array_column($monthly, 'added')),
                'used_up' => array_sum(array_column($monthly, 'used_up')),
                'boxed' => $user->spools()->where('status', SpoolStatus::Boxed)->count(),
                'on_spool' => $user->spools()->where('status', SpoolStatus::OnSpool)->count(),
            ],
            'topUsed' => $stats->topUsed($user),
        ]);
    }
}
