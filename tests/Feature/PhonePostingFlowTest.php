<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MarketplaceFixtures;
use Tests\TestCase;

class PhonePostingFlowTest extends TestCase
{
    use MarketplaceFixtures, RefreshDatabase;

    public function test_phone_and_profile_requirements_protect_both_web_and_direct_api_posting(): void
    {
        $unverified = User::factory()->unverifiedPhone()->create();
        $this->actingAs($unverified)->get('/kabinet/elonlar/yangi')->assertRedirect(route('phone.start'));
        $this->postJson('/api/v1/me/listings', $this->payload())->assertForbidden()->assertJsonPath('code', 'phone_verification_required');
        $incomplete = User::factory()->sellerProfileIncomplete()->create();
        $this->actingAs($incomplete)->get('/kabinet/elonlar/yangi')->assertRedirect(route('seller.onboarding'));
        $this->postJson('/api/v1/me/listings', $this->payload())->assertForbidden()->assertJsonPath('code', 'seller_onboarding_required');
        $this->assertDatabaseCount('listings', 0);
    }

    public function test_personal_listing_uses_verified_account_phone_instead_of_client_supplied_contact(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson('/api/v1/me/listings', $this->payload(['contact_phone' => '+998900000001']))->assertCreated();
        $response->assertJsonPath('data.contact_phone', $user->phone);
        $listing = Listing::findOrFail($response->json('data.id'));
        $this->assertSame($user->phone, $listing->contact_phone);
        $this->get('/kabinet/elonlar/'.$listing->id.'/tahrirlash')->assertOk()->assertSee('data-verified-phone="'.$user->phone.'"', false);
    }

    public function test_catalog_staff_keep_the_verified_store_owner_contact_and_listing_ownership(): void
    {
        $owner = User::factory()->create();
        $business = $this->company($owner);
        $business->update(['phone' => $owner->phone]);
        $staff = User::factory()->create();
        $business->members()->create(['user_id' => $staff->id, 'permissions' => ['catalog']]);
        $response = $this->actingAs($staff)->postJson('/api/v1/me/listings', $this->payload(['business_id' => $business->id, 'contact_phone' => '+998900000002']))->assertCreated();
        $response->assertJsonPath('data.contact_phone', $owner->phone);
        $this->assertDatabaseHas('listings', ['id' => $response->json('data.id'), 'user_id' => $owner->id, 'business_id' => $business->id, 'contact_phone' => $owner->phone]);
    }
}
