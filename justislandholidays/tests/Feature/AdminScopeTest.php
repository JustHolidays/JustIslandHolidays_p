<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use App\Models\Island;
use App\Models\Listing;
use App\Models\User;
use App\Support\AdminScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdminScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_island_admin_lists_only_their_islands(): void
    {
        $a = Island::factory()->create();
        $b = Island::factory()->create();
        $c = Island::factory()->create();
        $onA = Listing::factory()->onIsland($a)->create();
        $onB = Listing::factory()->onIsland($b)->create();
        Listing::factory()->onIsland($c)->create();

        $admin = $this->islandAdmin($a, $b);
        $ids = AdminScope::apply(Listing::query(), $admin)->pluck('id')->sort()->values()->all();

        $this->assertSame([$onA->id, $onB->id], $ids);
    }

    public function test_super_admin_lists_every_island(): void
    {
        Listing::factory()->count(3)->create();

        $this->assertSame(3, AdminScope::apply(Listing::query(), $this->superAdmin())->count());
    }

    public function test_scope_through_a_relation(): void
    {
        $a = Island::factory()->create();
        $b = Island::factory()->create();
        $mine = Enquiry::factory()->for(Listing::factory()->onIsland($a))->create();
        Enquiry::factory()->for(Listing::factory()->onIsland($b))->create();

        $ids = AdminScope::applyVia(Enquiry::query(), $this->islandAdmin($a), 'listing')->pluck('id')->all();

        $this->assertSame([$mine->id], $ids);
    }

    public function test_authorize_refuses_other_islands(): void
    {
        $a = Island::factory()->create();
        $b = Island::factory()->create();
        $admin = $this->islandAdmin($a);

        AdminScope::authorize($admin, $a->id);

        $this->expectException(HttpException::class);
        AdminScope::authorize($admin, $b->id);
    }

    public function test_records_with_no_island_are_refused_for_island_admins(): void
    {
        $this->expectException(HttpException::class);
        AdminScope::authorize($this->islandAdmin(Island::factory()->create()), null);
    }

    public function test_non_admins_are_refused(): void
    {
        $this->expectException(HttpException::class);
        AdminScope::apply(Listing::query(), User::factory()->owner()->create());
    }
}
