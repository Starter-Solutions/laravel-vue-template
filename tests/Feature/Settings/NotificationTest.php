<?php

use App\Models\User;
use App\Models\WebPushSubscription;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Minishlink\WebPush\VAPID;

test('notification settings require a signed-in user', function () {
    $this->get(route('notifications.edit'))->assertRedirect(route('login'));
    $this->patch(route('notifications.update'), ['enabled' => true])->assertRedirect(route('login'));
    $this->postJson(route('notifications.subscription.store'))->assertUnauthorized();
    $this->postJson(route('notifications.subscription.current'))->assertUnauthorized();
    $this->deleteJson(route('notifications.subscription.destroy', 1))->assertUnauthorized();
    $this->postJson(route('notifications.test'))->assertUnauthorized();
});

test('notification settings expose the public key and the saved account preference', function () {
    $user = User::factory()->create(['web_push_enabled' => true]);
    config(['services.web_push.vapid' => [
        'subject' => 'mailto:push@example.test',
        'publicKey' => 'public-key',
        'privateKey' => 'private-key',
    ]]);

    $response = $this->actingAs($user)->get(route('notifications.edit'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('settings/Notifications')
        ->where('vapidPublicKey', 'public-key')
        ->where('enabled', true)
        ->missing('vapidPrivateKey'));
});

test('users can turn account push notifications on and off', function () {
    $user = User::factory()->create();
    expect($user->refresh()->web_push_enabled)->toBeFalse();

    $this->actingAs($user)->patch(route('notifications.update'), ['enabled' => true])
        ->assertRedirect(route('notifications.edit'));
    expect($user->refresh()->web_push_enabled)->toBeTrue();

    $this->patch(route('notifications.update'), ['enabled' => false])
        ->assertRedirect(route('notifications.edit'));
    expect($user->refresh()->web_push_enabled)->toBeFalse();
    $this->postJson(route('notifications.test'))->assertForbidden();
});

test('the push test requires configured server credentials', function () {
    $user = User::factory()->create(['web_push_enabled' => true]);
    config(['services.web_push.vapid.privateKey' => null]);

    $this->actingAs($user)->postJson(route('notifications.test'))->assertServiceUnavailable();
});

test('a device registers once for the signed-in account using a supported provider', function () {
    $user = User::factory()->create(['web_push_enabled' => true]);
    $otherUser = User::factory()->create();
    $endpoint = 'https://fcm.googleapis.com/fcm/send/device';
    $keys = [
        'p256dh' => VAPID::createVapidKeys()['publicKey'],
        'auth' => 'YWFhYWFhYWFhYWFhYWFhYQ',
    ];
    $details = ['endpoint' => $endpoint, 'keys' => $keys];

    $this->actingAs($user)->postJson(route('notifications.subscription.store'), [
        'endpoint' => 'https://example.test/push', 'keys' => $keys,
    ])->assertUnprocessable()->assertJsonValidationErrors('endpoint');

    $this->postJson(route('notifications.subscription.store'), $details)->assertOk();
    $device = WebPushSubscription::query()->sole();
    $this->postJson(route('notifications.subscription.store'), $details + ['user_id' => $otherUser->id])
        ->assertOk()->assertExactJson(['id' => $device->id]);
    expect($device->refresh()->user_id)->toBe($user->id);
    expect($device->getRawOriginal('endpoint'))->not->toBe($endpoint);

    $this->actingAs($otherUser)->postJson(route('notifications.subscription.store'), [
        'endpoint' => $endpoint,
        'keys' => [...$keys, 'auth' => str_repeat('b', 22)],
    ])->assertConflict();
    expect($device->refresh()->user_id)->toBe($user->id);
});

test('a user can find and disconnect only their own push subscription', function () {
    $user = User::factory()->create();
    $device = WebPushSubscription::factory()->for($user)->create();
    $otherDevice = WebPushSubscription::factory()->create();
    $lookup = route('notifications.subscription.current');

    $this->actingAs($user)->postJson($lookup)->assertUnprocessable()->assertJsonValidationErrors('endpoint');
    $this->postJson($lookup, ['endpoint' => $device->endpoint])->assertOk()->assertExactJson(['id' => $device->id]);
    $this->postJson($lookup, ['endpoint' => $otherDevice->endpoint])->assertOk()->assertExactJson(['id' => null]);

    $this->deleteJson(route('notifications.subscription.destroy', $otherDevice))->assertNoContent();
    $this->assertModelExists($otherDevice);

    $disconnect = route('notifications.subscription.destroy', $device);
    $this->deleteJson($disconnect)->assertNoContent();
    $this->deleteJson($disconnect)->assertNoContent();
    $this->assertModelMissing($device);
    $this->postJson($lookup, ['endpoint' => $device->endpoint])->assertOk()->assertExactJson(['id' => null]);
});

test('checking a connected device refreshes only the account’s existing push encryption keys', function () {
    $user = User::factory()->create();
    $device = WebPushSubscription::factory()->for($user)->create();
    $otherDevice = WebPushSubscription::factory()->create();
    $lookup = route('notifications.subscription.current');
    $publicKey = VAPID::createVapidKeys()['publicKey'];
    $authToken = 'YmJiYmJiYmJiYmJiYmJiYg';
    $keys = ['p256dh' => $publicKey, 'auth' => $authToken];

    $this->actingAs($user)->postJson($lookup, ['endpoint' => $device->endpoint, 'keys' => ['auth' => $authToken]])
        ->assertUnprocessable()->assertJsonValidationErrors('keys.p256dh');
    $this->postJson($lookup, ['endpoint' => $device->endpoint, 'keys' => $keys])
        ->assertOk()->assertExactJson(['id' => $device->id]);

    $device->refresh();
    expect($device->public_key)->toBe($publicKey);
    expect($device->auth_token)->toBe($authToken);
    expect($device->getRawOriginal('auth_token'))->not->toBe($authToken);

    $otherAuthToken = $otherDevice->auth_token;
    $this->postJson($lookup, ['endpoint' => $otherDevice->endpoint, 'keys' => $keys])
        ->assertOk()->assertExactJson(['id' => null]);
    expect($otherDevice->refresh()->auth_token)->toBe($otherAuthToken);

    $this->postJson($lookup, ['endpoint' => 'https://fcm.googleapis.com/fcm/send/disconnected', 'keys' => $keys])
        ->assertOk()->assertExactJson(['id' => null]);
    $this->assertDatabaseCount('web_push_subscriptions', 2);
});

test('a push test sends encrypted notifications to the account’s registered devices', function () {
    config(['services.web_push.vapid' => ['subject' => 'mailto:push@example.test', ...VAPID::createVapidKeys()]]);
    $user = User::factory()->create(['web_push_enabled' => true]);
    $devices = WebPushSubscription::factory()->for($user)->count(2)->create();
    $expired = WebPushSubscription::factory()->for($user)->create();
    $otherDevice = WebPushSubscription::factory()->create();
    Http::preventStrayRequests();
    Http::fake([
        ...$devices->mapWithKeys(fn (WebPushSubscription $device) => [$device->endpoint => Http::response('', 201)])->all(),
        $expired->endpoint => Http::response('', 410),
    ]);

    $response = $this->actingAs($user)->postJson(route('notifications.test'));

    $response->assertOk()->assertExactJson(['sent' => 2, 'failed' => 1, 'expired' => [$expired->id]]);
    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => $request->url() === $devices->firstOrFail()->endpoint
        && $request->hasHeader('Authorization')
        && $request->hasHeader('Content-Encoding', 'aes128gcm')
        && $request->body() !== '');
    $this->assertModelMissing($expired);
    $this->assertModelExists($otherDevice);
});

test('a push test reports when the account has no devices registered', function () {
    config(['services.web_push.vapid' => ['subject' => 'mailto:push@example.test', ...VAPID::createVapidKeys()]]);
    $user = User::factory()->create(['web_push_enabled' => true]);
    Http::preventStrayRequests();

    $response = $this->actingAs($user)->postJson(route('notifications.test'));

    $response->assertOk()->assertExactJson(['sent' => 0, 'failed' => 0, 'expired' => []]);
});
