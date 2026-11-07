<?php

use App\Support\ApiDocumentation;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

it('serves the API docs and OpenAPI document to anyone, even from an address outside the allow-list', function () {
    config(['access.ips.web' => ['192.168.1.0/24'], 'access.ips.api' => ['192.168.1.0/24']]);
    $outsider = $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8']);

    $outsider->get('/spools')->assertForbidden();

    $outsider->get('/docs/api')->assertOk()
        ->assertSee('Filament Spools API')
        ->assertSee('/spools/{uuid}/used-up')
        ->assertSee('Mark a spool used up')
        ->assertCookieMissing(config('session.cookie'));

    $outsider->get('/docs/api/openapi.json')->assertOk()
        ->assertJsonPath('openapi', '3.1.0')
        ->assertJsonPath('servers.0.url', url('/api/v1'));
});

it('documents every API route and nothing that does not exist', function () {
    $normalize = fn (string $path) => preg_replace('/\{[^}]+\}/', '{}', $path);

    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => str_starts_with($route->uri(), 'api/v1/'))
        ->flatMap(fn (RoutingRoute $route) => collect($route->methods())
            ->reject(fn (string $method) => $method === 'HEAD')
            ->map(fn (string $method) => $method.' '.$normalize('/'.substr($route->uri(), strlen('api/v1/')))))
        ->sort()->values()->all();

    $documented = collect(app(ApiDocumentation::class)->spec()['paths'])
        ->flatMap(fn (array $methods, string $path) => collect(array_keys($methods))
            ->map(fn (string $method) => strtoupper($method).' '.$normalize($path)))
        ->sort()->values()->all();

    expect($documented)->toBe($routes);
});

it('shows the error bodies the API really sends', function () {
    $errors = app(ApiDocumentation::class)->spec()['components']['responses'];
    $example = fn (string $name) => $errors[$name]['content']['application/json']['example'];
    $readOnly = user()->createToken('phone', ['spools:read'])->plainTextToken;

    $this->getJson('/api/v1/me')->assertUnauthorized()->assertExactJson($example('Unauthenticated'));
    $this->withToken($readOnly)->postJson('/api/v1/spools', [])->assertForbidden()->assertJson($example('MissingAbility'));

    app('auth')->forgetGuards();
    $write = user()->createToken('tablet', ['spools:read', 'spools:write'])->plainTextToken;
    $this->withToken($write)->postJson('/api/v1/spools', [])->assertUnprocessable()->assertJson($example('ValidationFailed'));
});
