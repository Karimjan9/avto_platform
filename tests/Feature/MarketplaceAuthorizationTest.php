<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Models\Business;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketplaceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_owners_cannot_read_private_ads_change_status_delete_or_upload(): void
    {
        $listing = Listing::factory()->create();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/me/listings/'.$listing->id)->assertForbidden();
        $this->putJson('/api/v1/me/listings/'.$listing->id, [])->assertForbidden();
        $this->patchJson('/api/v1/me/listings/'.$listing->id.'/status', ['status' => 'pending'])->assertForbidden();
        $this->deleteJson('/api/v1/me/listings/'.$listing->id)->assertForbidden();
        $this->postJson('/api/v1/me/listings/'.$listing->id.'/images', ['images' => [UploadedFile::fake()->image('car.jpg')]])->assertForbidden();
        $this->getJson('/api/v1/me/listings')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_owners_cannot_self_publish_and_only_admins_moderate(): void
    {
        $listing = Listing::factory()->create(['status' => ListingStatus::Pending]);
        Sanctum::actingAs($listing->user);
        $this->patchJson('/api/v1/me/listings/'.$listing->id.'/status', ['status' => 'published'])->assertUnprocessable();
        $this->patchJson('/api/v1/admin/listings/'.$listing->id.'/moderation', ['status' => 'published'])->assertForbidden();
        $this->getJson('/api/v1/admin/listings')->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->patchJson('/api/v1/admin/listings/'.$listing->id.'/moderation', ['status' => 'published'])
            ->assertOk()->assertJsonPath('data.status', 'published');
        $this->getJson('/api/v1/listings/'.$listing->id)->assertOk();
    }

    public function test_rejection_requires_a_reason_and_admin_can_take_down_a_published_ad(): void
    {
        $listing = Listing::factory()->published()->create();
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $url = '/api/v1/admin/listings/'.$listing->id.'/moderation';
        $this->patchJson($url, ['status' => 'rejected'])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->patchJson($url, ['status' => 'rejected', 'reason' => 'Ma’lumotni tuzating.'])->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->getJson('/api/v1/listings/'.$listing->id)->assertNotFound();
    }

    public function test_draft_cannot_be_approved_without_submission(): void
    {
        $listing = Listing::factory()->create();
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->patchJson('/api/v1/admin/listings/'.$listing->id.'/moderation', ['status' => 'published'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_sold_archived_and_deleted_ads_are_hidden_and_invalid_transitions_fail(): void
    {
        $listing = Listing::factory()->published()->create();
        Sanctum::actingAs($listing->user);
        $this->patchJson('/api/v1/me/listings/'.$listing->id.'/status', ['status' => 'sold'])->assertOk();
        $this->getJson('/api/v1/listings/'.$listing->id)->assertNotFound();
        $this->patchJson('/api/v1/me/listings/'.$listing->id.'/status', ['status' => 'pending'])->assertUnprocessable();
        $this->patchJson('/api/v1/me/listings/'.$listing->id.'/status', ['status' => 'archived'])->assertOk();
        $this->deleteJson('/api/v1/me/listings/'.$listing->id)->assertNoContent();
        $this->assertSoftDeleted('listings', ['id' => $listing->id]);
        $this->getJson('/api/v1/me/listings/'.$listing->id)->assertNotFound();
    }

    public function test_salon_and_store_creation_and_moderation_control_directory_visibility(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $ids = [];
        foreach (['car_dealership', 'parts_store'] as $type) {
            $ids[] = $this->postJson('/api/v1/me/businesses', [
                'type' => $type, 'name' => 'Buxoro biznes', 'city' => 'Buxoro', 'phone' => '+998901234567',
            ])->assertCreated()->assertJsonPath('data.status', 'pending')->json('data.id');
        }
        $this->getJson('/api/v1/businesses')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/me/businesses')->assertOk()->assertJsonCount(2, 'data');
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        foreach ($ids as $id) {
            $this->patchJson('/api/v1/admin/businesses/'.$id.'/moderation', ['status' => 'active'])->assertOk();
        }
        $this->getJson('/api/v1/businesses?type=parts_store')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'parts_store');
        $this->getJson('/api/v1/businesses/'.$ids[0])->assertOk();
    }

    public function test_business_ownership_and_type_cannot_be_changed_by_a_seller(): void
    {
        $business = Business::factory()->create();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/me/businesses/'.$business->id)->assertForbidden();
        $this->deleteJson('/api/v1/me/businesses/'.$business->id)->assertForbidden();
        Sanctum::actingAs($business->user);
        $this->putJson('/api/v1/me/businesses/'.$business->id, ['type' => 'parts_store', 'name' => 'Store', 'city' => 'Buxoro', 'phone' => '+998901234567'])
            ->assertUnprocessable()->assertJsonValidationErrors('type');
    }

    public function test_unapproved_or_deleted_businesses_cannot_expose_their_listings(): void
    {
        $business = Business::factory()->create();
        $listing = Listing::factory()->published()->for($business->user)->create(['business_id' => $business->id]);
        $this->getJson('/api/v1/listings/'.$listing->id)->assertOk();
        $business->forceFill(['status' => 'pending'])->save();
        $this->getJson('/api/v1/listings/'.$listing->id)->assertNotFound();
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $pending = Listing::factory()->for($business->user)->create(['business_id' => $business->id, 'status' => ListingStatus::Pending]);
        $this->patchJson('/api/v1/admin/listings/'.$pending->id.'/moderation', ['status' => 'published'])
            ->assertUnprocessable()->assertJsonValidationErrors('business_id');
        $business->forceFill(['status' => 'active'])->save();
        $business->delete();
        $this->getJson('/api/v1/listings/'.$listing->id)->assertNotFound();
    }

    public function test_images_are_scoped_to_their_listing_and_seller(): void
    {
        Storage::fake('public');
        $first = Listing::factory()->create();
        $second = Listing::factory()->for($first->user)->create();
        $image = $second->images()->create(['path' => 'second.jpg']);
        Sanctum::actingAs($first->user);
        $this->deleteJson('/api/v1/me/listings/'.$first->id.'/images/'.$image->id)->assertNotFound();
        Sanctum::actingAs(User::factory()->create());
        $this->deleteJson('/api/v1/me/listings/'.$second->id.'/images/'.$image->id)->assertForbidden();
        $this->assertDatabaseHas('listing_images', ['id' => $image->id]);
    }

    public function test_admin_promotion_requires_an_existing_account(): void
    {
        $this->artisan('marketplace:admin missing@example.com')->assertFailed();
        $user = User::factory()->create();
        $this->artisan('marketplace:admin '.$user->email)->assertSuccessful();
        $this->assertTrue($user->refresh()->is_admin);
    }

    public function test_unknown_oversized_ids_return_not_found_instead_of_server_errors(): void
    {
        $this->getJson('/api/v1/listings/'.str_repeat('9', 40))->assertNotFound();
        $this->getJson('/api/v1/businesses/'.str_repeat('9', 40))->assertNotFound();
    }
}
