<?php

namespace App\Policies;

use App\Models\Spool;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Users only ever see their own spools. Someone else's spool answers 404,
 * so UUIDs cannot be probed for existence.
 */
class SpoolPolicy
{
    public function view(User $user, Spool $spool): Response
    {
        return $this->owns($user, $spool);
    }

    public function update(User $user, Spool $spool): Response
    {
        return $this->owns($user, $spool);
    }

    public function delete(User $user, Spool $spool): Response
    {
        return $this->owns($user, $spool);
    }

    private function owns(User $user, Spool $spool): Response
    {
        return $spool->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
