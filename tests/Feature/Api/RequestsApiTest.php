<?php

use App\Enums\AccessRequestStatus;
use App\Enums\PrintRequestStatus;
use App\Enums\SuggestionStatus;
use App\Models\AccessRequest;
use App\Models\FilamentProduct;
use App\Models\PrintRequest;
use App\Models\Suggestion;
use App\Notifications\AccessGranted;
use App\Notifications\PrintRequestReplied;
use App\Notifications\SuggestionReplied;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

const REQUESTS_READ = ['spools:read', 'requests:read'];
const REQUESTS_WRITE = ['spools:read', 'spools:write', 'requests:read', 'requests:write'];

it('needs the requests abilities, which tokens from before them lack', function () {
    $user = user();
    $request = PrintRequest::factory()->for($user)->create();

    Sanctum::actingAs($user, ['spools:read', 'spools:write']);
    $this->getJson('/api/v1/print-requests')->assertForbidden();
    $this->getJson('/api/v1/suggestions')->assertForbidden();
    $this->getJson('/api/v1/access-requests')->assertForbidden();

    Sanctum::actingAs($user, REQUESTS_READ);
    $this->getJson('/api/v1/print-requests')->assertOk();
    $this->patchJson("/api/v1/print-requests/{$request->uuid}", ['status' => 'accepted'])->assertForbidden();
    expect($request->fresh()->status)->toBe(PrintRequestStatus::Open);
});

it('lists your own print requests by folder, with colors and the estimate', function () {
    $user = user();
    $color = FilamentProduct::factory()->create(['article_name' => 'Jade White']);
    $open = PrintRequest::factory()->for($user)->create(['requester_name' => 'Sam', 'stl_path' => 'print-requests/a.stl', 'stl_volume' => 64000, 'stl_dimensions' => [40, 40, 40]]);
    $open->colors()->attach($color, ['position' => 0]);
    PrintRequest::factory()->for($user)->accepted()->create(['requester_name' => 'Alex']);
    PrintRequest::factory()->for($user)->printed()->create(['requester_name' => 'Done Dana']);
    $someoneElses = PrintRequest::factory()->create(['requester_name' => 'Not Mine']);
    Sanctum::actingAs($user, REQUESTS_READ);

    expect($this->getJson('/api/v1/print-requests?folder=open')->assertOk()->json('data.*.requester_name'))
        ->toEqualCanonicalizing(['Sam', 'Alex'])
        ->and($this->getJson('/api/v1/print-requests')->json('meta.total'))->toBe(3);

    $this->getJson("/api/v1/print-requests/{$open->uuid}")->assertOk()
        ->assertJsonPath('data.colors.0.article_name', 'Jade White')
        ->assertJsonPath('data.estimate.label', '≈ 24–40 g of PLA')
        ->assertJsonPath('data.stl.download_url', url("/api/v1/print-requests/{$open->uuid}/stl"))
        ->assertJsonPath('data.read_at', null);
    $this->getJson("/api/v1/print-requests/{$someoneElses->uuid}")->assertNotFound();
});

