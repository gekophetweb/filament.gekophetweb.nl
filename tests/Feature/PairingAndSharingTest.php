<?php

use App\Actions\DevicePairing;
use App\Livewire\Devices;
use App\Livewire\ShareAndExport;
use App\Models\FilamentProduct;
use App\Models\Spool;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

it('shows a QR code whose one-time code the app trades for its own token', function () {
    $user = user();

    $component = Livewire::actingAs($user)->test(Devices::class)
        ->set('name', 'Pixel 9')
        ->set('access', 'write')
        ->call('showQr')
        ->assertSee('<svg', false)
        ->assertSee('Scan this with the app');

    $payload = json_decode($component->get('pairingPayload'), true);
    expect($payload['api'])->toBe(url('/api/v1'))
        ->and($user->tokens()->count())->toBe(0);

    $response = $this->postJson('/api/v1/pair', ['code' => $payload['code']])->assertCreated();
    $token = $response->json('data.token');

    expect($response->json('data.abilities'))->toBe(['spools:read', 'spools:write'])
        ->and($user->tokens()->sole()->name)->toBe('Pixel 9');

    $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email', $user->email);
    $this->postJson('/api/v1/pair', ['code' => $payload['code']])->assertUnauthorized();
});

it('rejects pairing codes that expired, were cancelled or never existed', function () {
    $user = user();
    $pairing = app(DevicePairing::class);

    $expired = $pairing->start($user, 'Old phone', ['spools:read'])['code'];
    $this->travel(DevicePairing::TTL_SECONDS + 1)->seconds();
    $this->postJson('/api/v1/pair', ['code' => $expired])->assertUnauthorized();

    $component = Livewire::actingAs($user)->test(Devices::class)->set('name', 'Tablet')->call('showQr');
    $cancelled = json_decode($component->get('pairingPayload'), true)['code'];
    $component->call('cancelQr')->assertDontSee('<svg', false);
    $this->postJson('/api/v1/pair', ['code' => $cancelled])->assertUnauthorized();

    $this->postJson('/api/v1/pair', ['code' => 'made-up'])->assertUnauthorized();
    expect($user->tokens()->count())->toBe(0);
});

it('does not let the browser change the pairing code', function () {
    Livewire::actingAs(user())->test(Devices::class)->set('pairingCode', 'x');
})->throws(CannotUpdateLockedPropertyException::class);

it('respects the token limit when pairing', function () {
    config(['access.max_tokens_per_user' => 1]);
    $user = user();
    $code = app(DevicePairing::class)->start($user, 'Phone', ['spools:read'])['code'];
    $user->createToken('existing', ['spools:read'], now()->addYear());

    $this->postJson('/api/v1/pair', ['code' => $code])->assertUnauthorized();
    Livewire::actingAs($user)->test(Devices::class)->set('name', 'Phone')->call('showQr')->assertHasErrors('name');
});

it('opens the share link on the print request page without listing the owner\'s filament', function () {
    $user = user();
    $white = FilamentProduct::factory()->create(['article_number' => '10100', 'article_name' => 'Jade White']);
    Spool::factory()->for($user)->count(2)->create(['filament_product_id' => $white->id, 'purchased_at' => '2026-02-14']);

    Livewire::actingAs($user)->test(ShareAndExport::class)->call('newShareLink')->assertSee('/share/', false);
    $url = $user->fresh()->shareUrl();

    auth()->logout();
    $this->get($url)->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('Request a print from')
        ->assertDontSee(['Jade White', '2026-02-14', 'In boxes', 'On spools']);
});

it('stops an old share link when a new one is made or sharing is turned off', function () {
    $user = user();
    $old = route('share', $user->rotateShareToken());

    Livewire::actingAs($user)->test(ShareAndExport::class)->call('newShareLink');
    $this->get($old)->assertNotFound();
    $this->get($user->fresh()->shareUrl())->assertOk();

    $current = $user->fresh()->shareUrl();
    Livewire::actingAs($user)->test(ShareAndExport::class)->call('stopSharing');
    $this->get($current)->assertNotFound();
    expect($user->fresh()->shareUrl())->toBeNull();
});
