<?php

namespace App\Mcp\Tools;

use App\Actions\SearchCatalog;
use App\Http\Resources\FilamentProductResource;
use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('running_out_report')]
#[Description('Filaments the user has on the spool holder but no boxed spool of left as a backup: the ones to reorder first. Includes a buy link for each.')]
#[IsReadOnly]
class RunningOutReportTool extends SpoolTool
{
    protected function run(Request $request, User $user): Response
    {
        $products = app(SearchCatalog::class)->query($user, ['owned' => SearchCatalog::RUNNING_OUT])->limit(200)->get();

        return Response::json(FilamentProductResource::collection($products)->resolve());
    }
}
