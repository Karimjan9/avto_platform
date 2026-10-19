<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\PartOrder;
use App\Models\RentalReservation;
use App\Models\User;
use App\Services\PartOrderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\MarketplaceFixtures;
use Tests\TestCase;

class MarketplaceCommerceTest extends TestCase
{
    use MarketplaceFixtures, RefreshDatabase;

    private function order(int $listing, int $quantity = 2): array
    {
        return ['items' => [['listing_id' => $listing, 'quantity' => $quantity]], 'fulfillment' => 'pickup', 'recipient_name' => 'Xaridor', 'contact_phone' => '+998901234567'];
    }

    public function test_order_reserves_stock_prevents_overselling_and_cancel_restores_once(): void
    {
        $company = $this->company();
        $offer = $this->offer($company, stock: 3);
        $buyer = User::factory()->create();
        Sanctum::actingAs($buyer);
        $order = $this->postJson('/api/v1/me/orders', $this->order($offer->id))->assertCreated()->assertJsonPath('data.total', '24.68')->assertJsonPath('data.items.0.snapshot.unit', 'piece')->json('data.id');
        $this->assertSame(1, $offer->fresh()->partDetails->stock_quantity);
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/me/orders', $this->order($offer->id))->assertConflict();
        $this->getJson('/api/v1/me/orders/'.$order)->assertForbidden();
        $this->patchJson('/api/v1/me/orders/'.$order.'/cancel')->assertForbidden();
        Sanctum::actingAs($buyer);
        $this->patchJson('/api/v1/me/orders/'.$order.'/cancel')->assertOk();
        $this->patchJson('/api/v1/me/orders/'.$order.'/cancel')->assertOk();
        $this->assertSame(3, $offer->fresh()->partDetails->stock_quantity);
    }

    public function test_order_failure_rolls_back_other_items_and_expiry_releases_reservation(): void
    {
        $company = $this->company();
        $first = $this->offer($company, stock: 3);
        $second = $this->offer($company, stock: 0);
        Sanctum::actingAs(User::factory()->create());
        $data = $this->order($first->id);
        $data['items'][] = ['listing_id' => $second->id, 'quantity' => 1];
        $this->postJson('/api/v1/me/orders', $data)->assertConflict();
        $this->assertSame(3, $first->fresh()->partDetails->stock_quantity);
        $this->assertDatabaseCount('part_orders', 0);
        $id = $this->postJson('/api/v1/me/orders', $this->order($first->id))->assertCreated()->json('data.id');
        $this->travel(25)->hours();
        $this->assertSame(1, app(PartOrderService::class)->expire());
        $this->assertSame(0, app(PartOrderService::class)->expire());
        $this->assertSame('cancelled', PartOrder::find($id)->status);
        $this->assertSame(3, $first->fresh()->partDetails->stock_quantity);
    }

    public function test_order_status_requires_orders_permission_and_snapshots_do_not_change(): void
    {
        $company = $this->company();
        $offer = $this->offer($company);
        $worker = User::factory()->create();
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/v1/me/orders', $this->order($offer->id))->assertCreated()->json('data.id');
        Sanctum::actingAs($worker);
        $this->patchJson('/api/v1/me/businesses/'.$company->id.'/orders/'.$id, ['status' => 'confirmed'])->assertForbidden();
        $company->members()->create(['user_id' => $worker->id, 'permissions' => ['orders']]);
        $offer->update(['price' => '99.00']);
        $this->patchJson('/api/v1/me/businesses/'.$company->id.'/orders/'.$id, ['status' => 'confirmed'])->assertOk()->assertJsonPath('data.items.0.unit_price', '12.34');
        $this->patchJson('/api/v1/me/businesses/'.$company->id.'/orders/'.$id, ['status' => 'completed'])->assertConflict();
        $this->patchJson('/api/v1/me/businesses/'.$company->id.'/orders/'.$id, ['status' => 'ready'])->assertOk();
        $this->patchJson('/api/v1/me/businesses/'.$company->id.'/orders/'.$id, ['status' => 'completed'])->assertOk();
        $this->patchJson('/api/v1/me/businesses/'.$company->id.'/orders/'.$id, ['status' => 'cancelled'])->assertConflict();
        $this->assertSame(8, $offer->fresh()->partDetails->stock_quantity);
    }

    public function test_rental_intervals_handle_timezone_overlap_adjacency_and_privacy(): void
    {
        $this->travelTo(Carbon::parse('2026-10-18T12:00:00Z'));
        $owner = User::factory()->create();
        $listing = $this->rentalListing($owner);
        $buyer = User::factory()->create();
        Sanctum::actingAs($buyer);
        $data = ['starts_at' => '2026-10-20T08:00:00Z', 'ends_at' => '2026-10-22T08:00:00Z', 'driver_option' => 'without_driver'];
        $id = $this->postJson('/api/v1/listings/'.$listing->id.'/reservations', $data)->assertCreated()->assertJsonPath('data.snapshot.total', '60.50')->assertJsonPath('data.starts_at', '2026-10-20T08:00:00.000000Z')->json('data.id');
        $this->postJson('/api/v1/listings/'.$listing->id.'/reservations', ['starts_at' => '2026-10-20T13:00:00+05:00', 'ends_at' => '2026-10-22T13:00:00+05:00', 'driver_option' => 'without_driver'])->assertConflict();
        $this->postJson('/api/v1/listings/'.$listing->id.'/reservations', ['starts_at' => '2026-10-22T08:00:00Z', 'ends_at' => '2026-10-24T08:00:00Z', 'driver_option' => 'without_driver'])->assertCreated();
        $this->getJson('/api/v1/listings/'.$listing->id.'/availability?from=2026-10-20&to=2026-10-25')->assertOk()->assertJsonCount(2, 'data.busy')->assertJsonMissingPath('data.busy.0.user_id')->assertJsonMissingPath('data.busy.0.id');
        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/me/listings/'.$listing->id.'/calendar')->assertOk()->assertJsonCount(2, 'data');
        $this->deleteJson('/api/v1/me/listings/'.$listing->id)->assertUnprocessable();
        $this->patchJson('/api/v1/me/listings/'.$listing->id.'/calendar/'.$id, ['status' => 'confirmed'])->assertOk();
    }

