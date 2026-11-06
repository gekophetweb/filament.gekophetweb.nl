<?php

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;

/**
 * Updates keys in a .env file without touching comments or other lines,
 * creating the file (mode 0600) when it does not exist yet.
 *
 * The file is rewritten in place (open, lock, truncate, write) instead of
 * through a temp file and rename(), because a single bind-mounted file in
 * Docker cannot be replaced by rename().
 */
class EnvWriter
{
    public function __construct(
        private string $path,
        private string $backupDirectory,
    ) {}

    /**
     * True when the file exists and is writable, or can be created.
     */
    public function isWritable(): bool
    {
        if (file_exists($this->path)) {
            return is_file($this->path) && is_writable($this->path);
        }

        return is_dir(dirname($this->path)) && is_writable(dirname($this->path));
    }

    /**
     * @param  array<string, string>  $values
     */
    public function set(array $values): void
    {
        $lines = [];

        foreach ($values as $key => $value) {
            $lines[$key] = $key.'='.$this->encode($key, $value);
        }

        $isNew = ! file_exists($this->path);
        $handle = @fopen($this->path, 'c+');

        if ($handle === false) {
            throw new RuntimeException("Cannot open {$this->path} for writing.");
        }

        if ($isNew) {
            @chmod($this->path, 0600);
        }

        try {
            flock($handle, LOCK_EX);
            $original = stream_get_contents($handle);

            if ($original !== '') {
                $this->backup($original);
            }

            $contents = $this->apply($original, $lines);

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $contents);
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @param  array<string, string>  $lines  Rendered "KEY=value" lines by key.
     */
    private function apply(string $original, array $lines): string
    {
        $output = [];
        $written = [];
        $existing = $original === '' ? [] : preg_split('/\r\n|\n|\r/', rtrim($original, "\r\n"));

        foreach ($existing as $line) {
            $key = $this->keyOf($line);

            if ($key === null || ! isset($lines[$key])) {
                $output[] = $line;

                continue;
            }

            if (! isset($written[$key])) {
                $output[] = $lines[$key];
                $written[$key] = true;
            }
        }

        foreach ($lines as $key => $line) {
            if (! isset($written[$key])) {
                $output[] = $line;
            }
        }

        return implode("\n", $output)."\n";
    }

    private function keyOf(string $line): ?string
    {
        return preg_match('/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=/', $line, $match) === 1 ? $match[1] : null;
    }

    /**
     * Single quotes are fully literal in Dotenv (no escapes, no ${} interpolation),
     * so they are used whenever the value has no single quote. Otherwise the value
     * is double-quoted with backslash, quote and dollar escaped.
     */
    private function encode(string $key, string $value): string
    {
        if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key) !== 1) {
            throw new InvalidArgumentException("Invalid .env key name: {$key}");
        }

        if (preg_match('/[\r\n\0]/', $value) === 1) {
            throw new InvalidArgumentException("The value for {$key} may not contain line breaks or null bytes.");
        }

        if (! str_contains($value, "'")) {
            return "'{$value}'";
        }

        return '"'.strtr($value, ['\\' => '\\\\', '"' => '\\"', '$' => '\\$']).'"';
    }

    private function backup(string $contents): void
    {
        if (! is_dir($this->backupDirectory) && ! @mkdir($this->backupDirectory, 0700, true) && ! is_dir($this->backupDirectory)) {
            throw new RuntimeException("Cannot create backup directory {$this->backupDirectory}.");
        }

        $file = $this->backupDirectory.'/.env.'.date('Ymd-His').'-'.bin2hex(random_bytes(3));

        if (file_put_contents($file, $contents) === false) {
            throw new RuntimeException("Cannot write backup {$file}.");
        }

        @chmod($file, 0600);
    }
}
