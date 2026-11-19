<?php

namespace Tests\Feature\Admin;

use App\Models\Agency;
use App\Models\Island;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Owners and Agents are separate menu items; Agents is a dropdown with a properties page per agent. */
class AgentMenuTest extends TestCase
{
    use RefreshDatabase;

    private Island $island;

    private Island $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->island = Island::factory()->create();
        $this->other = Island::factory()->create();
    }

    private function home(User $owner, string $title, ?Island $island = null): Listing
    {
        return Listing::factory()->live()->onIsland($island ?? $this->island)->titled($title)->create(['owner_id' => $owner->id]);
    }

    public function test_owners_page_lists_owners_only_and_agents_page_agents_only(): void
    {
        $owner = User::factory()->owner()->create(['name' => 'Olive Owner']);
        $agent = User::factory()->agent()->create(['name' => 'Arthur Agent']);
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()
            ->assertSee('<h1 class="text-2xl font-semibold">Owners</h1>', false)
            ->assertSee('Olive Owner')
            // In the table: owners link to their account page. Arthur is only in the menu.
            ->assertDontSee('href="'.route('admin.users.show', $agent).'"', false);

        $this->actingAs($admin)->get(route('admin.agents.index'))->assertOk()
            ->assertSee('<h1 class="text-2xl font-semibold">Agents</h1>', false)
            ->assertSee('Arthur Agent')->assertDontSee('Olive Owner')
            ->assertSee(route('admin.agents.show', $agent), false);
    }

    public function test_menu_has_owners_and_an_agents_dropdown(): void
    {
        $agency = Agency::factory()->create(['name' => 'Island Lets']);
        User::factory()->agent()->create(['name' => 'Arthur Agent', 'agency_id' => $agency->id]);
        User::factory()->agent()->create(['name' => 'Gone Agent', 'deactivated_at' => now()]);

        $html = $this->actingAs($this->superAdmin())->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('>Owners</a>', $html);
        $this->assertStringNotContainsString('Owners and agents', $html);
        $this->assertStringContainsString('>All agents</a>', $html);
        $this->assertStringContainsString('Arthur Agent', $html);
        $this->assertStringContainsString('Island Lets', $html);
        $this->assertStringNotContainsString('Gone Agent', $html);
    }

    public function test_changing_an_owner_to_agent_moves_them_into_the_agents_dropdown(): void
    {
        $person = User::factory()->owner()->create(['name' => 'Pat Person', 'email' => 'pat@example.com']);
        $admin = $this->superAdmin();

        $this->assertStringNotContainsString(route('admin.agents.show', $person), $this->actingAs($admin)->get('/admin')->getContent());

        $this->actingAs($admin)->put(route('admin.users.update', $person), ['name' => 'Pat Person', 'email' => 'pat@example.com', 'role' => 'agent'])
            ->assertSessionHasNoErrors();

        $html = $this->actingAs($admin)->get('/admin')->getContent();
        $this->assertStringContainsString(route('admin.agents.show', $person), $html);
        // Gone from the Owners table (the menu still names him under Agents).
        $this->actingAs($admin)->get(route('admin.users.index'))->assertDontSee('href="'.route('admin.users.show', $person).'"', false);
        $this->actingAs($admin)->get(route('admin.agents.index'))->assertSee('Pat Person');
    }

    public function test_agent_page_lists_their_properties(): void
    {
        $agent = User::factory()->agent()->create(['name' => 'Arthur Agent']);
        $live = $this->home($agent, 'Villa Mar');
        Listing::factory()->onIsland($this->island)->titled('Draft Flat')->create(['owner_id' => $agent->id]);
        $this->home(User::factory()->agent()->create(), 'Not Theirs');

        $html = $this->actingAs($this->superAdmin())->get(route('admin.agents.show', $agent))->assertOk()
            ->assertSee('Properties (2)')
            ->assertSee('Villa Mar')->assertSee('Draft Flat')->assertDontSee('Not Theirs')
            ->assertSee($live->publicUrl('en'), false)
            ->getContent();

        // The dropdown is open and marks this agent.
        $this->assertMatchesRegularExpression('/<details\s+open/', $html);
        $this->assertMatchesRegularExpression('/aria-current="page"\s*>\s*<span>Arthur Agent/', $html);
    }

    public function test_agent_page_is_only_for_agents(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($this->superAdmin())->get(route('admin.agents.show', $owner))->assertNotFound();
    }

    public function test_island_admin_sees_only_agents_and_properties_on_their_islands(): void
    {
        $mine = User::factory()->agent()->create(['name' => 'Mine Agent']);
        $this->home($mine, 'Here Villa');
        $this->home($mine, 'Away Villa', $this->other);
        $theirs = User::factory()->agent()->create(['name' => 'Elsewhere Agent']);
        $this->home($theirs, 'Far Villa', $this->other);

        $admin = $this->islandAdmin($this->island);

        $html = $this->actingAs($admin)->get('/admin')->getContent();
        $this->assertStringContainsString('Mine Agent', $html);
        $this->assertStringNotContainsString('Elsewhere Agent', $html);

        $this->actingAs($admin)->get(route('admin.agents.show', $mine))->assertOk()
            ->assertSee('Here Villa')->assertDontSee('Away Villa');

        // Another island's agent, opened by URL, is refused.
        $this->actingAs($admin)->get(route('admin.agents.show', $theirs))->assertForbidden();
    }

    public function test_an_agents_account_page_sits_under_agents_in_the_menu(): void
    {
        $agent = User::factory()->agent()->create();

        $html = $this->actingAs($this->superAdmin())->get(route('admin.users.show', $agent))->assertOk()
            ->assertSee('Back to agents')->assertSee('See their properties')->getContent();

        $this->assertMatchesRegularExpression('/<details\s+open/', $html);
        $this->assertDoesNotMatchRegularExpression('/aria-current="page"\s*>Owners</', $html);
    }
}
