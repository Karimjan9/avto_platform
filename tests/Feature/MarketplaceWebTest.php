<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MarketplaceFixtures;
use Tests\TestCase;

class MarketplaceWebTest extends TestCase
{
    use MarketplaceFixtures, RefreshDatabase;

    public function test_public_catalog_renders_only_published_listings_and_accepts_empty_native_filters(): void
    {
        $published = Listing::factory()->published()->create(['title' => 'Ochiq avtomobil']);
        $draft = Listing::factory()->create(['title' => 'Maxfiy qoralama']);
        $this->get('/elonlar?section=cars&q=&price_min=&price_max=')->assertOk()->assertSee($published->title)->assertDontSee($draft->title);
        $this->get('/elonlar/'.$draft->id)->assertNotFound();
        $this->get('/elonlar/'.$published->id)->assertOk()->assertSee($published->description);
    }

    public function test_listing_cards_fetch_one_cover_and_query_count_does_not_grow_per_card(): void
    {
        $first = Listing::factory()->published()->create();
        $first->images()->create(['path' => 'late.webp', 'sort_order' => 5]);
        $first->images()->create(['path' => 'cover.webp', 'sort_order' => 1]);
        $first->images()->create(['path' => 'same-order.webp', 'sort_order' => 1]);
        $this->assertSame('cover.webp', $first->coverImage->path);
        DB::enableQueryLog();
        $this->get('/')->assertOk();
        $one = count(DB::getQueryLog());
        Listing::factory()->published()->count(17)->create();
        DB::flushQueryLog();
        $this->get('/')->assertOk()->assertSee('cover.webp')->assertDontSee('late.webp');
        $many = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual($one + 1, $many, 'Card rendering introduced per-listing database queries.');
        $this->assertLessThanOrEqual(8, $many);
    }

    public function test_web_login_uses_session_and_does_not_create_api_tokens(): void
    {
        $user = User::factory()->create(['email' => 'buyer@example.test']);
        $this->get('/kabinet')->assertRedirect('/kirish');
        $this->post('/kirish', ['email' => ' BUYER@example.test ', 'password' => 'password'])->assertRedirect('/kabinet');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $response = $this->get('/kabinet')->assertOk()->assertSee($user->name);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->post('/chiqish')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_registration_cannot_grant_admin_access_and_cabinet_is_isolated(): void
    {
        $this->post('/royxatdan-otish', ['name' => 'Yangi xaridor', 'email' => 'new@example.test', 'password' => 'SafePass123', 'password_confirmation' => 'SafePass123', 'is_admin' => true])->assertRedirect('/kabinet');
        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertFalse($user->is_admin);
        $other = Listing::factory()->create();
        $this->get('/kabinet/elonlar/'.$other->id.'/tahrirlash')->assertForbidden();
        $this->get('/admin')->assertForbidden();
        $business = $this->company();
        $this->get('/kabinet/biznes/'.$business->id.'/ombor')->assertForbidden();
    }

    public function test_custom_cabinet_editor_inventory_and_admin_render_with_authorized_data(): void
    {
        $owner = User::factory()->create();
        $business = $this->company($owner);
        $listing = $this->offer($business);
        $this->actingAs($owner)->get('/kabinet/elonlar/yangi')->assertOk()->assertSee('data-listing-editor', false);
        $this->get('/kabinet/elonlar/'.$listing->id.'/tahrirlash')->assertOk()->assertSee($listing->title);
        $this->get('/kabinet/biznes/'.$business->id.'/ombor')->assertOk()->assertSee('data-inventory', false);
        $owner->forceFill(['is_admin' => true])->save();
        $this->get('/admin')->assertOk()->assertSee('Tekshiruvdagi');
        $other = Listing::factory()->create(['title' => 'Admin uchun yopiq qoralama']);
        $this->get('/admin/elonlar/'.$other->id)->assertOk()->assertSee($other->title);
    }

    public function test_seller_calendar_requires_both_bounds_and_limits_requested_window(): void
    {
        $listing = Listing::factory()->create(['offer_type' => 'rent', 'rental_period' => 'day']);
        $this->actingAs($listing->user)->getJson('/api/v1/me/listings/'.$listing->id.'/calendar?from=2026-11-01')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson('/api/v1/me/listings/'.$listing->id.'/calendar?from=2026-11-01&to=2027-11-01')->assertUnprocessable();
        $this->getJson('/api/v1/me/listings/'.$listing->id.'/calendar?from=2026-11-01&to=2026-12-01')->assertOk();
    }
}
