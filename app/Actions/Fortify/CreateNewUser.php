<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $profileRules = $this->profileRules();

        if (is_string($input['email'] ?? null) && User::isAdminEmail($input['email'])) {
            $profileRules['email'][] = 'prohibited';
        }

        Validator::make($input, [
            ...$profileRules,
            'password' => $this->passwordRules(),
        ], [
            'email.prohibited' => __('This email address requires an invitation from an administrator.'),
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }
}
