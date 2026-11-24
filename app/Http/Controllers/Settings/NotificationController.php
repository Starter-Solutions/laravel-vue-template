<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\WebPushSubscription;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class NotificationController extends Controller
{
    private const array SUBSCRIPTION_KEY_RULES = [
        'keys.p256dh' => ['required_with:keys', 'string', 'regex:/\AB[A-Za-z0-9_-]{86}\z/'],
        'keys.auth' => ['required_with:keys', 'string', 'regex:/\A[A-Za-z0-9_-]{22}\z/'],
    ];

    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Notifications', [
            'enabled' => $request->user()->web_push_enabled,
            'vapidPublicKey' => $this->vapid()['publicKey'] ?? null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate(['enabled' => ['required', 'boolean']]);

        $user = $request->user();
        $user->web_push_enabled = $request->boolean('enabled');
        $user->save();

        return to_route('notifications.edit');
    }

    public function storeSubscription(Request $request): JsonResponse
    {
        $subscription = $request->validate([
            'endpoint' => [
                'required', 'string', 'max:4096', 'url:https',
                'regex:~\Ahttps://(?:fcm\.googleapis\.com|updates\.push\.services\.mozilla\.com|[a-z0-9.-]+\.(?:push\.apple\.com|notify\.windows\.com))(?:/|\?)[^\s#]+\z~i',
            ],
            'keys' => ['required', 'array:p256dh,auth'],
            ...self::SUBSCRIPTION_KEY_RULES,
        ]);

        $user = $request->user();
        $details = [
            'user_id' => $user->id,
            'endpoint' => $subscription['endpoint'],
            'public_key' => $subscription['keys']['p256dh'],
            'auth_token' => $subscription['keys']['auth'],
        ];
        $device = WebPushSubscription::query()->firstOrCreate([
            'endpoint_hash' => hash('sha256', $details['endpoint']),
        ], $details);

        if ($device->user_id !== $user->id) {
            abort_unless(hash_equals($device->public_key, $details['public_key'])
                && hash_equals($device->auth_token, $details['auth_token']), 409);
        }

        $device->update($details);

        return response()->json(['id' => $device->id]);
    }

    public function currentSubscription(Request $request): JsonResponse
    {
        $details = $request->validate([
            'endpoint' => ['required', 'string', 'max:4096'],
            'keys' => ['sometimes', 'required', 'array:p256dh,auth'],
            ...self::SUBSCRIPTION_KEY_RULES,
        ]);
        $subscriptions = $request->user()->webPushSubscriptions()
            ->where('endpoint_hash', hash('sha256', $details['endpoint']));
        $device = $subscriptions->first();
        $keys = $details['keys'] ?? null;

        if ($device !== null && $keys !== null
            && ($device->public_key !== $keys['p256dh'] || $device->auth_token !== $keys['auth'])) {
            $device->fill(['public_key' => $keys['p256dh'], 'auth_token' => $keys['auth']]);
            $subscriptions->whereKey($device->id)->update($device->getDirty());
        }

        return response()->json(['id' => $device?->id]);
    }

    public function destroySubscription(Request $request, int $subscription): HttpResponse
    {
        $request->user()->webPushSubscriptions()->whereKey($subscription)->delete();

        return response()->noContent();
    }

    public function sendTest(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->web_push_enabled, 403);

        $vapid = $this->vapid();
        abort_if($vapid === null, 503);

        $devices = $user->webPushSubscriptions()->get();
        $sent = 0;
        $failed = 0;
        $expired = [];

        if ($devices->isEmpty()) {
            return response()->json(compact('sent', 'failed', 'expired'));
        }

        $client = new Client([
            'handler' => Http::withOptions([])->buildHandlerStack(),
            'allow_redirects' => false,
            'connect_timeout' => 3,
            'timeout' => 8,
        ]);

        try {
            $sender = new WebPush(['VAPID' => $vapid], ['TTL' => 60], client: $client);
            $payload = json_encode([
                'title' => 'Test push notification',
                'body' => 'This notification came from your app server.',
            ], JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            Log::warning('Web Push test failed.', ['exception_type' => $exception::class]);

            abort(502);
        }

        foreach ($devices as $device) {
            try {
                $result = $sender->sendOneNotification(Subscription::create([
                    'endpoint' => $device->endpoint,
                    'keys' => ['p256dh' => $device->public_key, 'auth' => $device->auth_token],
                    'contentEncoding' => 'aes128gcm',
                ]), $payload);
            } catch (Throwable $exception) {
                Log::warning('Web Push test failed.', ['exception_type' => $exception::class]);
                $failed++;

                continue;
            }

            if ($result->isSuccess()) {
                $sent++;

                continue;
            }

            $failed++;

            if ($result->isSubscriptionExpired()) {
                $expired[] = $device->id;
                $user->webPushSubscriptions()->whereKey($device->id)
                    ->where('endpoint', $device->getRawOriginal('endpoint'))->delete();
            }
        }

        return response()->json(compact('sent', 'failed', 'expired'));
    }

    /** @return array{subject: string, publicKey: string, privateKey: string}|null */
    private function vapid(): ?array
    {
        $subject = config('services.web_push.vapid.subject');
        $publicKey = config('services.web_push.vapid.publicKey');
        $privateKey = config('services.web_push.vapid.privateKey');

        if (! is_string($subject) || $subject === ''
            || ! is_string($publicKey) || $publicKey === ''
            || ! is_string($privateKey) || $privateKey === '') {
            return null;
        }

        return compact('subject', 'publicKey', 'privateKey');
    }
}