    public function test_expired_pending_rentals_do_not_block_and_owner_blocks_do(): void
    {
        $this->travelTo(Carbon::parse('2026-10-18T12:00:00Z'));
        $owner = User::factory()->create();
        $listing = $this->rentalListing($owner);
        $buyer = User::factory()->create();
        Sanctum::actingAs($buyer);
        $data = ['starts_at' => '2026-10-21T08:00:00Z', 'ends_at' => '2026-10-23T08:00:00Z', 'driver_option' => 'without_driver'];
        $id = $this->postJson('/api/v1/listings/'.$listing->id.'/reservations', $data)->assertCreated()->json('data.id');
        $this->travel(25)->hours();
        Sanctum::actingAs($owner);
        $this->patchJson('/api/v1/me/listings/'.$listing->id.'/calendar/'.$id, ['status' => 'confirmed'])->assertConflict();
        $this->postJson('/api/v1/me/listings/'.$listing->id.'/calendar-blocks', ['starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'], 'note' => 'Servis'])->assertCreated();
        Sanctum::actingAs($buyer);
        $this->postJson('/api/v1/listings/'.$listing->id.'/reservations', $data)->assertConflict();
        $this->getJson('/api/v1/me/listings/'.$listing->id.'/calendar')->assertForbidden();
    }

    private function rentalListing(User $owner): Listing
    {
        $listing = Listing::factory()->published()->create(['user_id' => $owner->id, 'offer_type' => 'rent', 'rental_period' => 'day', 'price' => '30.25']);
        $listing->rentalDetails()->create(['daily_price' => '30.25', 'deposit' => '0', 'min_days' => 2, 'driver_option' => 'without_driver']);

        return $listing;
    }

    public function test_mobile_retry_does_not_duplicate_order_or_rental(): void
    {
        $company = $this->company();
        $offer = $this->offer($company);
        $buyer = User::factory()->create();
        Sanctum::actingAs($buyer);
        $data = $this->order($offer->id) + ['client_request_id' => (string) Str::uuid()];
        $id = $this->postJson('/api/v1/me/orders', $data)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/me/orders', $data)->assertCreated()->assertJsonPath('data.id', $id);
        $this->postJson('/api/v1/me/orders', array_replace($data, ['recipient_name' => 'Boshqa']))->assertConflict();
        $this->assertSame(8, $offer->fresh()->partDetails->stock_quantity);
        $this->assertDatabaseCount('part_orders', 1);
        $listing = $this->rentalListing(User::factory()->create());
        $rental = ['starts_at' => now()->addDays(3)->toIso8601String(), 'ends_at' => now()->addDays(5)->toIso8601String(), 'driver_option' => 'without_driver', 'client_request_id' => (string) Str::uuid()];
        $reservation = $this->postJson('/api/v1/listings/'.$listing->id.'/reservations', $rental)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/listings/'.$listing->id.'/reservations', $rental)->assertCreated()->assertJsonPath('data.id', $reservation);
        $this->assertDatabaseCount('rental_reservations', 1);
    }

    public function test_expiry_at_exact_deadline_cannot_be_confirmed_or_cancel_an_already_confirmed_order(): void
    {
        $company = $this->company();
        $offer = $this->offer($company);
        $buyer = User::factory()->create();
        Sanctum::actingAs($buyer);
        $id = $this->postJson('/api/v1/me/orders', $this->order($offer->id))->assertCreated()->json('data.id');
        Sanctum::actingAs($company->user);
        $this->patchJson('/api/v1/me/businesses/'.$company->id.'/orders/'.$id, ['status' => 'confirmed'])->assertOk();
        $this->travel(25)->hours();
        $this->assertSame(0, app(PartOrderService::class)->expire());
        $this->assertSame('confirmed', PartOrder::find($id)->status);
        $listing = $this->rentalListing($company->user);
        Sanctum::actingAs($buyer);
        $data = ['starts_at' => now()->addDays(3)->toIso8601String(), 'ends_at' => now()->addDays(5)->toIso8601String(), 'driver_option' => 'without_driver'];
        $rentalId = $this->postJson('/api/v1/listings/'.$listing->id.'/reservations', $data)->assertCreated()->json('data.id');
        $reservation = RentalReservation::find($rentalId);
        $this->travelTo($reservation->expires_at);
        Sanctum::actingAs($company->user);
        $this->patchJson('/api/v1/me/listings/'.$listing->id.'/calendar/'.$rentalId, ['status' => 'confirmed'])->assertConflict();
    }
}
