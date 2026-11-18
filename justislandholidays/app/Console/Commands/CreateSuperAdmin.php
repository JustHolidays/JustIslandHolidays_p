<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Creates a super admin from the command line. Needed for the very first account,
 * because only a super admin can create admin accounts in /admin/staff.
 * The password is random and shown once; two-factor set-up is forced at first sign-in.
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create-super-admin {email} {name}';

    protected $description = 'Create a super admin account with a one-time random password';

    public function handle(): int
    {
        $data = ['email' => strtolower($this->argument('email')), 'name' => $this->argument('name')];

        $validator = Validator::make($data, [
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'name' => ['required', 'string', 'max:160'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $password = Str::password(20);

        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $password]);
        $user->role = Role::SuperAdmin;
        $user->email_verified_at = now();
        $user->save();

        AuditLog::create([
            'actor_id' => null,
            'action' => 'staff.create',
            'entity' => 'user',
            'entity_id' => (string) $user->id,
            'detail' => ['role' => Role::SuperAdmin->value, 'via' => 'console'],
        ]);

        $this->info("Super admin created: {$user->email}");
        $this->line("Temporary password (shown once): {$password}");
        $this->line('Sign in, set up two-factor sign-in, then change the password.');

        return self::SUCCESS;
    }
}
