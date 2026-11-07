<?php

namespace App\Http\Controllers;

use App\Actions\ExportSpools;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpoolExportController extends Controller
{
    /**
     * Download the signed-in user's spools as CSV.
     */
    public function __invoke(Request $request, ExportSpools $export): StreamedResponse
    {
        $user = $request->user();

        return response()->streamDownload(function () use ($export, $user): void {
            $handle = fopen('php://output', 'w');
            $export->write($user, $handle);
            fclose($handle);
        }, 'spools-'.now()->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
