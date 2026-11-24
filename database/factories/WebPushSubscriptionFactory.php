<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WebPushSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Minishlink\WebPush\VAPID;

/**
 * @extends Factory<WebPushSubscription>
 */
class WebPushSubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/'.fake()->uuid();

        return [
            'user_id' => User::factory(),
            'endpoint_hash' => hash('sha256', $endpoint),
            'endpoint' => $endpoint,
            'public_key' => VAPID::createVapidKeys()['publicKey'],
            'auth_token' => 'YWFhYWFhYWFhYWFhYWFhYQ',
        ];
    }
}
