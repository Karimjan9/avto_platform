<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\MarketplaceFixtures;
use Tests\TestCase;

class BusinessCabinetTest extends TestCase
{
    use MarketplaceFixtures, RefreshDatabase;

    public function test_full_edit_cannot_overwrite_a_newer_stock_reservation(): void
    {
        $company = $this->company();
        $offer = $this->offer($company);
        Sanctum::actingAs($company->user);
        $this->patchJson('/api/v1/me/businesses/'.$company->id.'/inventory/'.$offer->id.'/stock', ['stock_quantity' => 8, 'expected_version' => 1])->assertOk();
        $body = $this->payload(['business_id' => $company->id, 'part_product_id' => $offer->part_product_id]);
        $this->putJson('/api/v1/me/listings/'.$offer->id, $body)->assertUnprocessable()->assertJsonValidationErrors('expected_stock_version');
        $this->putJson('/api/v1/me/listings/'.$offer->id, $body + ['expected_stock_version' => 1])->assertConflict();
        $this->assertSame(8, $offer->fresh()->partDetails->stock_quantity);
        $body['details'] = [];
        $this->putJson('/api/v1/me/listings/'.$offer->id, $body)->assertOk()->assertJsonPath('data.details.stock_quantity', 8)->assertJsonPath('data.details.stock_version', 2);
    }

    public function test_company_supports_all_sections_and_staff_cannot_take_ownership(): void
    {
        $owner = User::factory()->create();
        $worker = User::factory()->create();
        Sanctum::actingAs($owner);
        $companyId = $this->postJson('/api/v1/me/businesses', ['type' => 'car_dealership', 'sections' => ['cars', 'parts', 'moto'], 'name' => 'Avto kompaniya', 'city' => 'Buxoro', 'phone' => '+998901234567'])->assertCreated()->assertJsonCount(3, 'data.sections')->json('data.id');
        $company = Business::find($companyId);
        $company->forceFill(['status' => 'active'])->save();
        $member = $this->putJson('/api/v1/me/businesses/'.$companyId.'/members', ['user_id' => $worker->id, 'permissions' => ['catalog']])->assertCreated()->json('data.id');
        Sanctum::actingAs($worker);
        $listing = $this->postJson('/api/v1/me/listings', $this->payload(['business_id' => $companyId]))->assertCreated()->assertJsonPath('data.seller_type', 'parts_store')->json('data.id');
        $this->assertSame($owner->id, Listing::find($listing)->user_id);
        $this->putJson('/api/v1/me/businesses/'.$companyId.'/members', ['user_id' => $worker->id, 'permissions' => ['orders']])->assertForbidden();
        $this->putJson('/api/v1/me/listings/'.$listing, $this->payload(['expected_stock_version' => 1]))->assertUnprocessable()->assertJsonValidationErrors('business_id');
        $this->getJson('/api/v1/businesses?type=parts_store')->assertOk()->assertJsonCount(1, 'data');
        Sanctum::actingAs($owner);
        $this->deleteJson('/api/v1/me/businesses/'.$companyId.'/members/'.$member)->assertNoContent();
        Sanctum::actingAs($worker);
        $this->getJson('/api/v1/me/listings/'.$listing)->assertForbidden();
    }

    public function test_branches_and_stock_permissions_are_scoped_and_versioned(): void
    {
        $company = $this->company();
        $worker = User::factory()->create();
        Sanctum::actingAs($company->user);
        $branch = $this->postJson('/api/v1/me/businesses/'.$company->id.'/branches', ['name' => 'Markaziy filial', 'city' => 'Buxoro', 'address' => 'Markaziy ko‘cha 1', 'working_hours' => [['day' => 1, 'closed' => false, 'opens' => '09:00', 'closes' => '18:00']]])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/me/businesses/'.$company->id.'/branches', ['name' => 'Xato', 'city' => 'Buxoro', 'address' => 'Manzil', 'working_hours' => [['day' => 1, 'closed' => false]]])->assertUnprocessable();
        $offer = $this->offer($company);
        $offer->update(['business_branch_id' => $branch]);
        $this->putJson('/api/v1/me/businesses/'.$company->id.'/members', ['user_id' => $worker->id, 'permissions' => ['inventory']])->assertCreated();
        Sanctum::actingAs($worker);
        $this->patchJson('/api/v1/me/businesses/'.$company->id.'/inventory/'.$offer->id.'/stock', ['stock_quantity' => 0, 'expected_version' => 1])->assertOk()->assertJsonPath('data.details.stock_quantity', 0)->assertJsonPath('data.details.stock_version', 2)->assertJsonPath('data.status', 'published');
        $this->patchJson('/api/v1/me/businesses/'.$company->id.'/inventory/'.$offer->id.'/stock', ['stock_quantity' => 99, 'expected_version' => 1])->assertConflict();
        $this->postJson('/api/v1/me/businesses/'.$company->id.'/branches', ['name' => 'Fake'])->assertForbidden();
        $this->postJson('/api/v1/me/businesses/'.$company->id.'/import', ['rows' => [$this->payload()]])->assertForbidden();
        Sanctum::actingAs($company->user);
        $this->putJson('/api/v1/me/businesses/'.$company->id.'/branches/'.$branch, ['name' => 'Markaziy filial', 'city' => 'Buxoro', 'address' => 'Markaziy ko‘cha 1', 'is_active' => false])->assertOk();
        $this->getJson('/api/v1/listings/'.$offer->id)->assertNotFound();
    }

    public function test_bulk_import_is_atomic_and_reuses_normal_listing_validation(): void
    {
        $company = $this->company();
        Sanctum::actingAs($company->user);
        $this->postJson('/api/v1/me/businesses/'.$company->id.'/import', ['rows' => [$this->payload(), $this->payload(['price' => '-1'])]])->assertUnprocessable()->assertJsonValidationErrors('rows.1.price');
        $this->assertDatabaseCount('listings', 0);
        $this->postJson('/api/v1/me/businesses/'.$company->id.'/import', ['rows' => [$this->payload(), $this->payload(['title' => 'Ikkinchi mahsulot'])]])->assertCreated()->assertJsonPath('data.count', 2);
        $this->assertDatabaseCount('listings', 2);
        $foreign = $this->offer($this->company());
        $this->postJson('/api/v1/me/businesses/'.$company->id.'/import', ['rows' => [$this->payload(['id' => $foreign->id])]])->assertNotFound();
    }
}
