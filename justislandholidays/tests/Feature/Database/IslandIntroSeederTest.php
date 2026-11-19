<?php

namespace Tests\Feature\Database;

use App\Models\Island;
use Database\Seeders\IslandIntroSeeder;
use Database\Seeders\IslandSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IslandIntroSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_island_gets_an_english_introduction(): void
    {
        $this->seed([IslandSeeder::class, IslandIntroSeeder::class]);

        foreach (['mallorca', 'menorca', 'ibiza', 'formentera'] as $slug) {
            $intro = Island::where('slug', $slug)->sole()->intro['en'] ?? '';
            $this->assertGreaterThan(150, str_word_count($intro), "{$slug} has an introduction");
        }

        $this->get('/en/menorca')->assertOk()
            ->assertSee('Menorca is the quiet, green island of the Balearics.')
            ->assertSee('<h2>Getting there</h2>', false)
            // Full width of the content box, not the narrower text column.
            ->assertSee('class="prose  max-w-none" data-intro', false);
    }

    public function test_it_never_overwrites_an_edited_introduction(): void
    {
        $this->seed(IslandSeeder::class);
        Island::where('slug', 'mallorca')->sole()->update(['intro' => ['en' => 'Our own words.', 'es' => 'Nuestras palabras.']]);

        $this->seed(IslandIntroSeeder::class);
        $this->seed(IslandIntroSeeder::class);

        $this->assertSame(['en' => 'Our own words.', 'es' => 'Nuestras palabras.'], Island::where('slug', 'mallorca')->sole()->intro);
        $this->assertStringStartsWith('Menorca is', Island::where('slug', 'menorca')->sole()->intro['en']);
    }
}
