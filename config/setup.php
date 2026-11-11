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

    /*
    | Mail settings page (/mail-settings) for installs that are already set up,
    | opened with a one-time link from `php artisan setup:mail-url`: on the
    | Docker host, or through the tunnel from an address on the web allow-list.
    | The link works for mail_token_minutes; the page then stays open that
    | long in that browser.
    */
    'mail_token_path' => storage_path('app/mail-settings.token'),
    'mail_token_minutes' => 60,

    // Printed by `php artisan setup:url` (the published port in docker-compose.yml).
    'local_url' => 'http://127.0.0.1:8000',

];
