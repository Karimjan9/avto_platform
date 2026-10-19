<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListingFilterRequest;
use App\Http\Requests\SavePartProductRequest;
use App\Http\Resources\ListingResource;
use App\Http\Resources\PartProductResource;
use App\Models\PartProduct;
use App\Services\ListingSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PartProductController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['part_number' => ['sometimes', 'string', 'max:80'], 'manufacturer' => ['sometimes', 'string', 'max:120'], 'category' => ['sometimes', 'string', 'max:30'], 'vehicle_variant_id' => ['sometimes', 'integer', 'exists:vehicle_variants,id'], 'year' => ['sometimes', 'integer', 'between:1900,'.(now()->year + 1)], 'per_page' => ['sometimes', 'integer', 'between:1,50']]);

        return $this->results($data);
    }

    private function results(array $data)
    {
        $query = PartProduct::where('status', 'active')->withCount(['offers' => fn ($q) => $q->published()->whereNotNull('business_id')]);
        if (isset($data['part_number'])) {
            $query->where('article_key', PartProduct::articleKey($data['part_number']));
        }
        foreach (['manufacturer', 'category'] as $field) {
            if (isset($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }
        if (isset($data['vehicle_variant_id'])) {
            $query->whereHas('fitments', function ($q) use ($data) {
                $q->where('vehicle_variant_id', $data['vehicle_variant_id']);
                if (isset($data['year'])) {
                    $q->where(fn ($v) => $v->whereNull('year_from')->orWhere('year_from', '<=', $data['year']))->where(fn ($v) => $v->whereNull('year_to')->orWhere('year_to', '>=', $data['year']));
                }
            });
        }

        return PartProductResource::collection($query->latest('id')->paginate($data['per_page'] ?? 20)->withQueryString());
    }

    public function show(string $product)
    {
        return new PartProductResource(PartProduct::where('status', 'active')->with('fitments.variant')->withCount(['offers' => fn ($q) => $q->published()->whereNotNull('business_id')])->findOrFail($product));
    }

    public function offers(ListingFilterRequest $request, string $product, ListingSearch $search)
    {
        $model = PartProduct::where('status', 'active')->findOrFail($product);
        $filters = $request->validated();

        return ListingResource::collection($search->query($filters)->where('part_product_id', $model->id)->whereNotNull('business_id')->paginate($filters['per_page'] ?? 20)->withQueryString());
    }

    public function store(SavePartProductRequest $request)
    {
        $data = $request->validated();
        $articleKey = PartProduct::articleKey($data['part_number']);
        $identity = hash('sha256', mb_strtoupper(trim($data['manufacturer'])).'|'.$articleKey);
        $product = DB::transaction(function () use ($request, $data, $articleKey, $identity) {
            $product = PartProduct::firstOrCreate(['identity_key' => $identity], Arr::except($data, ['fitments']) + ['article_key' => $articleKey, 'created_by' => $request->user()->id, 'status' => 'pending']);
            if ($product->wasRecentlyCreated) {
                foreach ($data['fitments'] ?? [] as $fitment) {
                    $product->fitments()->create($fitment);
                }
            }

            return $product;
        });

        return (new PartProductResource($product->load('fitments.variant')))->response()->setStatusCode($product->wasRecentlyCreated ? 201 : 200);
    }

    public function pending(Request $request)
    {
        return PartProductResource::collection(PartProduct::where('status', 'pending')->latest('id')->paginate(50));
    }

    public function mine(Request $request)
    {
        return PartProductResource::collection(PartProduct::where('created_by', $request->user()->id)->latest('id')->paginate(50));
    }

    public function moderate(Request $request, PartProduct $product)
    {
        $data = $request->validate(['status' => ['required', 'in:active,rejected'], 'reason' => ['required_if:status,rejected', 'nullable', 'string', 'between:3,2000']]);
        $product->update(['status' => $data['status'], 'moderation_reason' => $data['reason'] ?? null]);

        return new PartProductResource($product);
    }
}
