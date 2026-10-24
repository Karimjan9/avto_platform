<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PartProduct;
use App\Models\VehicleModel;
use App\Models\VehicleVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PartFinderController extends Controller
{
    public function __invoke(Request $request)
    {
        $filters = $request->validate([
            'vehicle_make_id' => ['bail', 'required', 'integer', 'min:1', 'exists:vehicle_makes,id'],
            'vehicle_model_id' => ['bail', 'required', 'integer', 'min:1', Rule::exists('vehicle_models', 'id')->where('vehicle_make_id', $request->integer('vehicle_make_id'))->where('section', 'cars')],
            'year' => ['bail', 'required', 'integer', 'between:1900,'.(now()->year + 1)],
            'vehicle_variant_id' => ['bail', 'nullable', 'integer', 'min:1'],
        ], [
            'vehicle_make_id.required' => 'Markani tanlang.',
            'vehicle_model_id.required' => 'Modelni tanlang.',
            'vehicle_model_id.exists' => 'Model tanlangan avtomobil markasiga mos bo‘lishi kerak.',
            'year.required' => 'Ishlab chiqarilgan yilni kiriting.',
            'year.integer' => 'Yilni son bilan kiriting.',
            'year.between' => 'Yil 1900 va '.(now()->year + 1).' oralig‘ida bo‘lishi kerak.',
            'integer' => 'Tanlov qiymati noto‘g‘ri.',
            'min' => 'Tanlov qiymati noto‘g‘ri.',
            'exists' => 'Tanlov katalogda topilmadi.',
        ]);
        $year = (int) $filters['year'];
        $model = VehicleModel::with('make')->findOrFail($filters['vehicle_model_id']);
        $variants = VehicleVariant::withoutEagerLoads()->with('generation')
            ->whereHas('generation', fn (Builder $generation) => $generation
                ->where('vehicle_model_id', $model->id)->where('year_from', '<=', $year)
                ->where(fn (Builder $range) => $range->whereNull('year_to')->orWhere('year_to', '>=', $year)))
            ->orderBy('name')->orderBy('id')->get();
        $selectedVariant = isset($filters['vehicle_variant_id']) ? $variants->find((int) $filters['vehicle_variant_id']) : null;
        if (isset($filters['vehicle_variant_id']) && ! $selectedVariant) {
            throw ValidationException::withMessages(['vehicle_variant_id' => 'Motor / komplektatsiya tanlangan model va yilga mos bo‘lishi kerak.']);
        }
        $variantIds = $selectedVariant ? [$selectedVariant->id] : $variants->modelKeys();
        $products = PartProduct::where('status', 'active')
            ->whereHas('fitments', fn (Builder $fitment) => $fitment
                ->whereIn('vehicle_variant_id', $variantIds)
                ->where(fn (Builder $range) => $range->whereNull('year_from')->orWhere('year_from', '<=', $year))
                ->where(fn (Builder $range) => $range->whereNull('year_to')->orWhere('year_to', '>=', $year)))
            ->withCount(['offers' => fn (Builder $offers) => $offers->published()->whereNotNull('business_id')])
            ->orderBy('title')->orderBy('id')->paginate(12)->withQueryString();

        return view('marketplace.parts-fitment', compact('filters', 'year', 'model', 'variants', 'selectedVariant', 'products'));
    }
}
