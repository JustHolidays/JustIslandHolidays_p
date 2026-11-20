<?php

namespace App\Actions\Fortify;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Public sign-up: owners and agents only. Affiliates and admins are created by a super admin.
 * Honeypot, ALTCHA and rate limits are added with the public forms in build step 2.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'string', 'email', 'max:190', Rule::unique(User::class)],
            // Visitors save favourites (20 Nov 2026); owners and agents list homes.
            'account_type' => ['required', Rule::in([Role::Visitor->value, Role::Owner->value, Role::Agent->value])],
            'password' => $this->passwordRules(),
        ])->validate();

        $user = new User([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
        $user->role = Role::from($input['account_type']);
        $user->save();

        return $user;
    }
}
