<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Same as "throttle", but registered in the middleware priority list ahead of
 * authentication (bootstrap/app.php), so invalid-token floods are cut before
 * any database lookup. Plain "throttle" is always sorted after "auth".
 */
class ThrottleBeforeAuth extends ThrottleRequests
{
}
