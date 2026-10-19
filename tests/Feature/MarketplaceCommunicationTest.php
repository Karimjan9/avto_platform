<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Services\SavedSearchMatcher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\MarketplaceFixtures;
use Tests\TestCase;

class MarketplaceCommunicationTest extends TestCase
{
    use MarketplaceFixtures, RefreshDatabase;

    public function test_buyer_requests_multiple_quotes_and_can_accept_only_one(): void
    {
        $variant = $this->variant();
        $buyer = User::factory()->create();
        $one = $this->company();
        $two = $this->company();
        Sanctum::actingAs($buyer);
        $id = $this->postJson('/api/v1/me/part-requests', ['vehicle_variant_id' => $variant->id, 'vehicle_year' => 2024, 'title' => 'Fara kerak', 'description' => 'Chap old original fara qidiryapman.', 'category' => 'headlights', 'city' => 'Buxoro'])->assertCreated()->json('data.id');
        Sanctum::actingAs($one->user);
        $this->getJson('/api/v1/me/businesses/'.$one->id.'/part-requests')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.user_id');
        $first = $this->postJson('/api/v1/me/businesses/'.$one->id.'/part-requests/'.$id.'/quotes', ['price' => '45', 'currency' => 'USD', 'message' => 'Do‘konda mavjud'])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/me/part-requests/'.$id)->assertForbidden();
        Sanctum::actingAs($two->user);
        $second = $this->postJson('/api/v1/me/businesses/'.$two->id.'/part-requests/'.$id.'/quotes', ['price' => '40', 'currency' => 'USD', 'message' => 'Yetkazib beramiz'])->assertCreated()->json('data.id');
        Sanctum::actingAs($buyer);
        $this->getJson('/api/v1/me/part-requests/'.$id)->assertOk()->assertJsonCount(2, 'data.quotes')->assertJsonMissingPath('data.quotes.0.business.moderated_by');
        $this->postJson('/api/v1/me/part-requests/'.$id.'/quotes/'.$second.'/accept')->assertOk()->assertJsonPath('data.status', 'accepted');
        $this->postJson('/api/v1/me/part-requests/'.$id.'/quotes/'.$first.'/accept')->assertConflict();
        $this->assertDatabaseHas('part_quotes', ['id' => $first, 'status' => 'rejected']);
        $this->getJson('/api/v1/me/notifications')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_saved_search_notifies_matching_public_ads_once_and_is_private(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $id = $this->postJson('/api/v1/me/saved-searches', ['name' => 'Arzon mashina', 'filters' => ['section' => 'cars', 'currency' => 'USD', 'price_max' => 15000]])->assertCreated()->json('data.id');
        $match = Listing::factory()->published()->create(['currency' => 'USD', 'price' => '14000']);
        Listing::factory()->published()->create(['currency' => 'USD', 'price' => '16000']);
        Listing::factory()->create(['currency' => 'USD', 'price' => '10000']);
        $this->assertSame(1, app(SavedSearchMatcher::class)->run());
        $this->assertSame(0, app(SavedSearchMatcher::class)->run());
        $notice = $this->getJson('/api/v1/me/notifications?unread=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.data.listing_id', $match->id)->json('data.0.id');
        $this->patchJson('/api/v1/me/notifications/'.$notice.'/read')->assertOk();
        $this->getJson('/api/v1/me/notifications?unread=1')->assertOk()->assertJsonCount(0, 'data');
        Sanctum::actingAs(User::factory()->create());
        $this->deleteJson('/api/v1/me/saved-searches/'.$id)->assertForbidden();
        $this->patchJson('/api/v1/me/notifications/'.$notice.'/read')->assertForbidden();
        $this->postJson('/api/v1/me/saved-searches', ['name' => 'Xato', 'filters' => ['price_max' => 100]])->assertUnprocessable()->assertJsonValidationErrors('currency');
    }

    public function test_inquiries_are_visible_only_to_buyer_and_permitted_company_staff(): void
    {
        $company = $this->company();
        $buyer = User::factory()->create();
        $worker = User::factory()->create();
        Sanctum::actingAs($buyer);
        $id = $this->postJson('/api/v1/businesses/'.$company->id.'/inquiries', ['message' => 'Yetkazib berish haqida ma’lumot kerak'])->assertCreated()->json('data.id');
        Sanctum::actingAs($worker);
        $this->getJson('/api/v1/me/businesses/'.$company->id.'/inquiries')->assertForbidden();
        $company->members()->create(['user_id' => $worker->id, 'permissions' => ['requests']]);
        $this->patchJson('/api/v1/me/businesses/'.$company->id.'/inquiries/'.$id, ['reply' => 'Bugun yetkazamiz', 'status' => 'closed'])->assertOk();
        Sanctum::actingAs($buyer);
        $this->getJson('/api/v1/me/inquiries')->assertOk()->assertJsonPath('data.0.reply', 'Bugun yetkazamiz');
        $this->assertDatabaseHas('marketplace_notifications', ['user_id' => $buyer->id, 'type' => 'inquiry_reply']);
    }

    public function test_verifications_are_explicit_admin_only_expire_and_are_separate_from_advertising(): void
    {
        $this->travelTo(Carbon::parse('2026-10-18T12:00:00Z'));
        $owner = User::factory()->create(['phone' => '+998901234567']);
        $company = $this->company($owner, ['cars']);
        $listing = Listing::factory()->published()->create(['user_id' => $owner->id, 'business_id' => $company->id]);
        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/listings/'.$listing->id)->assertOk()->assertJsonPath('data.seller.phone_verified', false)->assertJsonPath('data.business.documents_verified', false)->assertJsonPath('data.vehicle_inspected', false)->assertJsonPath('data.promotion.is_sponsored', false);
        $this->postJson('/api/v1/admin/verifications', ['kind' => 'phone'])->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        foreach ([['kind' => 'phone', 'user_id' => $owner->id], ['kind' => 'business_documents', 'business_id' => $company->id], ['kind' => 'vehicle_inspection', 'listing_id' => $listing->id]] as $target) {
            $this->postJson('/api/v1/admin/verifications', $target + ['status' => 'verified', 'evidence_reference' => 'PRIVATE-CHECK-001', 'expires_at' => '2026-10-19T12:00:00Z'])->assertCreated()->assertJsonMissingPath('data.evidence_reference');
        }
        $this->putJson('/api/v1/admin/listings/'.$listing->id.'/promotion', ['starts_at' => '2026-10-18T10:00:00Z', 'ends_at' => '2026-10-20T12:00:00Z', 'is_active' => true])->assertCreated();
        $this->getJson('/api/v1/listings/'.$listing->id)->assertOk()->assertJsonPath('data.seller.phone_verified', true)->assertJsonPath('data.business.documents_verified', true)->assertJsonPath('data.vehicle_inspected', true)->assertJsonPath('data.promotion.label', 'Reklama')->assertJsonMissingPath('data.seller.verifications');
        $this->travel(25)->hours();
        $this->getJson('/api/v1/listings/'.$listing->id)->assertOk()->assertJsonPath('data.seller.phone_verified', false)->assertJsonPath('data.vehicle_inspected', false)->assertJsonPath('data.promotion.is_sponsored', true);
    }
}
