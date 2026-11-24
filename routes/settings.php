<?php

use App\Http\Controllers\Settings\NotificationController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('settings/notifications', [NotificationController::class, 'edit'])->name('notifications.edit');
    Route::patch('settings/notifications', [NotificationController::class, 'update'])->name('notifications.update');
    Route::post('settings/notifications/subscriptions', [NotificationController::class, 'storeSubscription'])
        ->middleware('throttle:10,1,web-push-subscription')
        ->name('notifications.subscription.store');
    Route::post('settings/notifications/subscriptions/current', [NotificationController::class, 'currentSubscription'])
        ->name('notifications.subscription.current');
    Route::delete('settings/notifications/subscriptions/{subscription}', [NotificationController::class, 'destroySubscription'])
        ->whereNumber('subscription')
        ->name('notifications.subscription.destroy');
    Route::post('settings/notifications/test', [NotificationController::class, 'sendTest'])
        ->middleware('throttle:5,1,web-push-test')
        ->name('notifications.test');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    abort_unless(Features::canManagePasskeys(), 404);

    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
