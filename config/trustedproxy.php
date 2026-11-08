<?php

/*
| Trusted reverse proxies (the fixed Docker "frontend" subnet of cloudflared,
| comma separated). Never "*". Laravel's TrustProxies middleware reads this on
| every request; it must be config, not env() in bootstrap/app.php, because
| `php artisan optimize` caches config and production then never loads .env.
*/
return [

    'proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),

];
