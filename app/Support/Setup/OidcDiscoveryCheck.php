<?php

namespace App\Support\Setup;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Loads an issuer's discovery document and reports what the app needs from it.
 */
class OidcDiscoveryCheck
{
    /**
     * @return list<array{label: string, status: 'ok'|'warn'|'fail', detail: string}>
     */
    public function run(string $issuer): array
    {
        $issuer = rtrim($issuer, '/');

        try {
            $response = Http::timeout(10)->acceptJson()->get($issuer.'/.well-known/openid-configuration');
        } catch (ConnectionException $e) {
            return [$this->result('Discovery document', 'fail', 'Could not connect: '.$e->getMessage())];
        }

        $document = $response->json();

        if (! $response->successful() || ! is_array($document)) {
            return [$this->result('Discovery document', 'fail', "HTTP {$response->status()} from {$issuer}/.well-known/openid-configuration")];
        }

        $checks = [$this->result('Discovery document', 'ok', 'Found')];

        $reportedIssuer = rtrim((string) ($document['issuer'] ?? ''), '/');
        $checks[] = $reportedIssuer === $issuer
            ? $this->result('Issuer matches', 'ok', $reportedIssuer)
            : $this->result('Issuer matches', 'warn', "The document says \"{$reportedIssuer}\". Use that value if sign-in fails.");

        $missing = array_values(array_filter(
            ['authorization_endpoint', 'token_endpoint', 'userinfo_endpoint'],
            fn (string $key) => blank($document[$key] ?? null),
        ));
        $checks[] = $missing === []
            ? $this->result('Sign-in, token and user info endpoints', 'ok', 'Present')
            : $this->result('Sign-in, token and user info endpoints', 'fail', 'Missing: '.implode(', ', $missing));

        $pkce = $document['code_challenge_methods_supported'] ?? null;
        $checks[] = match (true) {
            is_array($pkce) && in_array('S256', $pkce, true) => $this->result('PKCE (S256)', 'ok', 'Supported'),
            $pkce === null => $this->result('PKCE (S256)', 'warn', 'Not advertised; most providers support it anyway.'),
            default => $this->result('PKCE (S256)', 'fail', 'The provider does not support S256.'),
        };

        return $checks;
    }

    /**
     * @param  list<array{status: string}>  $checks
     */
    public static function passed(array $checks): bool
    {
        return $checks !== [] && collect($checks)->doesntContain('status', 'fail');
    }

    /**
     * @return array{label: string, status: 'ok'|'warn'|'fail', detail: string}
     */
    private function result(string $label, string $status, string $detail): array
    {
        return ['label' => $label, 'status' => $status, 'detail' => $detail];
    }
}
