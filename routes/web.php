<?php

use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::prefix('admin')->name('admin.')->middleware('can:manage-users')->group(function () {
        Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('users/{user}/password-reset', [UserController::class, 'sendPasswordReset'])
            ->name('users.password-reset');
    });
});

Route::middleware(['guest', 'signed', 'throttle:6,1'])->group(function () {
    Route::get('account/password/{user}', [AccountPasswordController::class, 'edit'])->name('account-password.edit');
    Route::post('account/password/{user}', [AccountPasswordController::class, 'update'])->name('account-password.update');
});

require __DIR__.'/settings.php';
