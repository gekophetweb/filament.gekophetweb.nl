<?php

use App\Enums\SuggestionStatus;
use App\Livewire\Suggestions;
use App\Models\Suggestion;
use App\Models\SuggestionMessage;
use App\Models\User;
use App\Notifications\PrintRequesterCode;
use App\Notifications\SuggestionReceived;
use App\Notifications\SuggestionReplied;
use App\Support\PrintRequester;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/** An owner whose share link takes requests from the given list. */
function suggestionOwner(string $allowList = '*'): User
{
    $owner = user();
    $owner->rotateShareToken();
    $owner->forceFill(['print_requester_emails' => $allowList])->save();

    return $owner;
}

function asSuggestionRequester(string $email = 'sam@example.com'): void
{
    test()->withSession([PrintRequester::SESSION_KEY => ['email' => $email, 'name' => 'Sam']]);
}

function suggestionUrl(User $owner): string
{
    return route('share.suggestions.store', $owner->share_token);
}

function suggestionsPage(User $owner): string
{
    return route('share.suggestions', $owner->share_token);
}

it('gives suggestions their own tab on the share link', function () {
    $owner = suggestionOwner();
    asSuggestionRequester();

    $this->get($owner->shareUrl())->assertOk()
        ->assertSee(suggestionsPage($owner), false)
        ->assertDontSee('action="'.suggestionUrl($owner).'"', false);
    $this->get(suggestionsPage($owner))->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee(['Suggestions for', $owner->shareUrl(), 'action="'.suggestionUrl($owner).'"'], false);
});

it('shows requesters their own suggestions with the status and the conversation', function () {
    $owner = suggestionOwner();
    $mine = Suggestion::factory()->for($owner)->status(SuggestionStatus::Planned)->create(['requester_email' => 'sam@example.com', 'body' => 'Get some silk gold']);
    $mine->addMessage('Good idea, ordering it this week', fromOwner: true);
    Suggestion::factory()->for($owner)->create(['requester_email' => 'other@example.com', 'body' => 'Not my idea']);
    Suggestion::factory()->create(['requester_email' => 'sam@example.com', 'body' => 'For another owner']);
    asSuggestionRequester();

    $this->get(suggestionsPage($owner))->assertOk()
        ->assertSee(['Your suggestions', 'Planned', 'Get some silk gold', 'Good idea, ordering it this week'])
        ->assertSee(route('share.suggestions.answer', [$owner->share_token, $mine->uuid]), false)
        ->assertDontSee(['Not my idea', 'For another owner']);
});

it('lets the requester answer the owner\'s reply, which emails the owner and marks it unread', function () {
    Notification::fake();
    $owner = suggestionOwner();
    $mine = Suggestion::factory()->for($owner)->read()->create(['requester_email' => 'sam@example.com', 'requester_name' => 'Sam']);
    $mine->addMessage('Which brand?', fromOwner: true);
    asSuggestionRequester();

    $this->post(route('share.suggestions.answer', [$owner->share_token, $mine->uuid]), ['body' => 'Bambu Lab please'])
        ->assertRedirect(suggestionsPage($owner).'#suggestion-'.$mine->uuid);

    expect($mine->fresh()->messages->pluck('body')->all())->toBe(['Which brand?', 'Bambu Lab please'])
        ->and($mine->fresh()->isUnread())->toBeTrue();
    Notification::assertSentTo($owner, SuggestionReceived::class, fn (SuggestionReceived $n) => $n->answer?->body === 'Bambu Lab please'
        && $n->toMail($owner)->subject === 'Sam answered on their suggestion');
});

it('does not let a requester answer someone else\'s suggestion', function () {
    $owner = suggestionOwner();
    $theirs = Suggestion::factory()->for($owner)->create(['requester_email' => 'other@example.com']);
    asSuggestionRequester();

    $this->post(route('share.suggestions.answer', [$owner->share_token, $theirs->uuid]), ['body' => 'Hi'])->assertNotFound();
    expect($theirs->messages()->count())->toBe(0);
});

it('lets the requester delete their own suggestion with its conversation', function () {
    $owner = suggestionOwner();
    $mine = Suggestion::factory()->for($owner)->create(['requester_email' => 'sam@example.com', 'body' => 'Get some silk gold']);
    $mine->addMessage('Which brand?', fromOwner: true);
    asSuggestionRequester();

    $this->get(suggestionsPage($owner))->assertSee(route('share.suggestions.destroy', [$owner->share_token, $mine->uuid]), false);
    $this->delete(route('share.suggestions.destroy', [$owner->share_token, $mine->uuid]))
        ->assertRedirect(suggestionsPage($owner))
        ->assertSessionHas('suggestion_deleted');

    expect(Suggestion::count())->toBe(0)->and(SuggestionMessage::count())->toBe(0);
    $this->get(suggestionsPage($owner))->assertSee('Your suggestion was deleted.')->assertDontSee('Get some silk gold');
});

