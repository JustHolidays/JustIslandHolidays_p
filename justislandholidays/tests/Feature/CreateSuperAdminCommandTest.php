<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateSuperAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_verified_super_admin_without_two_factor(): void
    {
        $this->artisan('admin:create-super-admin', ['email' => 'Boss@Example.com', 'name' => 'Boss'])
            ->assertSuccessful();

        $user = User::where('email', 'boss@example.com')->firstOrFail();
        $this->assertTrue($user->isSuperAdmin());
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse($user->hasConfirmedTwoFactor(), 'Two-factor is set up at first sign-in.');
        $this->assertSame(1, AuditLog::where('action', 'staff.create')->count());
    }

    public function test_refuses_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'boss@example.com']);

        $this->artisan('admin:create-super-admin', ['email' => 'boss@example.com', 'name' => 'Boss'])
            ->assertFailed();
    }
}
