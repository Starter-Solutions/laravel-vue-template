<?php

use App\Models\User;
use App\Notifications\AccountPasswordLink;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

beforeEach(function () {
    config(['admin.emails' => ['admin@example.test']]);

    $this->admin = User::factory()->create(['email' => 'Admin@example.test']);
});

test('only configured admins can see the users navigation and access the table', function () {
    $user = User::factory()->create();

    $this->get(route('admin.users.index'))->assertRedirect(route('login'));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canManageUsers', false));

    $this->get(route('admin.users.index'))->assertForbidden();

    $this->actingAs($this->admin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Users')
            ->where('auth.canManageUsers', true)
            ->where('users.total', 2)
            ->where('users.data.0.id', $user->id)
            ->where('users.data.0.is_admin', false)
            ->where('users.data.1.id', $this->admin->id)
            ->where('users.data.1.is_admin', true)
            ->where('users.data.1.email_verified', true)
            ->missing('users.data.1.password'));
});

test('nonadmins cannot create, edit, reset or delete accounts through the admin routes', function () {
    Notification::fake();

    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('admin.users.store'), ['name' => 'New user', 'email' => 'new@example.test'])->assertForbidden();
    $this->put(route('admin.users.update', $user), ['name' => 'New name', 'email' => $user->email])->assertForbidden();
    $this->post(route('admin.users.password-reset', $user))->assertForbidden();
    $this->delete(route('admin.users.destroy', $user))->assertForbidden();

    Notification::assertNothingSent();
});

test('the users table supports sorting and pagination', function () {
    User::factory()->create(['name' => 'Alpha']);
    $second = User::factory()->unverified()->create(['name' => 'Beta']);
    $this->admin->update(['name' => 'Gamma']);

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', [
            'tableKey' => 'users',
            'sort_by' => 'name',
            'descending' => 0,
            'per_page' => 1,
            'page' => 2,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.current_page', 2)
            ->where('users.last_page', 3)
            ->where('users.sort_by', 'name')
            ->has('users.data', 1)
            ->where('users.data.0.id', $second->id)
            ->where('users.data.0.email_verified', false));
});

test('admins can edit and delete regular accounts but admin accounts cannot be deleted', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)
        ->from(route('admin.users.index'))
        ->put(route('admin.users.update', $user), ['name' => 'Updated name', 'email' => 'updated@example.test'])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasNoErrors();

    expect($user->refresh())
        ->name->toBe('Updated name')
        ->email->toBe('updated@example.test')
        ->email_verified_at->toBeNull();

    $this->delete(route('admin.users.destroy', $this->admin))->assertForbidden();
    $this->delete(route('profile.destroy'), ['password' => 'password'])->assertForbidden();
    $this->assertModelExists($this->admin);

    $this->delete(route('admin.users.destroy', $user))->assertRedirect(route('admin.users.index'));
    $this->assertModelMissing($user);
});

test('configured admin addresses are reserved during public registration and profile changes', function () {
    config(['admin.emails' => ['admin@example.test', 'future-admin@example.test']]);

    $this->post(route('register'), [
        'name' => 'New user',
        'email' => 'Future-Admin@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('email');

    $user = User::factory()->create();
    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'future-admin@example.test'])
        ->assertSessionHasErrors('email');

    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $this->admin), ['name' => $this->admin->name, 'email' => 'other@example.test'])
        ->assertSessionHasErrors('email');
});

test('an invitation lets a new user verify their email and set a password once', function () {
    Notification::fake();

    $this->actingAs($this->admin)
        ->from(route('admin.users.index'))
        ->post(route('admin.users.store'), ['name' => 'New user', 'email' => 'new+invited@example.test'])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasNoErrors();

    $user = User::where('email', 'new+invited@example.test')->firstOrFail();
    expect($user->hasVerifiedEmail())->toBeFalse();

    Notification::assertSentTo($user, AccountPasswordLink::class);
    $notification = Notification::sent($user, AccountPasswordLink::class)->sole();
    $mail = $notification->toMail($user);

    expect($mail->actionText)->toBe('Set your password');
    $url = $mail->actionUrl;

    Auth::logout();
    $this->get(route('account-password.edit', $user))->assertForbidden();

    $this->get($url)->assertInertia(fn (Assert $page) => $page
        ->component('auth/ResetPassword')
        ->where('email', $user->email)
        ->where('submitUrl', $url));

    $this->post($url, ['password' => 'new-password', 'password_confirmation' => 'new-password'])
        ->assertRedirect(route('login'))
        ->assertSessionHasNoErrors();

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('new-password', $user->password))->toBeTrue();

    $this->get($url)->assertForbidden();
});

test('the admin password reset works with public resets disabled and expires', function () {
    Notification::fake();
    config(['fortify.features' => array_values(array_diff(config('fortify.features'), [Features::resetPasswords()]))]);

    $user = User::factory()->create();
    $originalPassword = $user->password;

    $this->actingAs($this->admin)
        ->from(route('admin.users.index'))
        ->post(route('admin.users.password-reset', $user))
        ->assertRedirect(route('admin.users.index'));

    expect($user->refresh()->password)->toBe($originalPassword);

    Notification::assertSentTo($user, AccountPasswordLink::class);
    $mail = Notification::sent($user, AccountPasswordLink::class)->sole()->toMail($user);
    expect($mail->actionText)->toBe('Reset password');

    Auth::logout();
    $this->get($mail->actionUrl)->assertOk();

    $this->travel(61)->minutes();
    $this->post($mail->actionUrl, ['password' => 'new-password', 'password_confirmation' => 'new-password'])
        ->assertForbidden();

    expect($user->refresh()->password)->toBe($originalPassword);
});
