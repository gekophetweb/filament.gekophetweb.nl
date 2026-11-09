<?php

use App\Enums\PrintRequestStatus;
use App\Livewire\PrintRequests;
use App\Models\FilamentProduct;
use App\Models\PrintRequest;
use App\Models\Spool;
use App\Models\User;
use App\Notifications\PrintRequestReceived;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * An owner with a share link and the given number of in-stock filaments.
 *
 * @return array{0: User, 1: Collection<int, FilamentProduct>}
 */
function sharingOwner(int $filaments = 5): array
{
    $owner = user();
    $owner->rotateShareToken();
    $products = FilamentProduct::factory()->count($filaments)->create();
    $products->each(fn (FilamentProduct $product) => Spool::factory()->for($owner)->create(['filament_product_id' => $product->id]));

    return [$owner, $products];
}

function printRequestUrl(User $owner): string
{
    return route('share.print-requests.store', $owner->share_token);
}

beforeEach(fn () => Storage::fake('local'));

it('shows the print request form on the share page without signing in', function () {
    [$owner, $products] = sharingOwner(1);

    $this->get($owner->shareUrl())->assertOk()
        ->assertSee('Request a print')
        ->assertSee(printRequestUrl($owner), false)
        ->assertSee('name="colors[]" value="'.$products->first()->id.'"', false);
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
    ])->assertRedirect($owner->shareUrl().'#print-request')->assertSessionHas('print_request_sent');

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

it('limits how many print requests one address sends per hour', function () {
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
