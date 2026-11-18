<?php

namespace App\Admin;

use App\Models\User;
use App\Notifications\Invitation;
use Illuminate\Support\Facades\Password;

final class Invitations
{
    public static function send(User $user): void
    {
        $token = Password::broker('invites')->createToken($user);
        $user->notify(new Invitation($token, $user->role->label()));
    }
}
