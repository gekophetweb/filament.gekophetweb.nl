<?php

use App\Enums\AccessRequestStatus;
use App\Livewire\PrintRequests;
use App\Livewire\ShareAndExport;
use App\Models\AccessRequest;
use App\Models\User;
use App\Notifications\AccessGranted;
use App\Notifications\AccessRequested;
use App\Support\PrintRequester;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/** An owner whose share link takes requests from the given list. */
function accessOwner(string $allowList = 'friend@example.com'): User
{
    $owner = user();
    $owner->rotateShareToken();
    $owner->forceFill(['print_requester_emails' => $allowList])->save();

    return $owner;
}

function signInAs(string $email = 'stranger@example.org', string $name = 'Sam'): void
{
    test()->withSession([PrintRequester::SESSION_KEY => ['email' => $email, 'name' => $name]]);
}

function accessRequestUrl(User $owner): string
{
    return route('share.access-requests.store', $owner->share_token);
}

it('offers a signed-in visitor who is not on the list to ask for access', function () {
    $owner = accessOwner();

    $this->get($owner->shareUrl())->assertDontSee(accessRequestUrl($owner), false);

    signInAs();
    $this->get($owner->shareUrl())->assertOk()->assertSee(['Ask '.$owner->name.' for access', accessRequestUrl($owner)], false);

    signInAs('friend@example.com');
    $this->get($owner->shareUrl())->assertDontSee(accessRequestUrl($owner), false);
});

it('records the request, emails the owner and shows it as pending', function () {
    Notification::fake();
    $owner = accessOwner();
    signInAs('Stranger@Example.org');

    $this->post(accessRequestUrl($owner), ['message' => ' I am Sam from next door ', 'return' => 'suggestions'])
        ->assertRedirect(route('share.suggestions', $owner->share_token));

    $request = $owner->accessRequests()->sole();
    expect($request)->email->toBe('stranger@example.org')->name->toBe('Sam')
        ->message->toBe('I am Sam from next door')->status->toBe(AccessRequestStatus::Pending);
    Notification::assertSentTo($owner, AccessRequested::class, function (AccessRequested $n) use ($owner) {
        $mail = $n->toMail($owner);

        return $mail->subject === 'Sam asks to request prints'
            && in_array('Their note: I am Sam from next door', $mail->introLines, true)
            && $mail->replyTo === [['stranger@example.org', 'Sam']];
    });

    $this->get($owner->shareUrl())->assertSee('You asked '.$owner->name.' for access on')->assertDontSee(accessRequestUrl($owner), false);
});

it('keeps one request per email and does not email again', function () {
    Notification::fake();
    $owner = accessOwner();
    signInAs();

    $this->post(accessRequestUrl($owner));
    $this->post(accessRequestUrl($owner), ['message' => 'Again']);

    expect($owner->accessRequests()->count())->toBe(1);
    Notification::assertSentToTimes($owner, AccessRequested::class, 1);
});

it('takes no request from someone already allowed, not signed in, or on a link that takes nothing', function () {
    $owner = accessOwner();

    $this->post(accessRequestUrl($owner))->assertRedirect($owner->shareUrl());

    signInAs('friend@example.com');
    $this->post(accessRequestUrl($owner))->assertRedirect($owner->shareUrl());

    $closed = accessOwner('');
    $this->post(accessRequestUrl($closed))->assertNotFound();

    expect(AccessRequest::count())->toBe(0);
});

it('shows a declined visitor that the owner does not take their requests, without asking again', function () {
    $owner = accessOwner();
    AccessRequest::factory()->for($owner)->declined()->create(['email' => 'stranger@example.org']);
    signInAs();

    $this->get($owner->shareUrl())
        ->assertSee('does not take print requests or suggestions from stranger@example.org')
        ->assertDontSee(accessRequestUrl($owner), false);
});

it('lets the owner approve a request, which adds the email and tells the person', function () {
    Notification::fake();
    $owner = accessOwner();
    $request = AccessRequest::factory()->for($owner)->create(['email' => 'stranger@example.org', 'name' => 'Sam', 'message' => 'Next door']);

    Livewire::actingAs($owner)->test(ShareAndExport::class)
        ->assertSee(['Access requests', 'stranger@example.org', 'Next door'])
        ->call('approveAccess', $request->id)
        ->assertSet('printRequesterEmails', "friend@example.com\nstranger@example.org")
        ->assertDontSee('Next door');

    expect($request->fresh()->status)->toBe(AccessRequestStatus::Approved)
        ->and($owner->fresh()->allowsPrintRequestsFrom('stranger@example.org'))->toBeTrue();
    Notification::assertSentOnDemand(AccessGranted::class, function (AccessGranted $n, array $channels, AnonymousNotifiable $notifiable) use ($owner) {
        return $notifiable->routes['mail'] === ['stranger@example.org' => 'Sam']
            && $n->toMail($notifiable)->actionUrl === $owner->shareUrl();
    });

    signInAs();
    $this->get($owner->shareUrl())->assertSee(route('share.print-requests.store', $owner->share_token), false);
});

it('does not add an email a list entry already covers', function () {
    $owner = accessOwner('@example.org');

    $owner->allowPrintRequester('Stranger@example.org');

    expect($owner->fresh()->printRequesterAllowList())->toBe(['@example.org']);
});

it('lets the owner decline a request without telling the person', function () {
    Notification::fake();
    $owner = accessOwner();
    $request = AccessRequest::factory()->for($owner)->create(['email' => 'stranger@example.org']);

    Livewire::actingAs($owner)->test(ShareAndExport::class)->call('declineAccess', $request->id);

    expect($request->fresh()->status)->toBe(AccessRequestStatus::Declined)
        ->and($owner->fresh()->allowsPrintRequestsFrom('stranger@example.org'))->toBeFalse();
    Notification::assertNothingSent();
});

it('does not let the owner handle someone else\'s access request', function () {
    $other = AccessRequest::factory()->create();

    Livewire::actingAs(accessOwner())->test(ShareAndExport::class)->call('approveAccess', $other->id)->assertNotFound();
    expect($other->fresh()->status)->toBe(AccessRequestStatus::Pending);
});

it('points the owner to waiting access requests from the print request inbox', function () {
    $owner = accessOwner();
    AccessRequest::factory()->for($owner)->count(2)->create();

    Livewire::actingAs($owner)->test(PrintRequests::class)->assertSee('2 people ask for access');
});
