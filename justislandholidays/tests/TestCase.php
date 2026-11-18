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

    protected function superAdmin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    protected function islandAdmin(Island ...$islands): User
    {
        return User::factory()->islandAdmin(...$islands)->create();
    }
}