it('does not let a requester delete someone else\'s suggestion', function () {
    $owner = suggestionOwner();
    $theirs = Suggestion::factory()->for($owner)->create(['requester_email' => 'other@example.com']);
    $otherOwners = Suggestion::factory()->create(['requester_email' => 'sam@example.com']);

    $this->delete(route('share.suggestions.destroy', [$owner->share_token, $theirs->uuid]))->assertNotFound();

    asSuggestionRequester();
    $this->delete(route('share.suggestions.destroy', [$owner->share_token, $theirs->uuid]))->assertNotFound();
    $this->delete(route('share.suggestions.destroy', [$owner->share_token, $otherOwners->uuid]))->assertNotFound();

    expect($theirs->fresh())->not->toBeNull()->and($otherOwners->fresh())->not->toBeNull();
});

it('lets the owner set the status and reply, emailing the reply to the requester', function () {
    Notification::fake();
    $owner = suggestionOwner();
    $suggestion = Suggestion::factory()->for($owner)->create(['requester_email' => 'sam@example.com', 'requester_name' => 'Sam']);

    Livewire::actingAs($owner)->test(Suggestions::class)
        ->call('open', $suggestion->uuid)
        ->call('setStatus', $suggestion->uuid, 'planned')
        ->call('sendReply')
        ->assertHasErrors('reply')
        ->set('reply', 'Ordering it this week')
        ->call('sendReply')
        ->assertHasNoErrors()
        ->assertSet('reply', '')
        ->assertSee(['Ordering it this week', 'Sent, and emailed to sam@example.com']);

    $suggestion->refresh();
    expect($suggestion->status)->toBe(SuggestionStatus::Planned)
        ->and($suggestion->messages->sole())->from_owner->toBeTrue()->body->toBe('Ordering it this week')
        ->and($suggestion->isUnread())->toBeFalse();
    Notification::assertSentOnDemand(SuggestionReplied::class, function (SuggestionReplied $n, array $channels, AnonymousNotifiable $notifiable) use ($owner) {
        $mail = $n->toMail($notifiable);

        return $notifiable->routes['mail'] === ['sam@example.com' => 'Sam']
            && in_array('Ordering it this week', $mail->introLines, true)
            && in_array('Status: Planned.', $mail->introLines, true)
            && $mail->actionUrl === route('share.suggestions', $owner->share_token);
    });
});

it('keeps done and declined suggestions out of the open folder', function () {
    $owner = user();
    Suggestion::factory()->for($owner)->status(SuggestionStatus::Planned)->create(['requester_name' => 'Planned Pat']);
    Suggestion::factory()->for($owner)->status(SuggestionStatus::Done)->create(['requester_name' => 'Done Dana']);

    Livewire::actingAs($owner)->test(Suggestions::class)
        ->assertSee('Planned Pat')
        ->assertDontSee('Done Dana')
        ->set('show', 'all')
        ->assertSee(['Planned Pat', 'Done Dana']);
});

it('rejects a status that does not exist', function () {
    $owner = user();
    $suggestion = Suggestion::factory()->for($owner)->create();

    Livewire::actingAs($owner)->test(Suggestions::class)->call('setStatus', $suggestion->uuid, 'shipped')->assertStatus(422);
});

it('comes back to the suggestions tab after signing in with an email code', function () {
    Notification::fake();
    $owner = suggestionOwner('friend@example.com');

    $this->post(route('share.email-code', $owner->share_token), ['name' => 'Friend', 'email' => 'friend@example.com', 'return' => 'suggestions'])
        ->assertRedirect(suggestionsPage($owner));
    $code = Notification::sent(new AnonymousNotifiable, PrintRequesterCode::class)->last()->code;

    $this->post(route('share.email-code.verify', $owner->share_token), ['code' => $code, 'return' => 'suggestions'])
        ->assertRedirect(suggestionsPage($owner));
    $this->get(suggestionsPage($owner))->assertSee(['Signed in as', 'friend@example.com', 'action="'.suggestionUrl($owner).'"'], false);
});

it('shows the suggestion box to allowed requesters only', function () {
    $owner = suggestionOwner('friend@example.com');

    $this->get(suggestionsPage($owner))->assertDontSee('action="'.suggestionUrl($owner).'"', false);

    asSuggestionRequester('stranger@example.org');
    $this->get(suggestionsPage($owner))->assertDontSee('action="'.suggestionUrl($owner).'"', false);

    asSuggestionRequester('friend@example.com');
    $this->get(suggestionsPage($owner))->assertSee(['Suggestion box', 'action="'.suggestionUrl($owner).'"'], false);
});

