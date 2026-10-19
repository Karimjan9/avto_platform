<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Models\VehicleVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class VehicleCatalogController extends Controller
{
    private const MODELS = ['makes' => VehicleMake::class, 'models' => VehicleModel::class, 'generations' => VehicleGeneration::class, 'variants' => VehicleVariant::class];

    public function index(Request $request, string $kind)
    {
        abort_unless(isset(self::MODELS[$kind]), 404);
        $data = $request->validate(['section' => ['sometimes', Rule::in(['cars', 'moto'])], 'vehicle_make_id' => ['sometimes', 'integer', 'min:1'], 'vehicle_model_id' => ['sometimes', 'integer', 'min:1'], 'vehicle_generation_id' => ['sometimes', 'integer', 'min:1'], 'q' => ['sometimes', 'string', 'max:80'], 'per_page' => ['sometimes', 'integer', 'between:1,100']]);
        $query = self::MODELS[$kind]::query();
        foreach (['models' => 'vehicle_make_id', 'generations' => 'vehicle_model_id', 'variants' => 'vehicle_generation_id'] as $type => $filter) {
            if ($kind === $type && isset($data[$filter])) {
                $query->where($filter, $data[$filter]);
            }
        }
        if (isset($data['section'])) {
            match ($kind) {
                'makes' => $query->whereIn('id', VehicleModel::select('vehicle_make_id')->where('section', $data['section'])),
                'models' => $query->where('section', $data['section']),
                'generations' => $query->whereHas('model', fn ($q) => $q->where('section', $data['section'])),
                'variants' => $query->whereHas('generation.model', fn ($q) => $q->where('section', $data['section'])),
            };
        }
        if (isset($data['q'])) {
            $query->where('name', 'like', '%'.$data['q'].'%');
        }

        return $query->orderBy('name')->orderBy('id')->paginate($data['per_page'] ?? 50)->withQueryString();
    }

    public function store(Request $request, string $kind)
    {
        abort_unless(isset(self::MODELS[$kind]), 404);
        $rules = ['name' => ['required', 'string', 'max:'.($kind === 'variants' ? 120 : 80)]];
        $rules += match ($kind) {
            'makes' => [],
            'models' => ['vehicle_make_id' => ['required', 'integer', 'exists:vehicle_makes,id'], 'section' => ['required', Rule::in(['cars', 'moto'])]],
            'generations' => ['vehicle_model_id' => ['required', 'integer', 'exists:vehicle_models,id'], 'year_from' => ['required', 'integer', 'between:1900,'.(now()->year + 1)], 'year_to' => ['nullable', 'integer', 'gte:year_from', 'max:'.(now()->year + 1)]],
            'variants' => ['vehicle_generation_id' => ['required', 'integer', 'exists:vehicle_generations,id'], 'engine_code' => ['nullable', 'string', 'max:80'], 'engine_cc' => ['nullable', 'integer', 'between:1,20000'], 'fuel_type' => ['required', Rule::in(config('marketplace.fuel_types'))], 'power_kw' => ['nullable', 'numeric', 'decimal:0,2', 'between:0.01,10000']],
        };
        $data = $request->validate($rules);
        if ($kind === 'variants') {
            $generation = VehicleGeneration::with('model')->findOrFail($data['vehicle_generation_id']);
            abort_if($generation->model->section === 'moto' && ! in_array($data['fuel_type'], ['gasoline', 'electric'], true), 422, 'Moto yoqilg‘isi mos emas.');
            abort_if($data['fuel_type'] === 'electric' && ! empty($data['engine_cc']), 422, 'Elektromotorda motor hajmi bo‘lmaydi.');
        }
        $identity = match ($kind) {
            'makes' => ['name'], 'models' => ['vehicle_make_id', 'section', 'name'], 'generations' => ['vehicle_model_id', 'name'], 'variants' => ['vehicle_generation_id', 'name']
        };
        $existing = self::MODELS[$kind]::where(Arr::only($data, $identity))->first();
        if ($existing) {
            return response()->json(['data' => $existing], 200);
        }

        return response()->json(['data' => self::MODELS[$kind]::create($data)], 201);
    }
}
