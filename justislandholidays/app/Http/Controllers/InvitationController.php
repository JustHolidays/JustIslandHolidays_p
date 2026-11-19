<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/** Accepting an invitation: set a password, confirm the email, then sign in. */
class InvitationController extends Controller
{
    use PasswordValidationRules;

    public function show(Request $request, string $token): View
    {
        return view('auth.invitation', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => $this->passwordRules(),
        ]);

        $user = null;
        $status = Password::broker('invites')->reset($data, function (User $u, string $password) use (&$user) {
            $u->forceFill([
                'password' => $password,
                'email_verified_at' => $u->email_verified_at ?? now(),
                'remember_token' => null,
            ])->save();
            $user = $u;
        });

        if ($status !== Password::PASSWORD_RESET || ! $user || $user->isDeactivated()) {
            return back()->withErrors(['email' => 'This invitation link has expired or is not valid. Ask for a new one.']);
        }

        Audit::record($user, 'invitation.accept', $user);
        Auth::login($user);
        $request->session()->regenerate();

        // Admins then get an emailed sign-in code (RequireSecondFactor) before the admin area.
        return $user->isAdmin()
            ? redirect()->route('second-factor.show')
            : redirect()->route('account.home')->with('status', 'Password set. Welcome to Just Island Holidays.');
    }
}
