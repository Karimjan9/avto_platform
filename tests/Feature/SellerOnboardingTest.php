<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Listing;
use App\Models\RentalReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MarketplaceFixtures;
use Tests\TestCase;

class SellerOnboardingTest extends TestCase
{
    use MarketplaceFixtures, RefreshDatabase;

    private function individual(array $changes = []): array
    {
        return array_replace(['seller_type' => 'individual', 'name' => 'Sotuvchi ismi', 'city' => 'Buxoro'], $changes);
    }

    private function shop(array $changes = []): array
    {
        return array_replace($this->individual(), ['seller_type' => 'shop', 'business_name' => 'Ehtiyot qismlar do‘koni', 'business_type' => 'parts_store', 'sections' => ['parts', 'cars'], 'address' => 'Markaziy ko‘cha 1', 'description' => 'Ehtiyot qismlar savdosi'], $changes);
    }

    public function test_guest_and_unverified_users_cannot_complete_seller_onboarding(): void
    {
        $this->get(route('seller.onboarding'))->assertRedirect(route('login'));
        $this->post(route('seller.onboarding.store'), $this->individual())->assertRedirect(route('login'));
        $user = User::factory()->unverifiedPhone()->sellerProfileIncomplete()->create();
        $this->actingAs($user)->get(route('cabinet'))->assertOk()->assertSee('Telefon raqamingizni tasdiqlang');
        $this->get(route('seller.onboarding'))->assertRedirect(route('phone.start'));
        $this->postJson(route('seller.onboarding.store'), $this->individual())->assertForbidden()->assertJsonPath('code', 'phone_verification_required');
        $this->assertNull($user->fresh()->seller_onboarded_at);
    }

    public function test_verified_individual_completes_profile_without_creating_business_and_returns_to_intended_page(): void
    {
        $user = User::factory()->sellerProfileIncomplete()->create(['email' => null]);
        $this->actingAs($user)->get(route('seller.onboarding'))->assertOk()->assertSee($user->phone)->assertSee('data-seller-profile-form', false);
        $this->withSession(['url.intended' => route('cabinet.listing.create')])->post(route('seller.onboarding.store'), $this->individual())->assertRedirect(route('cabinet.listing.create'));
        $this->assertTrue($user->fresh()->hasCompletedSellerProfile());
        $this->assertSame('individual', $user->fresh()->seller_type);
        $this->assertSame('Sotuvchi ismi', $user->fresh()->name);
        $this->assertSame('Buxoro', $user->fresh()->city);
        $this->assertNull($user->fresh()->email);
        $this->assertDatabaseCount('businesses', 0);
        $this->get(route('seller.onboarding'))->assertRedirect(route('cabinet'));
    }

    public function test_shop_creates_pending_owned_business_with_verified_server_phone_and_repeat_is_idempotent(): void
    {
        $user = User::factory()->sellerProfileIncomplete()->create();
        $this->actingAs($user)->post(route('seller.onboarding.store'), $this->shop())->assertRedirect(route('cabinet'));
        $business = Business::firstOrFail();
        $this->assertSame($user->id, $business->user_id);
        $this->assertSame($user->phone, $business->phone);
        $this->assertSame('pending', $business->status);
        $this->assertEqualsCanonicalizing(['parts', 'cars'], $business->sectionCodes());
        $this->assertTrue($user->fresh()->hasCompletedSellerProfile());
        $this->assertSame('shop', $user->fresh()->seller_type);
        $this->assertDatabaseCount('verification_records', 0);
        $onboarded = $user->fresh()->seller_onboarded_at->toISOString();
        $this->post(route('seller.onboarding.store'), $this->shop(['business_name' => 'Takror yuborilgan boshqa nom']))->assertRedirect(route('cabinet'));
        $this->assertDatabaseCount('businesses', 1);
        $this->assertSame('Ehtiyot qismlar do‘koni', $business->fresh()->name);
        $this->assertSame($onboarded, $user->fresh()->seller_onboarded_at->toISOString());
        $this->get(route('cabinet'))->assertOk()->assertSee('Ehtiyot qismlar do‘koni')->assertSee('Tekshiruvda');
    }

