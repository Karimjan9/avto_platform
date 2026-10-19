<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ListingResource;
use App\Models\Listing;
use App\Services\ListingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminListingController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['status' => ['sometimes', Rule::enum(ListingStatus::class)], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:50']]);

        return ListingResource::collection(Listing::with(Listing::RELATIONS)->where('status', $data['status'] ?? 'pending')->latest('id')->paginate($data['per_page'] ?? 20));
    }

    public function moderate(Request $request, Listing $listing, ListingService $service): ListingResource
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['published', 'rejected'])],
            'reason' => ['required_if:status,rejected', 'nullable', 'string', 'min:3', 'max:2000'],
        ]);

        return new ListingResource($service->moderate($listing, $request->user(), $data['status'], $data['reason'] ?? null));
    }
}
