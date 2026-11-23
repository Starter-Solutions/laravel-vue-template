<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

test('login omits links and passkey endpoints for disabled features', function () {
    config(['fortify.features' => []]);

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Login')
            ->where('auth.registrationUrl', null)
            ->where('resetPasswordUrl', null)
            ->where('passkeyRoutes', null));
});

test('login exposes links and passkey endpoints for enabled features', function () {
    $this->skipUnlessFortifyHas(Features::registration());
    $this->skipUnlessFortifyHas(Features::resetPasswords());
    $this->skipUnlessFortifyHas(Features::passkeys());

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.registrationUrl', '/register')
            ->where('resetPasswordUrl', '/forgot-password')
            ->where('passkeyRoutes.options', '/passkeys/login/options')
            ->where('passkeyRoutes.submit', '/passkeys/login'));
});

test('an unverified user can open the dashboard when email verification is disabled', function () {
    config(['fortify.features' => []]);
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

test('profile omits the verification link when email verification is disabled', function () {
    config(['fortify.features' => []]);
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('verificationUrl', null));
});

test('users with existing two factor credentials can sign in when the feature is disabled', function () {
    config(['fortify.features' => []]);
    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('password confirmation omits the passkey endpoints when passkeys are disabled', function () {
    config(['fortify.features' => []]);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('password.confirm'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/ConfirmPassword')
            ->where('passkeyRoutes', null));
});

test('registration sends no verification email when email verification is disabled', function () {
    $this->skipUnlessFortifyHas(Features::registration());
    config(['fortify.features' => [Features::registration()]]);
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'email_verified_at' => null,
    ]);
    Notification::assertNothingSent();
});

test('passkey discovery returns not found when passkeys are disabled', function () {
    config(['fortify.features' => []]);

    $this->get('/.well-known/passkey-endpoints')->assertNotFound();
});
