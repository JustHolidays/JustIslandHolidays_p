<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_to_english_home(): void
    {
        $this->get('/')->assertRedirect('/en');
        $this->get('/en')->assertOk();
    }
}
