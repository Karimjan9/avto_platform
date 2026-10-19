<?php

namespace Tests\Feature;

use App\Jobs\GenerateListingImageVariants;
use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ListingImageVariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_queued_images_produce_webp_sizes_and_card_uses_the_small_image(): void
    {
        Storage::fake('public');
        $listing = Listing::factory()->published()->create();
        $path = UploadedFile::fake()->image('large.jpg', 1920, 1080)->store('listings/'.$listing->id, 'public');
        $image = $listing->images()->create(['path' => $path, 'sort_order' => 1]);
        (new GenerateListingImageVariants($image->id))->handle();
        $image->refresh();
        Storage::disk('public')->assertExists([$image->thumbnail_path, $image->display_path, $image->path]);
        $thumbnail = getimagesizefromstring(Storage::disk('public')->get($image->thumbnail_path));
        $display = getimagesizefromstring(Storage::disk('public')->get($image->display_path));
        $this->assertSame([640, 360, 'image/webp'], [$thumbnail[0], $thumbnail[1], $thumbnail['mime']]);
        $this->assertSame([1600, 900], [$display[0], $display[1]]);
        $this->assertSame([1920, 1080], [$image->width, $image->height]);
        $this->get('/')->assertOk()->assertSee($image->thumbnail_path)->assertDontSee('src="'.Storage::disk('public')->url($image->path).'"', false);
    }

    public function test_non_image_values_are_rejected_instead_of_reaching_the_dimension_check(): void
    {
        $listing = Listing::factory()->create();
        $this->actingAs($listing->user)->postJson('/api/v1/me/listings/'.$listing->id.'/images', ['images' => ['invalid-string']])->assertUnprocessable()->assertJsonValidationErrors('images.0');
    }
}
