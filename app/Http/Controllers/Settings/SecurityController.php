<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        $canManagePasskeys = Features::canManagePasskeys();

        $props = [
            'twoFactorRoutes' => null,
            'passkeyRoutes' => $canManagePasskeys ? [
                'options' => route('passkey.registration-options', absolute: false),
                'submit' => route('passkey.store', absolute: false),
            ] : null,
            'passkeys' => $canManagePasskeys
                ? $request->user()
                    ->passkeys()
                    ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
                    ->latest()
                    ->get()
                    ->map(fn ($passkey) => [
                        'id' => $passkey->id,
                        'name' => $passkey->name,
                        'deleteUrl' => route('passkey.destroy', ['passkey' => $passkey], absolute: false),
                        'authenticator' => $passkey->authenticator,
                        'created_at_diff' => $passkey->created_at->diffForHumans(),
                        'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
                    ])
                    ->values()
                    ->all()
                : [],
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();

            $props['twoFactorRoutes'] = [
                'enable' => route('two-factor.enable', absolute: false),
                'disable' => route('two-factor.disable', absolute: false),
                'confirm' => route('two-factor.confirm', absolute: false),
                'qrCode' => route('two-factor.qr-code', absolute: false),
                'secretKey' => route('two-factor.secret-key', absolute: false),
                'recoveryCodes' => route('two-factor.recovery-codes', absolute: false),
                'regenerateRecoveryCodes' => route('two-factor.regenerate-recovery-codes', absolute: false),
            ];
            $props['twoFactorEnabled'] = $request->user()->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        return Inertia::render('settings/Security', $props);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
