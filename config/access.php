<?php

$list = fn (string $key): array => array_values(array_filter(array_map('trim', explode(',', (string) env($key, '')))));

// "*" means any address.
$ips = fn (string $key): array => in_array('*', $list($key), true) ? ['0.0.0.0/0', '::/0'] : $list($key);

return [

    /*
    | IP allow-lists per surface (CIDR, IPv4 and IPv6, comma separated, or
    | "*" for any address). An empty list blocks everything in production
    | (fail closed) and allows everything in other environments.
    */
    'ips' => [
        'web' => $ips('ALLOWED_IPS_WEB'),
        'api' => $ips('ALLOWED_IPS_API'),
        'mcp' => $ips('ALLOWED_IPS_MCP'),
    ],

    /*
    | SSO login allow-list: full emails ("me@example.com") and/or domains
    | ("@example.com"), or "*" so anyone with a verified email at the
    | identity provider can create an account. Empty means nobody can log in.
    */
    'allowed_emails' => array_map('strtolower', $list('SSO_ALLOWED_EMAILS')),

    /*
    | API and MCP rate limits. Per user (all of a user's tokens share them),
    | except the IP limit, which runs before token authentication.
    */
    'rate_limits' => [
        'api_ip_per_minute' => (int) env('RATE_LIMIT_API_IP_PER_MINUTE', 300),
        'api_read_per_minute' => (int) env('RATE_LIMIT_API_READ_PER_MINUTE', 120),
        'api_write_per_minute' => (int) env('RATE_LIMIT_API_WRITE_PER_MINUTE', 30),
        'api_write_per_day' => (int) env('RATE_LIMIT_API_WRITE_PER_DAY', 1000),
        'mcp_per_minute' => (int) env('RATE_LIMIT_MCP_PER_MINUTE', 60),
        'print_requests_per_hour' => (int) env('RATE_LIMIT_PRINT_REQUESTS_PER_HOUR', 10),
        // Emailed sign-in codes for print requesters, per IP and per email address.
        'print_request_codes_per_hour' => (int) env('RATE_LIMIT_PRINT_REQUEST_CODES_PER_HOUR', 5),
    ],

    // Largest STL a visitor may upload with a print request through a share link, in megabytes.
    // PHP's upload_max_filesize and post_max_size must allow at least this much (the Docker image sets 64M).
    'print_request_max_upload_mb' => (int) env('PRINT_REQUEST_MAX_UPLOAD_MB', 50),

    // Price in euros of one 1 kg PLA spool, for the cost in print request estimates.
    'print_request_spool_price' => (float) env('PRINT_REQUEST_SPOOL_PRICE', 18),

    // Active API/MCP tokens one user may have at the same time.
    'max_tokens_per_user' => (int) env('MAX_TOKENS_PER_USER', 10),

];
