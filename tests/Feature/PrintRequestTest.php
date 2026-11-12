<?php

use App\Enums\PrintRequestStatus;
use App\Http\Controllers\Auth\PrintRequesterEmailCodeController;
use App\Livewire\PrintRequests;
use App\Livewire\ShareAndExport;
use App\Models\FilamentProduct;
use App\Models\PrintRequest;
use App\Models\Spool;
use App\Models\User;
use App\Notifications\PrintRequesterCode;
use App\Notifications\PrintRequestReceived;
use App\Notifications\PrintRequestReplied;
use App\Support\PrintRequester;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;

/**
 * An owner with a share link that takes print requests from anyone who signs in, and the given number of in-stock filaments.
 * Unless told otherwise, the visitor is signed in as the requester sam@example.com.
 *
 * @return array{0: User, 1: Collection<int, FilamentProduct>}
 */
function sharingOwner(int $filaments = 5, ?string $requesterEmail = 'sam@example.com', string $allowList = '*'): array
{
    $owner = user();
    $owner->rotateShareToken();
    $owner->forceFill(['print_requester_emails' => $allowList])->save();
    if ($requesterEmail !== null) {
        signInAsRequester($requesterEmail);
    }
    $products = FilamentProduct::factory()->count($filaments)->create();
    $products->each(fn (FilamentProduct $product) => Spool::factory()->for($owner)->create(['filament_product_id' => $product->id]));

    return [$owner, $products];
}

function printRequestUrl(User $owner): string
{
    return route('share.print-requests.store', $owner->share_token);
}

function signInAsRequester(string $email, string $name = 'Sam'): void
{
    test()->withSession([PrintRequester::SESSION_KEY => ['email' => $email, 'name' => $name]]);
}

function requesterIdentity(string $email, array $raw = []): SocialiteUser
{
    return (new SocialiteUser)->setRaw($raw)->map(['id' => 'sub-'.$email, 'name' => 'Sam Requester', 'email' => $email]);
}

beforeEach(function () {
    Storage::fake('local');
    config(['services.sso.provider' => 'oidc']);
});

it('opens the share link on the print request form for a signed-in requester', function () {
    [$owner, $products] = sharingOwner(1);

    $this->get($owner->shareUrl())->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee(['Request a print from', 'Signed in as', 'sam@example.com'])
        ->assertSee(printRequestUrl($owner), false)
        ->assertSee('name="colors[]" value="'.$products->first()->id.'"', false);
});

it('sends old print request tab links to the share link', function () {
    [$owner] = sharingOwner(1);

    $this->get(route('share.print-request', $owner->share_token))->assertRedirect($owner->shareUrl())->assertStatus(301);
});

it('says the owner takes no requests while nobody is allowed to request prints', function () {
    [$owner] = sharingOwner(1, allowList: '');

    $this->get($owner->shareUrl())->assertOk()
        ->assertSee('is not taking print requests right now')
        ->assertDontSee(printRequestUrl($owner), false);
    $this->get(route('share.sign-in', $owner->share_token))->assertNotFound();
    $this->post(printRequestUrl($owner), ['requester_name' => 'Sam', 'model_url' => 'https://example.com/m'])->assertNotFound();
    expect(PrintRequest::count())->toBe(0);
});

it('asks visitors to sign in before they can request a print', function () {
    [$owner] = sharingOwner(1, requesterEmail: null);

    $this->get($owner->shareUrl())->assertOk()
        ->assertSee(route('share.sign-in', $owner->share_token), false)
        ->assertDontSee(printRequestUrl($owner), false);

    $this->post(printRequestUrl($owner), ['requester_name' => 'Sam', 'model_url' => 'https://example.com/m'])
        ->assertRedirect($owner->shareUrl());
    expect(PrintRequest::count())->toBe(0);
});

it('signs a requester in through the regular SSO callback without making an account', function () {
    [$owner] = sharingOwner(1, requesterEmail: null);
    Socialite::fake('oidc', requesterIdentity('Friend@Example.com'));

    $this->get(route('share.sign-in', $owner->share_token))->assertRedirect();
    $this->get(route('sso.callback'))->assertRedirect($owner->shareUrl());

    $this->assertGuest();
    expect(User::count())->toBe(1);
    $this->get($owner->shareUrl())
        ->assertSee(['friend@example.com', 'Sam Requester'])
        ->assertSee(printRequestUrl($owner), false);
});

