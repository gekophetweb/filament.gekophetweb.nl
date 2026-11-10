<?php

namespace App\Support\Mail;

use RuntimeException;

/**
 * RSA keys for DKIM: making one, checking a pasted one, and the DNS record that publishes its public half.
 * In .env the private key is kept base64 encoded, because a .env value cannot span lines.
 */
final class DkimKey
{
    public const BITS = 2048;

    public static function generate(): string
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => self::BITS]);

        if ($key === false || ! openssl_pkey_export($key, $pem)) {
            throw new RuntimeException('Could not generate a DKIM key: '.openssl_error_string());
        }

        return $pem;
    }

    public static function isValid(string $pem): bool
    {
        $key = @openssl_pkey_get_private(trim($pem));

        return $key !== false && (openssl_pkey_get_details($key)['type'] ?? null) === OPENSSL_KEYTYPE_RSA;
    }

    /**
     * The TXT record to publish at {selector}._domainkey.{domain}.
     *
     * @return array{name: string, value: string}
     */
    public static function dnsRecord(string $pem, string $selector, string $domain): array
    {
        $key = openssl_pkey_get_private(trim($pem));
        $publicPem = $key === false ? '' : (string) (openssl_pkey_get_details($key)['key'] ?? '');
        $publicKey = preg_replace('/-----[A-Z ]+-----|\s+/', '', $publicPem);

        return [
            'name' => "{$selector}._domainkey.{$domain}",
            'value' => "v=DKIM1; k=rsa; p={$publicKey}",
        ];
    }

    public static function encode(string $pem): string
    {
        return base64_encode(trim($pem)."\n");
    }

    /**
     * The PEM from a base64 .env value; a value that already is PEM is returned as it is.
     */
    public static function decode(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '-----BEGIN')) {
            return $value;
        }

        $pem = base64_decode($value, true);

        return $pem === false ? null : $pem;
    }
}
