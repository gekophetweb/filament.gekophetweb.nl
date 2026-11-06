<?php

use App\Support\LocalEnvironment;
use Dotenv\Dotenv;

const LOCAL_ENV_KEY = 'LOCAL_ENVIRONMENT_TEST_VALUE';

beforeEach(function () {
    $this->base = sys_get_temp_dir().'/localenv-'.bin2hex(random_bytes(4));
    mkdir($this->base.'/storage/app', 0777, true);
    file_put_contents($this->base.'/.env', LOCAL_ENV_KEY."=from-env\n");
});

afterEach(function () {
    unset($_ENV[LOCAL_ENV_KEY], $_SERVER[LOCAL_ENV_KEY]);
    putenv(LOCAL_ENV_KEY);
    @unlink($this->base.'/'.LocalEnvironment::RELATIVE_PATH);
    @unlink($this->base.'/.env');
    @rmdir($this->base.'/storage/app');
    @rmdir($this->base.'/storage');
    @rmdir($this->base);
});

it('lets values written by the installation win over .env', function () {
    file_put_contents($this->base.'/'.LocalEnvironment::RELATIVE_PATH, LOCAL_ENV_KEY."='from-local'\n");

    LocalEnvironment::load($this->base);
    Dotenv::createImmutable($this->base)->load();

    expect($_ENV[LOCAL_ENV_KEY])->toBe('from-local')
        ->and(getenv(LOCAL_ENV_KEY))->toBe('from-local');
});

it('leaves .env in charge when the installation has not written any settings yet', function () {
    LocalEnvironment::load($this->base);
    Dotenv::createImmutable($this->base)->load();

    expect($_ENV[LOCAL_ENV_KEY])->toBe('from-env');
});
