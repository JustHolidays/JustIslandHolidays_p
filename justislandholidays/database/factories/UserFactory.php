<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Island;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'role' => Role::Owner,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function deactivated(): static
    {
        return $this->state(fn () => ['deactivated_at' => now()]);
    }

    public function owner(): static
    {
        return $this->state(fn () => ['role' => Role::Owner]);
    }

    public function agent(): static
    {
        return $this->state(fn () => ['role' => Role::Agent]);
    }

    public function visitor(): static
    {
        return $this->state(fn () => ['role' => Role::Visitor]);
    }

    public function affiliate(): static
    {
        return $this->state(fn () => ['role' => Role::Affiliate]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => ['role' => Role::SuperAdmin])->withTwoFactor();
    }

    /** An island admin assigned to the given islands (assigned by a new super admin). */
    public function islandAdmin(Island ...$islands): static
    {
        return $this->state(fn () => ['role' => Role::IslandAdmin])
            ->withTwoFactor()
            ->afterCreating(function (User $user) use ($islands) {
                if ($islands === []) {
                    return;
                }
                $by = User::factory()->superAdmin()->create();
                foreach ($islands as $island) {
                    $user->islands()->attach($island->id, ['assigned_by' => $by->id, 'created_at' => now()]);
                }
            });
    }

    /** Two-factor set up and confirmed. */
    public function withTwoFactor(): static
    {
        return $this->state(fn () => [
            'two_factor_secret' => encrypt('TESTSECRET234567'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code-one', 'code-two'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function withoutTwoFactor(): static
    {
        return $this->state(fn () => [
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }
}