    public function test_profile_rejects_unverified_phone_and_privilege_or_ownership_fields(): void
    {
        $user = User::factory()->sellerProfileIncomplete()->create();
        $this->actingAs($user)->postJson(route('seller.onboarding.store'), $this->shop([
            'phone' => '+998901234567', 'contact_phone' => '+998901234567', 'phone_verified_at' => now()->toISOString(),
            'seller_onboarded_at' => now()->toISOString(), 'business_id' => 123, 'user_id' => 456, 'is_admin' => true, 'status' => 'active',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['phone', 'contact_phone', 'phone_verified_at', 'seller_onboarded_at', 'business_id', 'user_id', 'is_admin', 'status']);
        $this->assertNull($user->fresh()->seller_onboarded_at);
        $this->assertFalse($user->fresh()->is_admin);
        $this->assertDatabaseCount('businesses', 0);
    }

    public function test_shop_requires_actual_address_and_matching_primary_direction_before_writing_any_data(): void
    {
        $user = User::factory()->sellerProfileIncomplete()->create();
        $this->actingAs($user)->postJson(route('seller.onboarding.store'), $this->shop(['address' => '', 'sections' => ['moto']]))->assertUnprocessable()->assertJsonValidationErrors(['address', 'sections']);
        $this->assertNull($user->fresh()->seller_type);
        $this->assertNull($user->fresh()->seller_onboarded_at);
        $this->assertDatabaseCount('businesses', 0);
    }

    public function test_onboarding_uses_only_owned_business_and_never_a_business_where_user_is_staff(): void
    {
        $user = User::factory()->sellerProfileIncomplete()->create();
        $other = $this->company();
        $otherName = $other->name;
        $other->members()->create(['user_id' => $user->id, 'permissions' => ['catalog']]);
        $owned = $this->company($user);
        $this->actingAs($user)->post(route('seller.onboarding.store'), $this->shop())->assertRedirect(route('cabinet'));
        $this->assertDatabaseCount('businesses', 2);
        $this->assertSame('Ehtiyot qismlar do‘koni', $owned->fresh()->name);
        $this->assertSame('pending', $owned->fresh()->status);
        $this->assertSame($user->phone, $owned->fresh()->phone);
        $this->assertSame($otherName, $other->fresh()->name);
        $this->assertSame('active', $other->fresh()->status);
    }

    public function test_profile_edit_preserves_phone_and_does_not_requeue_unchanged_business(): void
    {
        $user = User::factory()->create(['seller_type' => 'shop']);
        $data = $this->shop(['name' => $user->name]);
        $business = $this->company($user, ['parts', 'cars']);
        $business->update(['name' => $data['business_name'], 'phone' => $user->phone, 'address' => $data['address'], 'description' => $data['description']]);
        $this->actingAs($user)->get(route('seller.profile'))->assertOk()->assertSee($business->name);
        $this->patch(route('seller.profile.update'), $data)->assertRedirect(route('seller.profile'));
        $this->assertSame('active', $business->fresh()->status);
        $this->patch(route('seller.profile.update'), array_replace($data, ['business_name' => 'Yangilangan do‘kon', 'city' => 'Kogon']))->assertRedirect(route('seller.profile'));
        $this->assertSame('pending', $business->fresh()->status);
        $this->assertSame('Kogon', $user->fresh()->city);
        $this->assertSame($user->phone, $business->fresh()->phone);
        $this->assertDatabaseCount('businesses', 1);
    }

    public function test_completed_individual_can_add_shop_through_profile_without_duplicate_on_repeated_update(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch(route('seller.profile.update'), $this->shop())->assertRedirect(route('seller.profile'));
        $this->patch(route('seller.profile.update'), $this->shop())->assertRedirect(route('seller.profile'));
        $this->assertSame('shop', $user->fresh()->seller_type);
        $this->assertDatabaseCount('businesses', 1);
        $this->assertSame('pending', Business::firstOrFail()->status);
    }

    public function test_intended_redirect_rejects_external_and_backslash_urls(): void
    {
        foreach (['https://outside.example.test/steal', '//outside.example.test/steal', '/\\outside.example.test/steal'] as $intended) {
            $user = User::factory()->sellerProfileIncomplete()->create();
            $this->actingAs($user)->withSession(['url.intended' => $intended])->post(route('seller.onboarding.store'), $this->individual())->assertRedirect(route('cabinet'));
        }
    }

    public function test_posting_requires_completed_profile_and_preserves_original_intended_destination(): void
    {
        $user = User::factory()->sellerProfileIncomplete()->create();
        $this->actingAs($user)->withSession(['url.intended' => '/kabinet/elonlar/yangi?section=moto'])->get(route('cabinet.listing.create'))->assertRedirect(route('seller.onboarding'))->assertSessionHas('url.intended', '/kabinet/elonlar/yangi?section=moto');
        $this->postJson('/api/v1/me/listings', $this->payload())->assertForbidden()->assertJsonPath('code', 'seller_onboarding_required');
    }

    public function test_cabinet_filter_and_status_actions_are_owner_scoped_and_use_transition_guards(): void
    {
        $owner = User::factory()->create();
        $draft = Listing::factory()->for($owner)->create(['title' => 'Shaxsiy qoralama']);
        $published = Listing::factory()->for($owner)->published()->create(['title' => 'Shaxsiy faol e’lon']);
        $other = Listing::factory()->published()->create(['title' => 'Begona maxfiy e’lon']);
        $this->actingAs($owner)->get(route('cabinet', ['status' => 'draft']))->assertOk()->assertSee($draft->title)->assertDontSee($published->title)->assertDontSee($other->title);
        $this->get(route('cabinet', ['q' => 'Shaxsiy faol']))->assertOk()->assertSee($published->title)->assertDontSee($draft->title);
        $this->patch(route('cabinet.listing.status', $other), ['status' => 'sold'])->assertForbidden();
        $this->patchJson(route('cabinet.listing.status', $draft), ['status' => 'sold'])->assertUnprocessable();
        $this->patch(route('cabinet.listing.status', $published), ['status' => 'sold'])->assertRedirect(route('cabinet'));
        $this->assertSame('sold', $published->fresh()->status->value);
        $this->assertSame('published', $other->fresh()->status->value);
    }

    public function test_verified_timestamps_cannot_be_mass_assigned_and_legacy_phone_collision_preflight_is_safe(): void
    {
        $user = new User(['phone_verified_at' => now(), 'seller_onboarded_at' => now()]);
        $this->assertNull($user->phone_verified_at);
        $this->assertNull($user->seller_onboarded_at);
        $first = User::factory()->unverifiedPhone()->create(['phone' => '+998 90 123 45 67']);
        $second = User::factory()->unverifiedPhone()->create(['phone' => '+998901234567']);
        $migration = require database_path('migrations/2026_10_24_090000_add_verified_seller_profile_to_users.php');
        try {
            $migration->up();
            $this->fail('Ambiguous phone ownership must stop migration before rewriting contacts.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('share a normalized phone number', $error->getMessage());
        }
        $this->assertSame('+998 90 123 45 67', DB::table('users')->where('id', $first->id)->value('phone'));
        $this->assertSame('+998901234567', DB::table('users')->where('id', $second->id)->value('phone'));
    }

    public function test_business_api_always_uses_server_verified_contact_number(): void
    {
        $user = User::factory()->create();
        $data = ['type' => 'parts_store', 'sections' => ['parts'], 'name' => 'API do‘koni', 'city' => 'Buxoro'];
        $this->actingAs($user)->postJson('/api/v1/me/businesses', $data + ['phone' => 'client supplied unverified number'])
            ->assertCreated()->assertJsonPath('data.phone', $user->phone)->assertJsonPath('data.status', 'pending');
        $business = $user->businesses()->firstOrFail();
        $this->putJson('/api/v1/me/businesses/'.$business->id, $data)->assertOk()->assertJsonPath('data.phone', $user->phone);
        $this->assertSame($user->phone, $business->fresh()->phone);
    }

    public function test_cabinet_status_action_preserves_future_rental_reservation_guard(): void
    {
        $owner = User::factory()->create();
        $listing = Listing::factory()->for($owner)->published()->create(['offer_type' => 'rent', 'rental_period' => 'day']);
        RentalReservation::create(['listing_id' => $listing->id, 'status' => 'confirmed', 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(3)]);
        $this->actingAs($owner)->patchJson(route('cabinet.listing.status', $listing), ['status' => 'archived'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame('published', $listing->fresh()->status->value);
    }
}
