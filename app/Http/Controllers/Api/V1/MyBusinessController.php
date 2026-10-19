<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveBusinessRequest;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use App\Services\BusinessAccess;
use App\Services\BusinessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MyBusinessController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:50']]);

        return BusinessResource::collection(Business::where(fn ($q) => $q->where('user_id', $request->user()->id)->orWhereHas('members', fn ($m) => $m->where('user_id', $request->user()->id)))->latest('id')->paginate($data['per_page'] ?? 20));
    }

    public function store(SaveBusinessRequest $request, BusinessService $service)
    {
        return (new BusinessResource($service->save($request->user(), $request->validated())))->response()->setStatusCode(201);
    }

    public function show(Business $business): BusinessResource
    {
        app(BusinessAccess::class)->authorize(request()->user(), $business);

        return new BusinessResource($business);
    }

    public function update(SaveBusinessRequest $request, Business $business, BusinessService $service): BusinessResource
    {
        return new BusinessResource($service->save($request->user(), $request->validated(), $business));
    }

    public function destroy(Business $business)
    {
        Gate::authorize('delete', $business);
        $business->delete();

        return response()->noContent();
    }
}
