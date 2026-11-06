<?php

use App\Support\LocalEnvironment;

return [

    /*
    | First-run setup wizard (/setup). It is open until the lock file exists
    | or SSO is already configured, and only for direct local requests
    | (127.0.0.1:8000, never through the Cloudflare Tunnel) with the token
    | printed in the container log.
    */
    'lock_path' => storage_path('app/setup.lock'),
    'token_path' => storage_path('app/setup.token'),

    // Where the wizard writes its settings (loaded before .env, see LocalEnvironment)
    // and keeps backups of earlier versions. Both are in the app storage volume.
    'env_path' => base_path(LocalEnvironment::RELATIVE_PATH),
    'backup_path' => storage_path('app/env-backups'),

    // Printed by `php artisan setup:url` (the published port in docker-compose.yml).
    'local_url' => 'http://127.0.0.1:8000',

];