it('accepts a print request, emails a new reply and marks it read', function () {
    Notification::fake();
    $user = user();
    $request = PrintRequest::factory()->for($user)->create(['requester_name' => 'Sam', 'requester_email' => 'sam@example.com']);
    Sanctum::actingAs($user, REQUESTS_WRITE);

    $this->patchJson("/api/v1/print-requests/{$request->uuid}", ['status' => 'accepted', 'reply' => 'Ready on Friday', 'read' => true])
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted')
        ->assertJsonPath('data.reply', 'Ready on Friday')
        ->assertJsonPath('meta.reply_emailed_to', 'sam@example.com');

    expect($request->fresh())->status->toBe(PrintRequestStatus::Accepted)->read_at->not->toBeNull();
    Notification::assertSentOnDemand(PrintRequestReplied::class, fn (PrintRequestReplied $n, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === ['sam@example.com' => 'Sam']);

    // The same reply again only changes the note, so no second email.
    $this->patchJson("/api/v1/print-requests/{$request->uuid}", ['reply' => 'Ready on Friday', 'private_note' => 'Old roll'])
        ->assertJsonPath('meta.reply_emailed_to', null);
    Notification::assertSentOnDemandTimes(PrintRequestReplied::class, 1);
    $this->patchJson("/api/v1/print-requests/{$request->uuid}", ['status' => 'lost'])->assertUnprocessable();
});

it('downloads the STL and deletes the request with it', function () {
    Storage::fake('local');
    Storage::disk('local')->put('print-requests/benchy.stl', 'solid benchy');
    $user = user();
    $request = PrintRequest::factory()->for($user)->create(['stl_path' => 'print-requests/benchy.stl', 'stl_original_name' => 'benchy.stl']);
    $someoneElses = PrintRequest::factory()->create();
    Sanctum::actingAs($user, REQUESTS_WRITE);

    $this->get("/api/v1/print-requests/{$request->uuid}/stl")->assertOk()->assertDownload('benchy.stl');
    $this->deleteJson("/api/v1/print-requests/{$someoneElses->uuid}")->assertNotFound();
    $this->deleteJson("/api/v1/print-requests/{$request->uuid}")->assertNoContent();

    expect(PrintRequest::find($request->id))->toBeNull()->and($someoneElses->fresh())->not->toBeNull();
    Storage::disk('local')->assertMissing('print-requests/benchy.stl');
});

it('reads a suggestion\'s conversation, replies by email and sets its status', function () {
    Notification::fake();
    $user = user();
    $suggestion = Suggestion::factory()->for($user)->create(['requester_name' => 'Sam', 'requester_email' => 'sam@example.com']);
    $suggestion->addMessage('Thanks!', fromOwner: false);
    Suggestion::factory()->for($user)->status(SuggestionStatus::Done)->create();
    $someoneElses = Suggestion::factory()->create();
    Sanctum::actingAs($user, REQUESTS_WRITE);

    $this->getJson('/api/v1/suggestions?folder=open')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.messages_count', 1);
    $this->getJson("/api/v1/suggestions/{$suggestion->uuid}")->assertOk()->assertJsonPath('data.messages.0.from_owner', false);
    $this->getJson("/api/v1/suggestions/{$someoneElses->uuid}")->assertNotFound();

    $this->postJson("/api/v1/suggestions/{$suggestion->uuid}/messages", ['body' => 'Good idea'])
        ->assertCreated()
        ->assertJsonPath('data.from_owner', true)
        ->assertJsonPath('meta.emailed_to', 'sam@example.com');
    Notification::assertSentOnDemand(SuggestionReplied::class);
    $this->postJson("/api/v1/suggestions/{$suggestion->uuid}/messages", ['body' => ''])->assertUnprocessable();

    $this->patchJson("/api/v1/suggestions/{$suggestion->uuid}", ['status' => 'planned', 'read' => true])
        ->assertOk()->assertJsonPath('data.status', 'planned')->assertJsonCount(2, 'data.messages');
    $this->deleteJson("/api/v1/suggestions/{$suggestion->uuid}")->assertNoContent();
    expect(Suggestion::find($suggestion->id))->toBeNull();
});

it('approves an access request onto the requester list and declines another quietly', function () {
    Notification::fake();
    $user = user();
    $robin = AccessRequest::factory()->for($user)->create(['email' => 'robin@example.org', 'name' => 'Robin']);
    $kim = AccessRequest::factory()->for($user)->create(['email' => 'kim@example.org']);
    AccessRequest::factory()->for($user)->declined()->create(['email' => 'old@example.org']);
    $someoneElses = AccessRequest::factory()->create();
    Sanctum::actingAs($user, REQUESTS_WRITE);

    expect($this->getJson('/api/v1/access-requests')->assertOk()->json('data.*.email'))->toBe(['robin@example.org', 'kim@example.org'])
        ->and($this->getJson('/api/v1/access-requests?status=declined')->json('data.*.email'))->toBe(['old@example.org']);

    $this->postJson("/api/v1/access-requests/{$robin->id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
    $this->postJson("/api/v1/access-requests/{$kim->id}/decline")->assertOk()->assertJsonPath('data.status', 'declined');
    $this->postJson("/api/v1/access-requests/{$robin->id}/decline")->assertUnprocessable();
    $this->postJson("/api/v1/access-requests/{$someoneElses->id}/approve")->assertNotFound();

    expect($user->fresh()->allowsPrintRequestsFrom('robin@example.org'))->toBeTrue()
        ->and($user->fresh()->allowsPrintRequestsFrom('kim@example.org'))->toBeFalse()
        ->and($robin->fresh()->status)->toBe(AccessRequestStatus::Approved);
    Notification::assertSentOnDemandTimes(AccessGranted::class, 1);
});
