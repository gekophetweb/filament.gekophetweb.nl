<?php

namespace App\Http\Controllers;

use App\Models\FilamentProduct;
use App\Models\User;
use Illuminate\Http\Response;

/**
 * Public, read-only view of someone's boxed and on-spool filament, reached through their share link.
 * Shows only filaments and counts: no purchase dates or history. Whatever the link allows,
 * visitors can always send a print request from it (see PrintRequestController).
 */
class SharedSpoolsController extends Controller
{
    public function __invoke(string $token): Response
    {
        $owner = User::where('share_token', $token)->firstOrFail();

        $products = FilamentProduct::query()
            ->withOwnedCount($owner)
            ->inStockFor($owner)
            ->with('material')
            ->orderBy('article_number')
            ->get();

        return response()
            ->view('share', [
                'owner' => $owner,
                'products' => $products,
                'boxedTotal' => $products->sum('boxed_count'),
                'onSpoolTotal' => $products->sum('on_spool_count'),
            ])
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
