<?php

use App\Support\EnvWriter;
use Dotenv\Dotenv;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/envwriter-'.bin2hex(random_bytes(4));
    mkdir($this->dir);
    $this->path = $this->dir.'/.env';
    $this->backups = $this->dir.'/backups';
});

afterEach(function () {
    foreach (array_merge(glob($this->backups.'/*') ?: [], glob($this->dir.'/{,.}*', GLOB_BRACE) ?: []) as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    @rmdir($this->backups);
    @rmdir($this->dir);
});

function writeEnv(string $path, string $content): EnvWriter
{
    file_put_contents($path, $content);

    return new EnvWriter($path, dirname($path).'/backups');
}

it('replaces existing keys in place and keeps comments and other lines', function () {
    $writer = writeEnv($this->path, "# SSO\nAPP_NAME=\"Spools\"\nSSO_CLIENT_ID=\nexport SSO_ISSUER=old\n\nOTHER=keep\n");

    $writer->set(['SSO_CLIENT_ID' => 'abc', 'SSO_ISSUER' => 'https://id.example.com']);

    expect(file_get_contents($this->path))->toBe(
        "# SSO\nAPP_NAME=\"Spools\"\nSSO_CLIENT_ID='abc'\nSSO_ISSUER='https://id.example.com'\n\nOTHER=keep\n"
    );
});

it('appends keys that are not in the file yet', function () {
    $writer = writeEnv($this->path, 'APP_NAME=Spools');

    $writer->set(['SSO_CLIENT_ID' => 'abc']);

    expect(file_get_contents($this->path))->toBe("APP_NAME=Spools\nSSO_CLIENT_ID='abc'\n");
});

it('removes duplicate definitions of a written key', function () {
    $writer = writeEnv($this->path, "SSO_CLIENT_ID=one\nA=1\nSSO_CLIENT_ID=two\n");

    $writer->set(['SSO_CLIENT_ID' => 'new']);

    expect(file_get_contents($this->path))->toBe("SSO_CLIENT_ID='new'\nA=1\n");
});

it('writes values that Dotenv reads back literally', function (string $value) {
    $writer = writeEnv($this->path, '');

    $writer->set(['SSO_CLIENT_SECRET' => $value]);

    expect(Dotenv::parse(file_get_contents($this->path)))->toBe(['SSO_CLIENT_SECRET' => $value]);
})->with([
    'plain' => 'abc123',
    'dollar sign is not interpolated' => 'a$b${APP_KEY}c',
    'hash is not a comment' => 'x # y',
    'backslash' => 'C:\\path\\x',
    'double quote' => 'say "hi"',
    'single quote' => "it's",
    'single quote with dollar and backslash' => "it's \$HOME \\n \"x\"",
    'empty' => '',
    'spaces and symbols' => ' ~.-_=&%! ',
]);

it('rejects values that could inject extra lines', function (string $value) {
    $writer = writeEnv($this->path, "A=1\n");

    expect(fn () => $writer->set(['SSO_CLIENT_ID' => $value]))->toThrow(InvalidArgumentException::class);
    expect(file_get_contents($this->path))->toBe("A=1\n");
})->with([
    'newline' => "abc\nAPP_DEBUG=true",
    'carriage return' => "abc\rAPP_DEBUG=true",
    'null byte' => "abc\0",
]);

it('rejects invalid key names', function (string $key) {
    $writer = writeEnv($this->path, '');

    expect(fn () => $writer->set([$key => 'x']))->toThrow(InvalidArgumentException::class);
})->with(['lower_case', 'WITH SPACE', "NEW\nLINE", '1STARTS_WITH_DIGIT', '']);

it('backs up the previous content before writing', function () {
    $writer = writeEnv($this->path, "SSO_CLIENT_ID=old\n");

    $writer->set(['SSO_CLIENT_ID' => 'new']);

    $backups = glob($this->backups.'/.env.*');
    expect($backups)->toHaveCount(1)
        ->and(file_get_contents($backups[0]))->toBe("SSO_CLIENT_ID=old\n");
});

it('reports whether the file can be written or created', function () {
    expect((new EnvWriter($this->dir.'/missing/.env.local', $this->backups))->isWritable())->toBeFalse()
        ->and((new EnvWriter($this->path, $this->backups))->isWritable())->toBeTrue()
        ->and(writeEnv($this->path, '')->isWritable())->toBeTrue();
});

it('creates a missing file readable only by its owner, without a backup', function () {
    (new EnvWriter($this->path, $this->backups))->set(['SSO_CLIENT_ID' => 'abc']);

    expect(file_get_contents($this->path))->toBe("SSO_CLIENT_ID='abc'\n")
        ->and(glob($this->backups.'/.env.*') ?: [])->toBeEmpty();

    if (DIRECTORY_SEPARATOR === '/') {
        expect(fileperms($this->path) & 0777)->toBe(0600);
    }
});
