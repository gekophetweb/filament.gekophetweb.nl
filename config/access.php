<?php

$list = fn (string $key): array => array_values(array_filter(array_map('trim', explode(',', (string) env($key, '')))));

return [

    /*
    | IP allow-lists per surface (CIDR, IPv4 and IPv6, comma separated).
    | An empty list blocks everything in production (fail closed) and
    | allows everything in other environments.
    */
    'ips' => [
        'web' => $list('ALLOWED_IPS_WEB'),
        'api' => $list('ALLOWED_IPS_API'),
        'mcp' => $list('ALLOWED_IPS_MCP'),
    ],

    // Trusted reverse proxies (the fixed Docker "frontend" subnet). Never "*".
    'trusted_proxies' => $list('TRUSTED_PROXIES'),

    /*
    | SSO login allow-list: full emails ("me@example.com") and/or domains
    | ("@example.com"). Empty means nobody can log in.
    */
    'allowed_emails' => array_map('strtolower', $list('SSO_ALLOWED_EMAILS')),

];
