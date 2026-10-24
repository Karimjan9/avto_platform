<?php

namespace Database\Seeders;

use App\Jobs\GenerateListingImageVariants;
use App\Models\Business;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\MarketplaceSection;
use App\Models\PartProduct;
use App\Models\User;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Models\VehicleVariant;
use App\Services\MarketplacePresentation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MarketplacePresentationSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('presentation.enabled')) {
            throw new RuntimeException('Enable MARKETPLACE_PRESENTATION_ENABLED explicitly before seeding demonstration data.');
        }
        $sources = [];
        foreach (config('presentation.images') as $key => $path) {
            $source = realpath($path) ?: realpath(public_path($path));
            if (! $source || ! is_file($source) || ! @getimagesize($source)) {
                throw new RuntimeException('Demonstration image is missing or invalid: '.$key);
            }
            $sources[$key] = $source;
        }

        DB::transaction(function () use ($sources) {
            foreach (['cars' => 'Mashinalar', 'parts' => 'Zapchastlar', 'moto' => 'Moto'] as $code => $name) {
                MarketplaceSection::firstOrCreate(['code' => $code], ['name' => $name]);
            }
            $owners = [];
            foreach (config('presentation.owners') as $key => $identity) {
                $owner = User::where('email', $identity['email'])->first();
                if ($owner && ($owner->name !== $identity['name'] || $owner->is_admin)) {
                    throw new RuntimeException('Existing account conflicts with demonstration identity: '.$key);
                }
                $owners[$key] = $owner ?: User::create($identity + ['password' => Str::random(64), 'phone' => null]);
            }
            $onix = $this->variant('Chevrolet', 'Onix', 'cars', 2022, 'DEMO · 1.2 AT', 1200);
            $tracker = $this->variant('Chevrolet', 'Tracker', 'cars', 2023, 'DEMO · AT', 1200);
            $yamaha = $this->variant('Yamaha', 'FZ-S 150', 'moto', 2022, 'DEMO · 150 cm³', 150);

            $businesses = [];
            foreach (config('presentation.businesses') as $slug => $metadata) {
                $owner = $owners[$metadata['owner']];
                $business = Business::withTrashed()->where('slug', $slug)->first();
                if ($business && ($business->trashed() || $business->user_id !== $owner->id)) {
                    throw new RuntimeException('Existing business conflicts with demonstration slug: '.$slug);
                }
                $business ??= new Business;
                $business->forceFill(['user_id' => $owner->id, 'type' => 'parts_store', 'name' => $metadata['name'], 'slug' => $slug, 'description' => 'Demo namoyish do‘koni. Haqiqiy savdo va aloqa uchun mo‘ljallanmagan.', 'city' => 'Buxoro', 'address' => 'Namoyish manzili', 'phone' => '', 'status' => 'active'])->save();
                $business->directions()->firstOrCreate(['section' => 'parts']);
                $businesses[$metadata['owner']] = $business;
            }

            $identity = config('presentation.featured_product');
            $product = PartProduct::where('identity_key', $identity['identity_key'])->first();
            if ($product && $product->created_by !== $owners['avtoqism']->id) {
                throw new RuntimeException('Existing product conflicts with demonstration identity.');
            }
            $product ??= new PartProduct;
            $product->fill($identity + ['created_by' => $owners['avtoqism']->id, 'manufacturer' => 'Chevrolet', 'article_key' => PartProduct::articleKey($identity['part_number']), 'category' => 'headlights', 'attributes' => ['side' => 'right', 'position' => 'front'], 'status' => 'active'])->save();
            $product->fitments()->updateOrCreate(['vehicle_variant_id' => $onix->id], ['year_from' => 2022, 'year_to' => 2022]);

            $onixListing = $this->listing($owners['showcase'], 'Chevrolet Onix', ['section' => 'cars', 'condition' => 'used', 'price' => '184000000.00', 'vehicle_variant_id' => $onix->id]);
            $onixListing->carDetails()->updateOrCreate([], ['brand' => 'Chevrolet', 'model' => 'Onix', 'year' => 2022, 'mileage_km' => 32000, 'engine_cc' => 1200, 'fuel_type' => 'gasoline', 'transmission' => 'automatic']);
            $this->image($onixListing, 'onix', $sources['onix']);
            $trackerListing = $this->listing($owners['showcase'], 'Chevrolet Tracker', ['section' => 'cars', 'condition' => 'used', 'price' => '245000000.00', 'vehicle_variant_id' => $tracker->id]);
            $trackerListing->carDetails()->updateOrCreate([], ['brand' => 'Chevrolet', 'model' => 'Tracker', 'year' => 2023, 'mileage_km' => 18000, 'engine_cc' => 1200, 'fuel_type' => 'gasoline', 'transmission' => 'automatic']);
            $this->image($trackerListing, 'tracker', $sources['tracker']);
            foreach (['avtoqism' => ['price' => '1250000.00', 'stock' => 3], 'buxoroparts' => ['price' => '1320000.00', 'stock' => 5]] as $key => $offer) {
                $listing = $this->listing($owners[$key], $identity['title'], ['business_id' => $businesses[$key]->id, 'section' => 'parts', 'condition' => 'new', 'price' => $offer['price'], 'part_product_id' => $product->id]);
                $detail = $listing->partDetails()->updateOrCreate([], $product->detailDefaults() + ['origin' => 'original', 'unit' => 'piece', 'stock_quantity' => $offer['stock'], 'delivery_available' => false, 'pickup_available' => true]);
                $detail->forceFill(['stock_version' => max(1, $detail->stock_version ?? 0), 'stock_updated_at' => $detail->stock_updated_at ?? now()])->save();
                $this->image($listing, 'headlight-'.$key, $sources['headlight']);
            }
            $motoListing = $this->listing($owners['showcase'], 'Yamaha FZ-S 150', ['section' => 'moto', 'condition' => 'used', 'price' => '18500000.00', 'vehicle_variant_id' => $yamaha->id]);
            $motoListing->motoDetails()->updateOrCreate([], ['type' => 'motorcycle', 'brand' => 'Yamaha', 'model' => 'FZ-S 150', 'year' => 2022, 'mileage_km' => 12000, 'engine_cc' => 150, 'fuel_type' => 'gasoline']);
            $this->image($motoListing, 'yamaha', $sources['yamaha']);
        });
        $this->command?->info('Demonstration data prepared: four recommendations and two shop offers. Real accounts and verification records were not modified.');
    }

    private function variant(string $makeName, string $modelName, string $section, int $year, string $name, int $engine): VehicleVariant
    {
        $make = VehicleMake::firstOrCreate(['name' => $makeName]);
        $model = VehicleModel::firstOrCreate(['vehicle_make_id' => $make->id, 'section' => $section, 'name' => $modelName]);
        $generation = VehicleGeneration::firstOrCreate(['vehicle_model_id' => $model->id, 'name' => 'DEMO '.$modelName.' '.$year], ['year_from' => $year, 'year_to' => $year]);

        return VehicleVariant::firstOrCreate(['vehicle_generation_id' => $generation->id, 'name' => $name], ['engine_cc' => $engine, 'fuel_type' => 'gasoline']);
    }

    private function listing(User $owner, string $title, array $attributes): Listing
    {
        $listing = Listing::withTrashed()->where('user_id', $owner->id)->where('title', $title)->first();
        if ($listing && ($listing->trashed() || ! str_starts_with($listing->description, MarketplacePresentation::LISTING_DESCRIPTION))) {
            throw new RuntimeException('Existing listing conflicts with demonstration identity: '.$title);
        }
        $listing ??= new Listing;
        $listing->forceFill($attributes + ['user_id' => $owner->id, 'title' => $title, 'description' => MarketplacePresentation::LISTING_DESCRIPTION, 'offer_type' => 'sale', 'currency' => 'UZS', 'city' => 'Buxoro', 'contact_phone' => '', 'deal_terms' => ['cash'], 'status' => 'published', 'published_at' => $listing->published_at ?? now()])->save();

        return $listing;
    }

    private function image(Listing $listing, string $key, string $source): void
    {
        $path = 'demo-presentation/'.$key.'.webp';
        if (ListingImage::where('path', $path)->where('listing_id', '!=', $listing->id)->exists()) {
            throw new RuntimeException('Existing media conflicts with demonstration path: '.$key);
        }
        $disk = Storage::disk('public');
        $content = file_get_contents($source);
        $image = $listing->images()->firstOrCreate(['path' => $path], ['sort_order' => 0]);
        $changed = ! $disk->exists($path) || hash('sha256', $disk->get($path)) !== hash('sha256', $content);
        if ($changed && ! $disk->put($path, $content)) {
            throw new RuntimeException('Could not store demonstration photo: '.$key);
        }
        if ($changed || ! $image->thumbnail_path || ! $disk->exists($image->thumbnail_path) || ! $image->display_path || ! $disk->exists($image->display_path)) {
            (new GenerateListingImageVariants($image->id))->handle();
        }
    }
}
