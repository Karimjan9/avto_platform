<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ListingResource;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Services\ListingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ListingImageController extends Controller
{
    public function store(Request $request, Listing $listing, ListingService $service)
    {
        Gate::authorize('update', $listing);
        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:'.config('marketplace.max_images')],
            'images.*' => ['bail', 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('marketplace.image_max_kb'), 'dimensions:max_width=6000,max_height=6000', function ($attribute, $file, $fail) {
                $dimensions = @getimagesize($file->getPathname());
                if ($dimensions && $dimensions[0] * $dimensions[1] > 24000000) {
                    $fail('Rasm juda katta. 24 megapikselgacha kichraytirib yuklang.');
                }
            }],
        ]);

        return (new ListingResource($service->addImages($listing, $request->file('images'))))->response()->setStatusCode(201);
    }

    public function destroy(Listing $listing, ListingImage $image, ListingService $service): ListingResource
    {
        Gate::authorize('update', $listing);

        return new ListingResource($service->removeImage($listing, $image));
    }
}
