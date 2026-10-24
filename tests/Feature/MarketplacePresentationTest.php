<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\PartProduct;
use App\Models\User;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Services\MarketplacePresentation;
use Database\Seeders\MarketplacePresentationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\MarketplaceFixtures;
use Tests\TestCase;

class MarketplacePresentationTest extends TestCase
{
    use MarketplaceFixtures, RefreshDatabase;

    public function test_seed_is_idempotent_preserves_real_data_and_connects_four_cards_to_two_actual_demo_offers(): void
    {
        config(['presentation.enabled' => true]);
        Storage::fake('public');
        $realCompany = $this->company();
        $realListing = $this->offer($realCompany);
        $realState = $realListing->getAttributes();
        $make = VehicleMake::create(['name' => 'Chevrolet']);
        $realModel = VehicleModel::create(['vehicle_make_id' => $make->id, 'section' => 'cars', 'name' => 'Onix']);
        $modelState = $realModel->fresh()->getAttributes();

        (new MarketplacePresentationSeeder)->run();
        $firstIds = Listing::where('description', MarketplacePresentation::LISTING_DESCRIPTION)->orderBy('id')->pluck('id')->all();
        $firstImages = ListingImage::orderBy('id')->pluck('path')->all();
        (new MarketplacePresentationSeeder)->run();

        $this->assertCount(5, $firstIds);
        $this->assertSame($firstIds, Listing::where('description', MarketplacePresentation::LISTING_DESCRIPTION)->orderBy('id')->pluck('id')->all());
        $this->assertSame($firstImages, ListingImage::orderBy('id')->pluck('path')->all());
        $this->assertSame($realState, $realListing->fresh()->getAttributes());
        $this->assertSame($modelState, $realModel->fresh()->getAttributes());
        $this->assertSame(3, User::where('email', 'like', '%@demo.buxoroavto.invalid')->count());
        $this->assertDatabaseCount('verification_records', 0);
        $this->assertDatabaseCount('listing_promotions', 0);
        foreach (ListingImage::whereIn('listing_id', $firstIds)->get() as $image) {
            Storage::disk('public')->assertExists([$image->path, $image->thumbnail_path, $image->display_path]);
            $this->assertGreaterThan(0, $image->width);
            $this->assertGreaterThan(0, $image->height);
        }
        $this->assertSame(hash_file('sha256', public_path(config('presentation.images.onix'))), hash('sha256', Storage::disk('public')->get('demo-presentation/onix.webp')));

        $home = $this->get('/')->assertOk();
        $cards = $home->viewData('listings')->getCollection();
        $this->assertSame(['Chevrolet Onix', 'Chevrolet Tracker', 'Chevrolet Onix fara (o‘ng)', 'Yamaha FZ-S 150'], $cards->pluck('title')->all());
        $this->assertSame(['184000000.00', '245000000.00', '1250000.00', '18500000.00'], $cards->pluck('price')->all());
        $this->assertTrue($cards->every(fn ($listing) => $listing->is_demo && $listing->contact_phone === ''));
        $this->assertSame([3, 5], $home->viewData('partOffers')->pluck('partDetails.stock_quantity')->all());
        $this->assertSame(['1250000.00', '1320000.00'], $home->viewData('partOffers')->pluck('price')->all());
        $this->assertSame([4.8, 4.6], $home->viewData('partOffers')->map(fn ($offer) => $offer->business->presentation['rating'])->all());
        $product = $home->viewData('featuredPart');
        $this->assertInstanceOf(PartProduct::class, $product);
        $this->getJson('/api/v1/parts/'.$product->id.'/offers')->assertOk()->assertJsonCount(2, 'data');
        $this->assertContains($realListing->id, $this->get('/elonlar')->assertOk()->viewData('listings')->getCollection()->modelKeys());
    }

    public function test_demo_detail_hides_contacts_and_booking_even_with_mode_off_and_real_detail_stays_usable(): void
    {
        config(['presentation.enabled' => false]);
        $demo = Listing::factory()->published()->create(['description' => MarketplacePresentation::LISTING_DESCRIPTION, 'offer_type' => 'rent', 'rental_period' => 'day']);
        $demo->rentalDetails()->create(['daily_price' => 100, 'deposit' => 0, 'min_days' => 1, 'driver_option' => 'without_driver']);
        $image = $demo->images()->create(['path' => 'demo-presentation/original.webp']);
        $image->forceFill(['display_path' => 'demo-presentation/reencoded.webp', 'width' => 359, 'height' => 132])->save();
        $this->actingAs(User::factory()->create())->get('/elonlar/'.$demo->id)->assertOk()
            ->assertSee('Namoyish uchun test e’lon')->assertSee('data-demo-notice', false)
            ->assertDontSee('href="tel:', false)->assertDontSee('data-rental-picker', false)->assertDontSee('data-rental-book', false)
            ->assertSee('src="'.Storage::disk('public')->url($image->path).'"', false)->assertDontSee($image->display_path);

        $real = Listing::factory()->published()->create(['offer_type' => 'rent', 'rental_period' => 'day', 'contact_phone' => '+998901234567']);
        $real->rentalDetails()->create(['daily_price' => 100, 'deposit' => 0, 'min_days' => 1, 'driver_option' => 'without_driver']);
        $this->get('/elonlar/'.$real->id)->assertOk()->assertDontSee('data-demo-notice', false)
            ->assertSee('href="tel:+998901234567"', false)->assertSee('data-rental-picker', false)->assertSee('data-rental-book', false);
    }

    public function test_seeding_requires_explicit_presentation_mode(): void
    {
        config(['presentation.enabled' => false]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Enable MARKETPLACE_PRESENTATION_ENABLED');
        (new MarketplacePresentationSeeder)->run();
    }

    public function test_existing_non_demo_account_is_never_overwritten(): void
    {
        config(['presentation.enabled' => true]);
        $user = User::factory()->create(['email' => config('presentation.owners.showcase.email'), 'name' => 'Existing real account']);
        $before = $user->fresh()->getAttributes();
        try {
            (new MarketplacePresentationSeeder)->run();
            $this->fail('The conflicting account must stop the demonstration seed.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('conflicts with demonstration identity', $exception->getMessage());
        }
        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('listings', 0);
    }

    public function test_foreign_business_slug_conflict_rolls_back_demo_changes(): void
    {
        config(['presentation.enabled' => true]);
        $business = Business::factory()->create(['slug' => 'demo-avtoqism-buxoro']);
        $before = $business->fresh()->getAttributes();
        try {
            (new MarketplacePresentationSeeder)->run();
            $this->fail('The foreign business must stop the demonstration seed.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('conflicts with demonstration slug', $exception->getMessage());
        }
        $this->assertSame($before, $business->fresh()->getAttributes());
        $this->assertSame(0, User::where('email', 'like', '%@demo.buxoroavto.invalid')->count());
        $this->assertDatabaseCount('listings', 0);
    }
}
