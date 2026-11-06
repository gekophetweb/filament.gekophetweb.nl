<?php

namespace App\Support;

use Dotenv\Dotenv;
use Dotenv\Repository\Adapter\PutenvAdapter;
use Dotenv\Repository\RepositoryBuilder;

/**
 * Loads storage/app/.env.local: the settings this installation wrote itself
 * (APP_KEY on first start, SSO and allow-lists from the setup wizard).
 *
 * It lives in the app's storage volume instead of next to docker-compose.yml,
 * so the hand-edited .env stays read-only for the containers.
 *
 * The values go into the real environment through a repository of their own,
 * before Laravel reads .env. Laravel's immutable repository then sees them as
 * set from outside and keeps them. (Loading both files through one repository
 * would not work: it may overwrite values it loaded itself.)
 */
class LocalEnvironment
{
    public const RELATIVE_PATH = 'storage/app/.env.local';

    public static function load(string $basePath): void
    {
        $path = $basePath.'/'.self::RELATIVE_PATH;

        $repository = RepositoryBuilder::createWithDefaultAdapters()
            ->addAdapter(PutenvAdapter::class)
            ->immutable()
            ->make();

        Dotenv::create($repository, dirname($path), basename($path))->safeLoad();
    }
}