it('does not sign in a requester whose email is not verified', function () {
    [$owner] = sharingOwner(1, requesterEmail: null);
    Socialite::fake('oidc', requesterIdentity('friend@example.com', ['email_verified' => false]));

    $this->get(route('share.sign-in', $owner->share_token));
    $this->get(route('sso.callback'))
        ->assertRedirect($owner->shareUrl())
        ->assertSessionHas('print_requester_error');

    expect(session(PrintRequester::SESSION_KEY))->toBeNull();
});

it('does not treat a later app login as a requester sign-in that was never finished', function () {
    config(['access.allowed_emails' => ['me@example.com']]);
    [$owner] = sharingOwner(1, requesterEmail: null);
    Socialite::fake('oidc', requesterIdentity('me@example.com'));

    $this->get(route('share.sign-in', $owner->share_token));
    $this->get(route('sso.redirect'));
    $this->get(route('sso.callback'))->assertRedirect(route('catalog'));

    $this->assertAuthenticated();
});

it('only takes requests from people on the owner\'s list', function () {
    [$owner] = sharingOwner(1, requesterEmail: 'stranger@example.org', allowList: "friend@example.com\n@family.example");

    $this->get($owner->shareUrl())->assertOk()
        ->assertSee('does not take print requests from stranger@example.org')
        ->assertDontSee(printRequestUrl($owner), false);
    $this->post(printRequestUrl($owner), ['requester_name' => 'Sam', 'model_url' => 'https://example.com/m'])->assertForbidden();

    signInAsRequester('kid@family.example');
    $this->post(printRequestUrl($owner), ['requester_name' => 'Kid', 'model_url' => 'https://example.com/m'])->assertSessionHasNoErrors();

    expect($owner->printRequests()->sole()->requester_contact)->toBe('kid@family.example');
});

it('lets the signed-in owner request a print through their own link', function () {
    [$owner] = sharingOwner(1, requesterEmail: null, allowList: 'friend@example.com');

    $this->actingAs($owner)->post(printRequestUrl($owner), ['requester_name' => 'Me', 'model_url' => 'https://example.com/m'])
        ->assertSessionHasNoErrors();

    expect($owner->printRequests()->sole()->requester_contact)->toBe($owner->email);
});

it('uses the verified email as the contact, whatever the form sends', function () {
    [$owner] = sharingOwner(1);

    $this->post(printRequestUrl($owner), ['requester_name' => 'Sam', 'requester_contact' => 'someone-else@example.com', 'model_url' => 'https://example.com/m']);

    expect($owner->printRequests()->sole()->requester_contact)->toBe('sam@example.com');
});

/**
 * Ask for an emailed code on the owner's share link and return the code that was mailed, or null when none was.
 */
function requestEmailCode(User $owner, string $email, string $name = 'Sam'): ?string
{
    Notification::fake();
    test()->post(route('share.email-code', $owner->share_token), ['name' => $name, 'email' => $email])
        ->assertRedirect($owner->shareUrl());

    return Notification::sent(new AnonymousNotifiable, PrintRequesterCode::class)->last()?->code;
}

