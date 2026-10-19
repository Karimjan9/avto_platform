<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use App\Services\BusinessService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminBusinessController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['status' => ['sometimes', Rule::in(['pending', 'active', 'rejected'])], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:50']]);

        return BusinessResource::collection(Business::where('status', $data['status'] ?? 'pending')->latest('id')->paginate($data['per_page'] ?? 20));
    }

    public function moderate(Request $request, Business $business, BusinessService $service): BusinessResource
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'rejected'])],
            'reason' => ['required_if:status,rejected', 'nullable', 'string', 'min:3', 'max:2000'],
        ]);

        return new BusinessResource($service->moderate($business, $request->user(), $data['status'], $data['reason'] ?? null));
    }
}
