<?php

namespace App\Policies;

use App\Models\PrintRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Only the owner of the share link a request came in through sees or handles it.
 * Someone else's request answers 404, so UUIDs cannot be probed for existence.
 */
class PrintRequestPolicy
{
    public function view(User $user, PrintRequest $printRequest): Response
    {
        return $this->owns($user, $printRequest);
    }

    public function update(User $user, PrintRequest $printRequest): Response
    {
        return $this->owns($user, $printRequest);
    }

    public function delete(User $user, PrintRequest $printRequest): Response
    {
        return $this->owns($user, $printRequest);
    }

    private function owns(User $user, PrintRequest $printRequest): Response
    {
        return $printRequest->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
