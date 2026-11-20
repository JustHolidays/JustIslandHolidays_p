<?php

namespace Tests\Feature\Admin;

use App\Models\SiteSetting;
use App\Support\PayPalSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** PayPal Settings: Just Island Holidays' PayPal details, super admins only (20 Nov 2026). */
class PayPalSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paypal' => [
            'mode' => 'sandbox', 'client_id' => 'ENV-ID', 'client_secret' => 'env-secret-9999',
            'webhook_id' => 'WH-ENV', 'brand_name' => 'Just Island Holidays',
        ]]);
    }

    private function paypalAccepts(): void
    {
        Http::fake(['*/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600])]);
    }

    private function save(array $data): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->superAdmin())->from(route('admin.paypal-settings.edit'))
            ->put(route('admin.paypal-settings.update'), $data + ['mode' => 'sandbox']);
    }

    public function test_super_admin_opens_the_page_from_the_settings_menu(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get('/admin')->assertSee('>PayPal Settings</a>', false);
        $this->actingAs($admin)->get(route('admin.paypal-settings.edit'))->assertOk()
            ->assertSee('PayPal')->assertSee('ENV-ID')->assertSee('ending …9999')
            ->assertSee('from the server')->assertSee(route('webhooks.paypal'))
            ->assertDontSee('env-secret-9999');
    }

    public function test_island_admins_and_owners_cannot_open_it(): void
    {
        $islandAdmin = $this->islandAdmin(\App\Models\Island::factory()->create());

        $this->actingAs($islandAdmin)->get(route('admin.paypal-settings.edit'))->assertForbidden();
        $this->actingAs($islandAdmin)->put(route('admin.paypal-settings.update'), ['mode' => 'live'])->assertForbidden();
        $this->assertStringNotContainsString('PayPal Settings', $this->actingAs($islandAdmin)->get('/admin')->getContent());
        $this->actingAs(\App\Models\User::factory()->owner()->create())->get(route('admin.paypal-settings.edit'))->assertForbidden();
    }

    public function test_details_are_checked_with_paypal_then_saved_with_the_secret_encrypted(): void
    {
        $this->paypalAccepts();

        $this->save(['client_id' => 'NEW-ID', 'client_secret' => 'new-secret-1234', 'webhook_id' => 'WH-NEW-1', 'brand_name' => 'JIH'])
            ->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, 'checked with PayPal'));

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://api-m.sandbox.paypal.com/v1/oauth2/token')
            && $r->hasHeader('Authorization', 'Basic '.base64_encode('NEW-ID:new-secret-1234')));

        $row = SiteSetting::find('paypal');
        $this->assertSame('NEW-ID', $row->value['client_id']);
        $this->assertNotSame('new-secret-1234', $row->value['client_secret'], 'Stored encrypted.');
        $this->assertSame('new-secret-1234', Crypt::decryptString($row->value['client_secret']));
        $this->assertStringNotContainsString('new-secret-1234', json_encode(\App\Models\AuditLog::where('action', 'settings.paypal')->sole()->detail));

        // In use straight away, and on the next request too.
        $this->assertSame('NEW-ID', config('services.paypal.client_id'));
        config(['services.paypal.client_id' => 'ENV-ID', 'services.paypal.client_secret' => 'env-secret-9999']);
        PayPalSettings::apply();
        $this->assertSame('new-secret-1234', config('services.paypal.client_secret'));
        $this->assertSame('WH-NEW-1', config('services.paypal.webhook_id'));
        $this->assertSame('JIH', config('services.paypal.brand_name'));
    }

    public function test_nothing_is_saved_when_paypal_refuses(): void
    {
        Http::fake(['*/v1/oauth2/token' => Http::response(['error' => 'invalid_client'], 401)]);

        $this->save(['client_id' => 'WRONG', 'client_secret' => 'nope'])->assertSessionHasErrors('client_id');

        $this->assertNull(SiteSetting::find('paypal'));
        $this->assertSame('ENV-ID', config('services.paypal.client_id'));
    }

    public function test_an_empty_secret_keeps_the_one_in_use(): void
    {
        $this->paypalAccepts();
        $this->save(['client_id' => 'NEW-ID', 'client_secret' => 'first-secret-1111'])->assertSessionHasNoErrors();

        $this->save(['client_id' => 'NEW-ID', 'client_secret' => '', 'webhook_id' => 'WH-2'])->assertSessionHasNoErrors();

        $this->assertSame('first-secret-1111', Crypt::decryptString(SiteSetting::find('paypal')->value['client_secret']));
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Basic '.base64_encode('NEW-ID:first-secret-1111')));
    }

    public function test_switching_to_live_needs_the_box_ticked_and_is_checked_against_live_paypal(): void
    {
        $this->paypalAccepts();

        $this->save(['mode' => 'live', 'client_id' => 'LIVE-ID', 'client_secret' => 'live-secret'])->assertSessionHasErrors('live_confirm');
        $this->assertNull(SiteSetting::find('paypal'));

        $this->save(['mode' => 'live', 'client_id' => 'LIVE-ID', 'client_secret' => 'live-secret', 'live_confirm' => '1'])->assertSessionHasNoErrors();
        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://api-m.paypal.com/v1/oauth2/token'));
        $this->assertSame('live', config('services.paypal.mode'));
        $this->assertSame('https://api-m.paypal.com', app(\App\Billing\PayPalClient::class)->baseUrl());
    }

    public function test_an_unreadable_secret_falls_back_to_env(): void
    {
        SiteSetting::create(['setting_key' => 'paypal', 'value' => ['mode' => 'sandbox', 'client_secret' => 'not-encrypted-with-this-key']]);
        Cache::flush();

        PayPalSettings::apply();

        $this->assertSame('env-secret-9999', config('services.paypal.client_secret'));
        $this->actingAs($this->superAdmin())->get(route('admin.paypal-settings.edit'))->assertSee('can no longer be read');
    }
}
