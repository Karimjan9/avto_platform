<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BusinessType;
use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'type' => ['sometimes', Rule::enum(BusinessType::class)],
            'city' => ['sometimes', 'string', 'max:100'], 'q' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $query = Business::active()->latest('id');
        if (isset($data['type'])) {
            $query->offersSection(BusinessType::from($data['type'])->section()->value);
        }
        if (isset($data['city'])) {
            $query->where('city', $data['city']);
        }
        if (isset($data['q'])) {
            $query->where('name', 'like', '%'.$data['q'].'%');
        }

        return BusinessResource::collection($query->paginate($data['per_page'] ?? 20)->withQueryString());
    }

    public function show(string $business): BusinessResource
    {
        return new BusinessResource(Business::active()->findOrFail($business));
    }
}
