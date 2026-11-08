<?php

use App\Http\Middleware\EnsureIpAllowed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

function ipCheck(string $remoteAddr, array $headers = []): int
{
    $request = Request::create('/x', 'GET', server: ['REMOTE_ADDR' => $remoteAddr]);
    foreach ($headers as $k => $v) {
        $request->headers->set($k, $v);
    }

    return (new EnsureIpAllowed)->handle($request, fn () => response('ok'), 'web')->getStatusCode();
}

it('allows listed IPs and CIDR ranges, IPv4 and IPv6', function (string $ip, int $status) {
    config(['access.ips.web' => ['192.168.1.0/24', '10.0.0.5', '2001:db8::/32']]);

    expect(ipCheck($ip))->toBe($status);
})->with([
    ['192.168.1.77', 200],
    ['10.0.0.5', 200],
    ['10.0.0.6', 403],
    ['2001:db8:1::1', 200],
    ['2001:db9::1', 403],
    ['8.8.8.8', 403],
]);

it('fails closed on an empty list in production', function () {
    config(['access.ips.web' => []]);
    app()->detectEnvironment(fn () => 'production');

    expect(ipCheck('192.168.1.1'))->toBe(403);
});

it('allows everything with an empty list outside production', function () {
    config(['access.ips.web' => []]);

    expect(ipCheck('8.8.8.8'))->toBe(200);
});

it('ignores spoofed X-Forwarded-For from untrusted clients', function () {
    config(['access.ips.web' => ['192.168.1.0/24']]);
    Request::setTrustedProxies(['172.28.0.0/24'], Request::HEADER_X_FORWARDED_FOR);

    // Direct client lies about its address: still blocked.
    expect(ipCheck('8.8.8.8', ['X-Forwarded-For' => '192.168.1.10']))->toBe(403);
    // Through the trusted proxy (cloudflared), the forwarded client IP counts.
    expect(ipCheck('172.28.0.3', ['X-Forwarded-For' => '192.168.1.10']))->toBe(200);
    expect(ipCheck('172.28.0.3', ['X-Forwarded-For' => '192.168.1.10, 8.8.8.8']))->toBe(403);
});

it('takes the scheme and client IP only from the configured proxy, through the HTTP stack', function (string $remoteAddr, string $seen) {
    config(['trustedproxy.proxies' => ['172.28.0.0/24']]);
    Route::get('/_proxy-check', fn (Request $request) => $request->getScheme().' '.$request->ip());

    $this->withServerVariables(['REMOTE_ADDR' => $remoteAddr])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '192.168.1.10'])
        ->get('/_proxy-check')
        ->assertContent($seen);
})->with([
    'cloudflared' => ['172.28.0.3', 'https 192.168.1.10'],
    'untrusted client' => ['8.8.8.8', 'http 8.8.8.8'],
]);

it('reads the trusted proxies from TRUSTED_PROXIES into config, so they survive config caching', function () {
    $_SERVER['TRUSTED_PROXIES'] = '172.28.0.0/24, 10.1.0.0/16';
    $proxies = (require config_path('trustedproxy.php'))['proxies'];
    unset($_SERVER['TRUSTED_PROXIES']);

    expect($proxies)->toBe(['172.28.0.0/24', '10.1.0.0/16']);
});

it('blocks web routes with a bare 403 and logs the attempt', function () {
    $log = tempnam(sys_get_temp_dir(), 'ip');
    config(['access.ips.web' => ['192.168.1.0/24'], 'logging.channels.blocked_ips.path' => $log]);

    $this->get('/login')->assertForbidden()->assertContent('Forbidden');

    expect(file_get_contents($log))->toContain('Blocked request')->toContain('"ip":"127.0.0.1"')->toContain('"path":"login"');
});

it('checks the IP before authentication on the API and MCP', function () {
    config(['access.ips.api' => ['192.168.1.0/24'], 'access.ips.mcp' => ['192.168.1.0/24']]);

    $this->getJson('/api/v1/me')->assertForbidden();
    $this->postJson('/mcp', [])->assertForbidden();
});

it('reads * as any IPv4 or IPv6 address', function () {
    $_SERVER['ALLOWED_IPS_WEB'] = '*';
    config(['access.ips.web' => (require config_path('access.php'))['ips']['web']]);
    unset($_SERVER['ALLOWED_IPS_WEB']);
    app()->detectEnvironment(fn () => 'production');

    expect(ipCheck('8.8.8.8'))->toBe(200)
        ->and(ipCheck('2001:db9::1'))->toBe(200);
});
