<?php

namespace Tests;

use App\Models\Island;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Signs in as the user. An admin also counts as having done the second sign-in step
     * (emailed code or app), so tests reach the page under test. Use signInWithPasswordOnly()
     * to test the second step itself.
     */
    public function actingAs(\Illuminate\Contracts\Auth\Authenticatable $user, $guard = null)
    {
        if ($user instanceof User && $user->isAdmin()) {
            $this->withSession([\App\Auth\SecondFactor::SESSION_KEY => $user->id]);
        }

        return parent::actingAs($user, $guard);
    }

    /** Signed in, but without the admin's second step. */
    protected function signInWithPasswordOnly(User $user): static
    {
        return parent::actingAs($user);
    }

    protected function superAdmin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    protected function islandAdmin(Island ...$islands): User
    {
        return User::factory()->islandAdmin(...$islands)->create();
    }
}