it('takes a suggestion with the verified email and emails the owner', function () {
    Notification::fake();
    $owner = suggestionOwner();
    asSuggestionRequester();

    $this->post(suggestionUrl($owner), ['body' => "  Get some silk gold!\nPlease.  "])
        ->assertRedirect(suggestionsPage($owner))
        ->assertSessionHas('suggestion_sent');

    $suggestion = $owner->suggestions()->sole();
    expect($suggestion)->body->toBe("Get some silk gold!\nPlease.")
        ->requester_email->toBe('sam@example.com')->requester_name->toBe('Sam');
    Notification::assertSentTo($owner, SuggestionReceived::class, function (SuggestionReceived $notification) use ($owner, $suggestion) {
        $mail = $notification->toMail($owner);

        return $mail->subject === 'Suggestion from Sam'
            && $mail->replyTo === [['sam@example.com', 'Sam']]
            && $mail->actionUrl === route('suggestions', ['suggestion' => $suggestion->uuid]);
    });
});

it('does not email the owner who turned emails off', function () {
    Notification::fake();
    $owner = suggestionOwner();
    $owner->forceFill(['emails_print_requests' => false])->save();
    asSuggestionRequester();

    $this->post(suggestionUrl($owner), ['body' => 'Hi']);

    expect($owner->suggestions()->count())->toBe(1);
    Notification::assertNothingSent();
});

it('refuses suggestions from visitors who are not signed in or not allowed', function () {
    $owner = suggestionOwner('friend@example.com');

    $this->post(suggestionUrl($owner), ['body' => 'Hi'])->assertRedirect(suggestionsPage($owner));

    asSuggestionRequester('stranger@example.org');
    $this->post(suggestionUrl($owner), ['body' => 'Hi'])->assertForbidden();

    expect(Suggestion::count())->toBe(0);
});

it('takes no suggestions while the link takes no requests', function () {
    $owner = suggestionOwner('');
    asSuggestionRequester();

    $this->post(suggestionUrl($owner), ['body' => 'Hi'])->assertNotFound();
});

it('needs some text, within the limit', function () {
    $owner = suggestionOwner();
    asSuggestionRequester();

    $this->post(suggestionUrl($owner), ['body' => ''])->assertSessionHasErrors('body', null, 'suggestion');
    $this->post(suggestionUrl($owner), ['body' => str_repeat('a', Suggestion::MAX_LENGTH + 1)])->assertSessionHasErrors('body', null, 'suggestion');

    expect(Suggestion::count())->toBe(0);
});

it('limits how many suggestions one requester sends per hour', function () {
    config(['access.rate_limits.suggestions_per_hour' => 2]);
    $owner = suggestionOwner();
    asSuggestionRequester();

    $this->post(suggestionUrl($owner), ['body' => 'One'])->assertRedirect();
    $this->post(suggestionUrl($owner), ['body' => 'Two'])->assertRedirect();
    $this->post(suggestionUrl($owner), ['body' => 'Three'])->assertTooManyRequests();
});

it('lists the owner\'s suggestions and marks one read when opened', function () {
    $owner = user();
    $suggestion = Suggestion::factory()->for($owner)->create(['requester_name' => 'Sam', 'body' => 'Get some silk gold']);
    Suggestion::factory()->for($owner)->read()->create(['requester_name' => 'Alex']);
    Suggestion::factory()->create(['requester_name' => 'Someone Else']);

    $page = Livewire::actingAs($owner)->test(Suggestions::class)
        ->set('show', 'unread')
        ->assertSee('Sam')
        ->assertDontSee(['Alex', 'Someone Else'])
        ->call('open', $suggestion->uuid)
        ->assertSee(['Get some silk gold', $suggestion->requester_email]);

    expect($suggestion->fresh()->isUnread())->toBeFalse();

    $page->call('markUnread', $suggestion->uuid);
    expect($suggestion->fresh()->isUnread())->toBeTrue();

    $page->call('delete', $suggestion->uuid);
    expect($suggestion->fresh())->toBeNull();
});

it('does not let the owner open or delete someone else\'s suggestion', function () {
    $other = Suggestion::factory()->create();

    Livewire::actingAs(user())->test(Suggestions::class)->call('delete', $other->uuid)->assertNotFound();
    expect($other->fresh())->not->toBeNull();
});

it('opens the suggestion linked from the email and shows the unread count in the navigation', function () {
    $owner = user();
    $suggestion = Suggestion::factory()->for($owner)->create(['body' => 'Get some silk gold']);
    Suggestion::factory()->for($owner)->count(2)->create();

    $this->actingAs($owner)->get(route('catalog'))->assertSee('3 unread');
    $this->actingAs($owner)->get(route('suggestions', ['suggestion' => $suggestion->uuid]))->assertOk()->assertSee('Get some silk gold');

    expect($suggestion->fresh()->isUnread())->toBeFalse();
});