it('signs in a guest with a code mailed to their email', function () {
    [$owner] = sharingOwner(1, requesterEmail: null, allowList: 'friend@example.com');

    $code = requestEmailCode($owner, 'Friend@Example.com', 'Friend');

    expect($code)->toMatch('/^\d{6}$/');
    Notification::assertSentOnDemand(PrintRequesterCode::class, fn (PrintRequesterCode $n, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === ['friend@example.com' => 'Friend']);
    $this->get($owner->shareUrl())->assertSee(['a 6-digit code is on its way', 'friend@example.com'])->assertDontSee(printRequestUrl($owner), false);

    $this->post(route('share.email-code.verify', $owner->share_token), ['code' => $code])->assertRedirect($owner->shareUrl());

    $this->assertGuest();
    $this->get($owner->shareUrl())->assertSee(['Signed in as', 'friend@example.com'])->assertSee(printRequestUrl($owner), false);
    $this->post(printRequestUrl($owner), ['requester_name' => 'Friend', 'model_url' => 'https://example.com/m'])->assertSessionHasNoErrors();
    expect($owner->printRequests()->sole()->requester_email)->toBe('friend@example.com');
});

it('mails no code to an email the owner does not allow, but says the same', function () {
    [$owner] = sharingOwner(1, requesterEmail: null, allowList: 'friend@example.com');

    expect(requestEmailCode($owner, 'stranger@example.org'))->toBeNull();

    $this->get($owner->shareUrl())->assertSee(['a 6-digit code is on its way', 'stranger@example.org']);
    $this->post(route('share.email-code.verify', $owner->share_token), ['code' => '000000'])->assertSessionHasErrors('code', null, 'emailCode');
    expect(session(PrintRequester::SESSION_KEY))->toBeNull();
});

it('rejects a wrong code, and drops the code after too many tries', function () {
    [$owner] = sharingOwner(1, requesterEmail: null, allowList: 'friend@example.com');
    $code = requestEmailCode($owner, 'friend@example.com');
    $wrong = $code === '111111' ? '222222' : '111111';
    $verify = route('share.email-code.verify', $owner->share_token);

    foreach (range(1, PrintRequesterEmailCodeController::MAX_ATTEMPTS) as $attempt) {
        $this->post($verify, ['code' => $wrong])->assertSessionHasErrors('code', null, 'emailCode');
    }
    $this->post($verify, ['code' => $code])->assertSessionHas('print_requester_error');

    expect(session(PrintRequester::SESSION_KEY))->toBeNull();
});

it('rejects an expired code', function () {
    [$owner] = sharingOwner(1, requesterEmail: null, allowList: 'friend@example.com');
    $code = requestEmailCode($owner, 'friend@example.com');

    $this->travel(PrintRequesterEmailCodeController::CODE_MINUTES + 1)->minutes();
    $this->post(route('share.email-code.verify', $owner->share_token), ['code' => $code])->assertSessionHas('print_requester_error');

    expect(session(PrintRequester::SESSION_KEY))->toBeNull();
});

it('limits how many codes one email gets per hour', function () {
    config(['access.rate_limits.print_request_codes_per_hour' => 2]);
    [$owner] = sharingOwner(1, requesterEmail: null, allowList: 'friend@example.com');
    $send = fn () => $this->post(route('share.email-code', $owner->share_token), ['name' => 'Sam', 'email' => 'friend@example.com']);

    $send()->assertRedirect();
    $send()->assertRedirect();
    $send()->assertTooManyRequests();
});

it('signs a requester out', function () {
    [$owner] = sharingOwner(1);

    $this->post(route('share.sign-out', $owner->share_token))->assertRedirect($owner->shareUrl());

    expect(session(PrintRequester::SESSION_KEY))->toBeNull();
});

it('lets the owner set who may request prints', function () {
    $owner = user();

    Livewire::actingAs($owner)->test(ShareAndExport::class)
        ->set('printRequesterEmails', "Friend@Example.com, @family.example\n\nfriend@example.com")
        ->call('savePrintRequesters')
        ->assertHasNoErrors()
        ->assertSet('printRequesterEmails', "friend@example.com\n@family.example")
        ->set('printRequesterEmails', "friend@example.com\nnot an email")
        ->call('savePrintRequesters')
        ->assertHasErrors('printRequesterEmails');

    expect($owner->fresh()->printRequesterAllowList())->toBe(['friend@example.com', '@family.example']);
});

it('shows requesters only their own requests, with the status and the owner\'s reply but not the private note', function () {
    [$owner] = sharingOwner(1);
    PrintRequest::factory()->for($owner)->printed()->create([
        'requester_email' => 'sam@example.com', 'model_url' => 'https://example.com/benchy',
        'reply' => 'Ready on Friday', 'private_note' => 'Used the old roll',
    ]);
    PrintRequest::factory()->for($owner)->create(['requester_email' => 'other@example.com', 'model_url' => 'https://example.com/not-mine']);
    PrintRequest::factory()->create(['requester_email' => 'sam@example.com', 'model_url' => 'https://example.com/other-owner']);

    $this->get($owner->shareUrl())->assertOk()
        ->assertSee(['Your requests', 'Printed', 'https://example.com/benchy', 'Ready on Friday'])
        ->assertSeeInOrder(['Your requests', 'New request', 'Send request'])
        ->assertDontSee(['Used the old roll', 'not-mine', 'other-owner']);
});

it('lets the owner reply to a request and keep a private note', function () {
    $owner = user();
    $request = PrintRequest::factory()->for($owner)->create();

    Livewire::actingAs($owner)->test(PrintRequests::class)
        ->call('open', $request->uuid)
        ->set('reply', '  Ready on Friday ')
        ->set('privateNote', 'Used the old roll')
        ->call('saveComments')
        ->assertHasNoErrors()
        ->assertSee('Saved.');

    expect($request->fresh())->reply->toBe('Ready on Friday')->private_note->toBe('Used the old roll');
});

it('emails a new reply to the requester with a link to the share page', function () {
    Notification::fake();
    [$owner] = sharingOwner(1);
    $request = PrintRequest::factory()->for($owner)->create([
        'requester_name' => 'Sam', 'requester_email' => 'sam@example.com', 'model_url' => 'https://example.com/benchy',
    ]);

    Livewire::actingAs($owner)->test(PrintRequests::class)
        ->call('open', $request->uuid)
        ->set('reply', "Ready on Friday.\n\nPick it up any time.")
        ->call('saveComments')
        ->assertSee('The reply is emailed to sam@example.com.');

    Notification::assertSentOnDemand(PrintRequestReplied::class, function (PrintRequestReplied $notification, array $channels, AnonymousNotifiable $notifiable) use ($owner, $request) {
        $mail = $notification->toMail($notifiable);

        return $notifiable->routes['mail'] === ['sam@example.com' => 'Sam']
            && $notification->printRequest->is($request)
            && $mail->subject === "{$owner->name} replied to your print request"
            && in_array('Ready on Friday.', $mail->introLines, true)
            && in_array('Pick it up any time.', $mail->introLines, true)
            && $mail->actionUrl === $owner->shareUrl();
    });
});

it('only emails a reply when it is new or changed and there is an email to send it to', function () {
    Notification::fake();
    $owner = user();
    $replied = PrintRequest::factory()->for($owner)->create(['requester_email' => 'sam@example.com', 'reply' => 'Ready on Friday']);
    $withoutEmail = PrintRequest::factory()->for($owner)->create(['requester_email' => null]);

    $component = Livewire::actingAs($owner)->test(PrintRequests::class);
    $component->call('open', $replied->uuid)->set('privateNote', 'Used the old roll')->call('saveComments');
    $component->call('open', $replied->uuid)->set('reply', '')->call('saveComments');
    $component->call('open', $withoutEmail->uuid)->set('reply', 'Hi')->call('saveComments')->assertDontSee('The reply is emailed');

    Notification::assertNothingSent();
});

it('does not let the owner reply to someone else\'s request', function () {
    $other = PrintRequest::factory()->create();

    Livewire::actingAs(user())->test(PrintRequests::class)
        ->set('selected', $other->uuid)
        ->set('reply', 'Hi')
        ->call('saveComments')
        ->assertNotFound();
    expect($other->fresh()->reply)->toBeNull();
});

it('takes a print request with an STL file and up to four colors from a visitor', function () {
    [$owner, $products] = sharingOwner();
    $picked = $products->take(4)->pluck('id')->reverse()->values();

    $this->post(printRequestUrl($owner), [
        'requester_name' => 'Sam',
        'requester_contact' => 'sam@example.com',
        'stl' => UploadedFile::fake()->create('benchy.stl', 120, 'model/stl'),
        'colors' => $picked->all(),
        'notes' => 'Two please',
    ])->assertRedirect($owner->shareUrl())->assertSessionHas('print_request_sent');

    $printRequest = $owner->printRequests()->sole();
    expect($printRequest->requester_name)->toBe('Sam')
        ->and($printRequest->status)->toBe(PrintRequestStatus::Open)
        ->and($printRequest->stl_original_name)->toBe('benchy.stl')
        ->and($printRequest->colors->pluck('id')->all())->toBe($picked->all());
    Storage::disk('local')->assertExists($printRequest->stl_path);
});

it('takes a print request with only a link to the model', function () {
    [$owner] = sharingOwner(1);

    $this->post(printRequestUrl($owner), ['requester_name' => 'Sam', 'model_url' => 'https://www.printables.com/model/1'])
        ->assertSessionHasNoErrors();

    expect($owner->printRequests()->sole())
        ->model_url->toBe('https://www.printables.com/model/1')
        ->stl_path->toBeNull();
});

it('needs a name and an STL or a link', function () {
    [$owner] = sharingOwner(1);

    $this->post(printRequestUrl($owner), [])->assertSessionHasErrors([
        'requester_name' => 'The requester name field is required.',
        'stl' => 'Upload an STL file or paste a link to the model.',
        'model_url' => 'Paste a link to the model or upload an STL file.',
    ]);
    expect(PrintRequest::count())->toBe(0);
});

it('rejects more than four colors', function () {
    [$owner, $products] = sharingOwner(5);

    $this->post(printRequestUrl($owner), ['requester_name' => 'Sam', 'model_url' => 'https://example.com/m', 'colors' => $products->pluck('id')->all()])
        ->assertSessionHasErrors(['colors' => 'Pick at most 4 colors.']);
    expect(PrintRequest::count())->toBe(0);
});

it('only accepts colors the share link shows', function () {
    [$owner] = sharingOwner(1);
    $notInStock = FilamentProduct::factory()->create();
    Spool::factory()->for($owner)->usedUp()->create(['filament_product_id' => $notInStock->id]);

    $this->post(printRequestUrl($owner), ['requester_name' => 'Sam', 'model_url' => 'https://example.com/m', 'colors' => [$notInStock->id]])
        ->assertSessionHasErrors(['colors.0' => 'Pick colors from the list.']);
});

it('rejects files that are not STL and links that are not http', function () {
    [$owner] = sharingOwner(1);

    $this->post(printRequestUrl($owner), ['requester_name' => 'Sam', 'stl' => UploadedFile::fake()->create('page.html', 1, 'text/html')])
        ->assertSessionHasErrors('stl');
    $this->post(printRequestUrl($owner), ['requester_name' => 'Sam', 'model_url' => 'javascript:alert(1)'])
        ->assertSessionHasErrors('model_url');
    expect(PrintRequest::count())->toBe(0);
});

it('rejects STL files over the size limit', function () {
    config(['access.print_request_max_upload_mb' => 1]);
    [$owner] = sharingOwner(1);

    $this->post(printRequestUrl($owner), ['requester_name' => 'Sam', 'stl' => UploadedFile::fake()->create('big.stl', 1025)])
        ->assertSessionHasErrors('stl');
});

it('does not take print requests through a link that was turned off', function () {
    [$owner] = sharingOwner(1);
    $url = printRequestUrl($owner);
    $owner->stopSharing();

    $this->post($url, ['requester_name' => 'Sam', 'model_url' => 'https://example.com/m'])->assertNotFound();
    expect(PrintRequest::count())->toBe(0);
});

it('limits how many print requests one requester sends per hour', function () {
    config(['access.rate_limits.print_requests_per_hour' => 2]);
    [$owner] = sharingOwner(1);
    $input = ['requester_name' => 'Sam', 'model_url' => 'https://example.com/m'];

    $this->post(printRequestUrl($owner), $input)->assertRedirect();
    $this->post(printRequestUrl($owner), $input)->assertRedirect();
    $this->post(printRequestUrl($owner), $input)->assertTooManyRequests();
});

it('lists open requests for the owner and lets them mark one printed', function () {
    $owner = user();
    $color = FilamentProduct::factory()->create(['article_name' => 'Jade White']);
    $open = PrintRequest::factory()->for($owner)->create(['requester_name' => 'Sam']);
    $open->colors()->attach($color, ['position' => 0]);
    PrintRequest::factory()->for($owner)->printed()->create(['requester_name' => 'Old Alex']);
    PrintRequest::factory()->create(['requester_name' => 'Someone Else']);

    Livewire::actingAs($owner)->test(PrintRequests::class)
        ->assertSee('Sam')
        ->assertDontSee(['Old Alex', 'Someone Else'])
        ->call('setStatus', $open->uuid, 'printed')
        ->assertDontSee('Sam')
        ->set('show', 'all')
        ->assertSee(['Sam', 'Old Alex'])
        ->assertDontSee('Someone Else');

    expect($open->fresh()->status)->toBe(PrintRequestStatus::Printed);
});

it('lets the owner accept or decline a new request, keeping accepted ones in the open folder', function () {
    $owner = user();
    $toAccept = PrintRequest::factory()->for($owner)->create(['requester_name' => 'Sam']);
    $toDecline = PrintRequest::factory()->for($owner)->create(['requester_name' => 'Alex']);

    $inbox = Livewire::actingAs($owner)->test(PrintRequests::class)
        ->call('open', $toAccept->uuid)
        ->assertSee(['Accept', 'Decline'])
        ->assertDontSee('Mark printed')
        ->call('setStatus', $toAccept->uuid, 'accepted')
        ->assertSee(['Mark printed', 'Decline'])
        ->call('setStatus', $toDecline->uuid, 'declined');

    expect($toAccept->fresh()->status)->toBe(PrintRequestStatus::Accepted)
        ->and($toDecline->fresh()->status)->toBe(PrintRequestStatus::Declined);
    $inbox->call('close')->assertSee('Sam')->assertDontSee('Alex')->assertViewHas('counts', fn (array $counts) => $counts['open'] === 1);
});

it('lets the requester delete their own request and its STL from the share link', function () {
    Storage::fake('local');
    [$owner] = sharingOwner(1);
    Storage::disk('local')->put('print-requests/mine.stl', 'solid');
    $mine = PrintRequest::factory()->for($owner)->create(['requester_email' => 'sam@example.com', 'stl_path' => 'print-requests/mine.stl']);
    $theirs = PrintRequest::factory()->for($owner)->create(['requester_email' => 'other@example.com']);
    $elsewhere = PrintRequest::factory()->create(['requester_email' => 'sam@example.com']);

    $this->delete(route('share.print-requests.destroy', [$owner->share_token, $mine]))
        ->assertRedirect($owner->shareUrl())->assertSessionHas('print_request_deleted');
    $this->delete(route('share.print-requests.destroy', [$owner->share_token, $theirs]))->assertNotFound();
    $this->delete(route('share.print-requests.destroy', [$owner->share_token, $elsewhere]))->assertNotFound();

    expect(PrintRequest::find($mine->id))->toBeNull()
        ->and($theirs->fresh())->not->toBeNull()
        ->and($elsewhere->fresh())->not->toBeNull();
    Storage::disk('local')->assertMissing('print-requests/mine.stl');
});

it('does not let a visitor who is not signed in delete a request', function () {
    [$owner] = sharingOwner(1, requesterEmail: null);
    $request = PrintRequest::factory()->for($owner)->create(['requester_email' => 'sam@example.com']);

    $this->delete(route('share.print-requests.destroy', [$owner->share_token, $request]))->assertNotFound();
    expect($request->fresh())->not->toBeNull();
});

it('marks a request read when the owner opens it, and unread again on request', function () {
    $owner = user();
    $color = FilamentProduct::factory()->create(['article_name' => 'Jade White']);
    $request = PrintRequest::factory()->for($owner)->create(['requester_name' => 'Sam', 'notes' => 'Two please']);
    $request->colors()->attach($color, ['position' => 0]);
    PrintRequest::factory()->for($owner)->create(['read_at' => now(), 'requester_name' => 'Already Read']);

    $inbox = Livewire::actingAs($owner)->test(PrintRequests::class)
        ->set('show', 'unread')
        ->assertSee('Sam')
        ->assertDontSee(['Already Read', 'Two please'])
        ->call('open', $request->uuid)
        ->assertSee(['Two please', 'Jade White']);

    expect($request->fresh()->isUnread())->toBeFalse();

    $inbox->call('markUnread', $request->uuid)->assertDontSee('Two please');
    expect($request->fresh()->isUnread())->toBeTrue();
});

it('opens and marks read the request linked from the email', function () {
    $owner = user();
    $request = PrintRequest::factory()->for($owner)->create(['notes' => 'Two please']);

    $this->actingAs($owner)->get(route('print-requests', ['request' => $request->uuid]))
        ->assertOk()->assertSee('Two please');

    expect($request->fresh()->isUnread())->toBeFalse();
});

it('shows the unread count in the navigation', function () {
    $owner = user();
    PrintRequest::factory()->for($owner)->count(3)->create();
    PrintRequest::factory()->for($owner)->create(['read_at' => now()]);

    $this->actingAs($owner)->get(route('catalog'))->assertSee('3 unread');
});

it('emails the owner about a new request, replying to the requester', function () {
    Notification::fake();
    [$owner, $products] = sharingOwner(1);

    $this->post(printRequestUrl($owner), [
        'requester_name' => 'Sam',
        'requester_contact' => 'sam@example.com',
        'model_url' => 'https://www.printables.com/model/1',
        'colors' => [$products->first()->id],
    ]);

    $printRequest = $owner->printRequests()->sole();
    Notification::assertSentTo($owner, PrintRequestReceived::class, function (PrintRequestReceived $notification) use ($owner, $printRequest, $products) {
        $mail = $notification->toMail($owner);

        return $notification->printRequest->is($printRequest)
            && $mail->subject === 'Print request from Sam'
            && $mail->replyTo === [['sam@example.com', 'Sam']]
            && $mail->actionUrl === route('print-requests', ['request' => $printRequest->uuid])
            && in_array('Model: https://www.printables.com/model/1', $mail->introLines, true)
            && in_array("Colors: {$products->first()->article_name} ({$products->first()->material->name})", $mail->introLines, true);
    });
});

it('does not email the owner once they turn the emails off', function () {
    Notification::fake();
    [$owner] = sharingOwner(1);

    Livewire::actingAs($owner)->test(PrintRequests::class)->call('toggleEmails');
    expect($owner->fresh()->emails_print_requests)->toBeFalse();

    $this->post(printRequestUrl($owner->fresh()), ['requester_name' => 'Sam', 'model_url' => 'https://example.com/m']);

    expect($owner->printRequests()->count())->toBe(1);
    Notification::assertNothingSent();
});

it('does not let the owner handle someone else\'s request', function () {
    $other = PrintRequest::factory()->create();

    Livewire::actingAs(user())->test(PrintRequests::class)->call('delete', $other->uuid)->assertNotFound();
    expect($other->fresh())->not->toBeNull();
});

it('lets only the owner download the STL, as an attachment', function () {
    [$owner] = sharingOwner(1);
    $this->post(printRequestUrl($owner), ['requester_name' => 'Sam', 'stl' => UploadedFile::fake()->createWithContent('benchy.stl', 'solid benchy')]);
    $printRequest = $owner->printRequests()->sole();
    $url = route('print-requests.stl', $printRequest);

    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs(user())->get($url)->assertNotFound();
    $this->actingAs($owner)->get($url)->assertOk()
        ->assertDownload('benchy.stl')
        ->assertHeader('Content-Type', 'application/octet-stream');
});

it('deletes the STL file together with the request', function () {
    [$owner] = sharingOwner(1);
    $this->post(printRequestUrl($owner), ['requester_name' => 'Sam', 'stl' => UploadedFile::fake()->create('benchy.stl', 10)]);
    $printRequest = $owner->printRequests()->sole();

    Livewire::actingAs($owner)->test(PrintRequests::class)->call('delete', $printRequest->uuid);

    expect(PrintRequest::count())->toBe(0);
    Storage::disk('local')->assertMissing($printRequest->stl_path);
});
