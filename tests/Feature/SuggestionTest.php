<?php

use App\Livewire\Suggestions;
use App\Models\Suggestion;
use App\Models\User;
use App\Notifications\SuggestionReceived;
use App\Support\PrintRequester;
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

it('shows the suggestion box to allowed requesters only', function () {
    $owner = suggestionOwner('friend@example.com');

    $this->get($owner->shareUrl())->assertDontSee(suggestionUrl($owner), false);

    asSuggestionRequester('stranger@example.org');
    $this->get($owner->shareUrl())->assertDontSee(suggestionUrl($owner), false);

    asSuggestionRequester('friend@example.com');
    $this->get($owner->shareUrl())->assertSee(['Suggestion box', suggestionUrl($owner)], false);
});

it('takes a suggestion with the verified email and emails the owner', function () {
    Notification::fake();
    $owner = suggestionOwner();
    asSuggestionRequester();

    $this->post(suggestionUrl($owner), ['body' => "  Get some silk gold!\nPlease.  "])
        ->assertRedirect($owner->shareUrl().'#suggestion-box')
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

    $this->post(suggestionUrl($owner), ['body' => 'Hi'])->assertRedirect($owner->shareUrl());

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
