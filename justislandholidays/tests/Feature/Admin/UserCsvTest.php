<?php

namespace Tests\Feature\Admin;

use App\Admin\UserCsv;
use App\Enums\Role;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Island;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

/** Owner and agent CSV import and export for super admins (21 Nov 2026). */
class UserCsvTest extends TestCase
{
    use BuildsListings;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlans();
        $this->admin = $this->superAdmin();
    }

    private function csv(array $rows, ?array $columns = null): UploadedFile
    {
        $columns ??= array_keys(UserCsv::COLUMNS);
        $out = fopen('php://temp', 'r+');
        fputcsv($out, $columns, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, array_map(fn ($c) => $row[$c] ?? '', $columns), ',', '"', '');
        }
        rewind($out);

        return UploadedFile::fake()->createWithContent('people.csv', stream_get_contents($out));
    }

    /** Uploads, then returns the preview page and its token. */
    private function upload(UploadedFile $file): array
    {
        $response = $this->actingAs($this->admin)->post(route('admin.users.csv.upload'), ['file' => $file]);
        $response->assertRedirect();
        $token = basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));

        return [$this->actingAs($this->admin)->get(route('admin.users.csv.preview', $token))->assertOk(), $token];
    }

    public function test_only_super_admins_can_use_it(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users.csv'))->assertOk()->assertSee('Import and export owners and agents');
        $this->actingAs($this->admin)->get(route('admin.users.index'))->assertSee(route('admin.users.csv'), false);

        $islandAdmin = $this->islandAdmin(Island::factory()->create());
        foreach ([$islandAdmin, User::factory()->owner()->create(), User::factory()->agent()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.users.csv'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.users.csv.export'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.users.csv.template'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.users.csv.upload'), ['file' => $this->csv([])])->assertForbidden();
        }
        $this->actingAs($islandAdmin)->get(route('admin.users.index'))->assertDontSee(route('admin.users.csv'), false);
        auth()->logout();
        $this->get(route('admin.users.csv'))->assertRedirect();
    }

    public function test_export_lists_owners_and_agents_only_and_is_audited(): void
    {
        $agency = Agency::factory()->create(['name' => 'Sol Homes']);
        $owner = User::factory()->owner()->create(['name' => 'Olivia Owner', 'email' => 'olivia@example.com', 'phone' => '=SUM(A1)']);
        $agent = User::factory()->agent()->create(['name' => 'Arturo Agent', 'email' => 'arturo@example.com']);
        $agent->forceFill(['agency_id' => $agency->id])->save();
        User::factory()->affiliate()->create(['email' => 'partner@example.com']);
        User::factory()->create(['email' => 'visitor@example.com', 'role' => Role::Visitor]);

        $content = $this->actingAs($this->admin)->get(route('admin.users.csv.export'))->assertOk()->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF".implode(',', UserCsv::header()), $content);
        $this->assertStringContainsString('olivia@example.com,"Olivia Owner",owner', $content);
        $this->assertStringContainsString("'=SUM(A1)", $content, 'Formula-like text is written safely.');
        $this->assertStringContainsString('Sol Homes', $content);
        $this->assertStringNotContainsString('partner@example.com', $content);
        $this->assertStringNotContainsString('visitor@example.com', $content);
        $this->assertStringNotContainsString($this->admin->email, $content);
        $this->assertStringNotContainsString('password', strtolower($content));

        $agentsOnly = $this->actingAs($this->admin)->get(route('admin.users.csv.export', ['role' => 'agent']))->streamedContent();
        $this->assertStringContainsString('arturo@example.com', $agentsOnly);
        $this->assertStringNotContainsString('olivia@example.com', $agentsOnly);

        $this->assertTrue(AuditLog::where('action', 'users.csv_export')->exists());
    }

    public function test_template_has_the_import_columns(): void
    {
        $content = $this->actingAs($this->admin)->get(route('admin.users.csv.template'))->assertOk()->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF".implode(',', array_keys(UserCsv::COLUMNS))."\n", $content);
    }

    public function test_new_rows_add_owners_and_agents_without_emailing_anyone(): void
    {
        Mail::fake();
        Notification::fake();
        $agency = Agency::factory()->create(['name' => 'Sol Homes']);

        [$preview, $token] = $this->upload($this->csv([
            ['email' => 'New.Owner@Example.com', 'name' => 'Nina Owner', 'role' => 'owner', 'phone' => '+34 600 111 222'],
            ['email' => 'new.agent@example.com', 'name' => 'Nico Agent', 'role' => 'Agent', 'agency' => 'sol homes'],
        ]));
        $preview->assertSee('New owner')->assertSee('New agent')->assertSee('nothing has been saved yet');
        $this->assertDatabaseMissing('users', ['email' => 'new.owner@example.com']);

        $this->actingAs($this->admin)->post(route('admin.users.csv.apply', $token))
            ->assertRedirect(route('admin.users.csv'))->assertSessionHas('status', fn ($s) => str_contains($s, '2 accounts added'));

        $owner = User::where('email', 'new.owner@example.com')->firstOrFail();
        $this->assertSame(Role::Owner, $owner->role);
        $this->assertSame('+34 600 111 222', $owner->phone);
        $this->assertNull($owner->agency_id);
        $this->assertNull($owner->email_verified_at, 'The email is confirmed by the invitation, later.');
        $agent = User::where('email', 'new.agent@example.com')->firstOrFail();
        $this->assertSame(Role::Agent, $agent->role);
        $this->assertSame($agency->id, $agent->agency_id);
        $this->assertSame('free', app(\App\Billing\AccountPlan::class)->planFor(app(\App\Billing\AccountPlan::class)->accountFor($owner))->code);

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
        Notification::assertNothingSent();
        $this->assertSame(2, AuditLog::where('action', 'user.csv_create')->count());
        $this->assertTrue(AuditLog::where('action', 'users.csv_import')->exists());
    }

    public function test_existing_rows_update_only_name_phone_and_agency(): void
    {
        $old = Agency::factory()->create(['name' => 'Old Agency']);
        $new = Agency::factory()->create(['name' => 'New Agency']);
        $owner = User::factory()->owner()->create(['name' => 'Olivia', 'email' => 'olivia@example.com', 'phone' => '111']);
        $agent = User::factory()->agent()->create(['name' => 'Arturo', 'email' => 'arturo@example.com']);
        $agent->forceFill(['agency_id' => $old->id])->save();
        $same = User::factory()->owner()->create(['name' => 'Sam', 'email' => 'sam@example.com', 'phone' => null]);

        [$preview, $token] = $this->upload($this->csv([
            ['email' => 'OLIVIA@example.com', 'name' => 'Olivia Smith', 'role' => 'agent', 'phone' => '222', 'agency' => 'New Agency'],
            ['email' => 'arturo@example.com', 'name' => 'Arturo', 'role' => 'agent', 'agency' => 'New Agency'],
            ['email' => 'sam@example.com', 'name' => 'Sam', 'role' => 'owner'],
        ]));
        $preview->assertSee('The role stays owner')->assertSee('owners do not belong to an agency');
        $this->assertMatchesRegularExpression('#data-count="update">\s*2\s*<#', $preview->getContent());
        $this->assertMatchesRegularExpression('#data-count="unchanged">\s*1\s*<#', $preview->getContent());

        $this->actingAs($this->admin)->post(route('admin.users.csv.apply', $token))->assertRedirect();

        $owner->refresh();
        $this->assertSame('Olivia Smith', $owner->name);
        $this->assertSame('222', $owner->phone);
        $this->assertSame(Role::Owner, $owner->role, 'The role never changes through the file.');
        $this->assertNull($owner->agency_id);
        $this->assertSame($new->id, $agent->fresh()->agency_id);
        $this->assertSame('Sam', $same->fresh()->name);
        $this->assertSame(2, AuditLog::where('action', 'user.csv_update')->count());
    }

    public function test_problem_rows_are_skipped_with_reasons(): void
    {
        User::factory()->affiliate()->create(['email' => 'partner@example.com']);
        $closed = User::factory()->owner()->create(['email' => 'gone@example.com']);
        $closed->delete();

        [$preview, $token] = $this->upload($this->csv([
            ['email' => 'not-an-email', 'name' => 'X', 'role' => 'owner'],
            ['email' => 'partner@example.com', 'name' => 'Partner', 'role' => 'owner'],
            ['email' => $this->admin->email, 'name' => 'Boss', 'role' => 'owner'],
            ['email' => 'gone@example.com', 'name' => 'Gone', 'role' => 'owner'],
            ['email' => 'nobody@example.com', 'name' => '', 'role' => 'owner'],
            ['email' => 'admin.wannabe@example.com', 'name' => 'Wannabe', 'role' => 'super_admin'],
            ['email' => 'agent2@example.com', 'name' => 'Agent Two', 'role' => 'agent', 'agency' => 'No Such Agency'],
            ['email' => 'twice@example.com', 'name' => 'Twice', 'role' => 'owner'],
            ['email' => 'TWICE@example.com', 'name' => 'Twice again', 'role' => 'owner'],
        ]));

        $preview->assertSee('is not a valid address')
            ->assertSee('another kind of account (Affiliate)', false)
            ->assertSee('belongs to a closed account')
            ->assertSee('The name is missing')
            ->assertSee('must be owner or agent')
            ->assertSee('There is no agency called')
            ->assertSee('already on line');

        $this->actingAs($this->admin)->post(route('admin.users.csv.apply', $token))->assertRedirect();

        $this->assertSame(1, User::where('email', 'twice@example.com')->count());
        $this->assertSame('Twice', User::where('email', 'twice@example.com')->value('name'));
        $this->assertDatabaseMissing('users', ['email' => 'admin.wannabe@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'agent2@example.com']);
        $this->assertSame(Role::Affiliate, User::where('email', 'partner@example.com')->first()->role);
        $this->assertSame(Role::SuperAdmin, $this->admin->fresh()->role);
        $this->assertNotSame('Boss', $this->admin->fresh()->name);
    }

    public function test_a_preview_is_saved_once_and_only_by_the_admin_who_uploaded_it(): void
    {
        [, $token] = $this->upload($this->csv([['email' => 'once@example.com', 'name' => 'Once', 'role' => 'owner']]));

        $other = $this->superAdmin();
        $this->actingAs($other)->post(route('admin.users.csv.apply', $token))->assertRedirect(route('admin.users.csv'))->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('users', ['email' => 'once@example.com']);

        $this->actingAs($this->admin)->post(route('admin.users.csv.apply', $token))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.users.csv.apply', $token))->assertSessionHasErrors('file');
        $this->assertSame(1, User::where('email', 'once@example.com')->count());
    }

    public function test_bad_files_are_refused(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.csv.upload'), ['file' => $this->csv([['email' => 'a@example.com']], ['email', 'phone'])])
            ->assertSessionHasErrors(['file' => 'The first row must be the column names from the template (missing: name).']);
        $this->actingAs($this->admin)->post(route('admin.users.csv.upload'), ['file' => $this->csv([])])
            ->assertSessionHasErrors('file');

        // Semicolons (European Excel) and export-only columns are read; the extras are ignored.
        $semi = UploadedFile::fake()->createWithContent('semi.csv', "email;name;role;status;plan\nsemi@example.com;Semi Colon;owner;active;Free Plan\n");
        [$preview] = $this->upload($semi);
        $preview->assertSee('New owner')->assertDontSee('These columns are not used');
    }
}
