<?php

namespace App\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountPasswordController extends Controller
{
    use PasswordValidationRules;

    public function edit(Request $request, User $user): Response
    {
        $token = $this->tokenFor($request, $user);

        abort_unless(Password::broker(config()->string('fortify.passwords'))->tokenExists($user, $token), 403);

        return Inertia::render('auth/ResetPassword', [
            'submitUrl' => $request->getSchemeAndHttpHost().$request->getRequestUri(),
            'token' => $token,
            'email' => $user->email,
            'passwordRules' => PasswordRules::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate(['password' => $this->passwordRules()]);

        $status = DB::transaction(function () use ($request, $user, $validated): string {
            $account = User::query()->lockForUpdate()->findOrFail($user->id);

            return Password::broker(config()->string('fortify.passwords'))->reset([
                'email' => $account->email,
                'token' => $this->tokenFor($request, $account),
                'password' => $validated['password'],
            ], function (User $account, string $password): void {
                $wasVerified = $account->hasVerifiedEmail();

                $account->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    'email_verified_at' => $account->email_verified_at ?? now(),
                ])->save();

                if (! $wasVerified) {
                    event(new Verified($account));
                }

                event(new PasswordReset($account));
            });
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return to_route('login')->with('status', __($status));
    }

    private function tokenFor(Request $request, User $user): string
    {
        $token = $request->query('token');

        abort_unless(is_string($token) && $request->query('email') === $user->email, 403);

        return $token;
    }
}
