<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\ProfileValidationRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AccountPasswordLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class UserController extends Controller
{
    use ProfileValidationRules;

    public function index(): Response
    {
        return Inertia::render('admin/Users', [
            'users' => User::query()->dataTable(
                tableKey: 'users',
                columns: ['id', 'name', 'email', 'email_verified_at'],
                defaultPerPage: 15,
                defaultSortBy: 'id',
                defaultDescending: true,
                allowedSorts: ['id', 'name', 'email'],
            )->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => $user->hasVerifiedEmail(),
                'is_admin' => $user->isAdmin(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = User::create([
            ...$request->validate($this->profileRules()),
            'password' => Str::random(64),
        ]);

        $sent = $this->sendPasswordLink($user, invitation: true);

        Inertia::flash('toast', [
            'type' => $sent ? 'success' : 'error',
            'message' => $sent
                ? 'User created and invitation sent.'
                : 'User created. The invitation could not be sent.',
        ]);

        return back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user): void {
            $account = User::query()->lockForUpdate()->findOrFail($user->id);
            $validated = $request->validate($this->profileUpdateRules($account, $request->input('email')));

            if ($validated['email'] !== $account->email) {
                Password::broker(config()->string('fortify.passwords'))->deleteToken($account);
                $account->email_verified_at = null;
            }

            $account->update($validated);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User updated.']);

        return back();
    }

    public function sendPasswordReset(User $user): RedirectResponse
    {
        $sent = $this->sendPasswordLink($user, invitation: false);

        Inertia::flash('toast', [
            'type' => $sent ? 'success' : 'error',
            'message' => $sent
                ? 'Password reset email sent.'
                : 'The password reset email could not be sent. Please try again later.',
        ]);

        return back();
    }

    public function destroy(User $user): RedirectResponse
    {
        DB::transaction(function () use ($user): void {
            $account = User::query()->lockForUpdate()->findOrFail($user->id);

            abort_if($account->isAdmin(), 403);

            Password::broker(config()->string('fortify.passwords'))->deleteToken($account);
            $account->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User deleted.']);

        return to_route('admin.users.index');
    }

    private function sendPasswordLink(User $user, bool $invitation): bool
    {
        $broker = Password::broker(config()->string('fortify.passwords'));

        try {
            $status = $broker->sendResetLink(
                ['email' => $user->email],
                function (User $account, string $token) use ($invitation): void {
                    $account->notify(new AccountPasswordLink($token, $invitation));
                },
            );
        } catch (TransportExceptionInterface $exception) {
            report($exception);
            $broker->deleteToken($user);

            return false;
        }

        return $status === Password::RESET_LINK_SENT;
    }
}
