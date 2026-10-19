<?php

namespace App\Services;

use App\Models\VehicleVariant;

class VehicleCatalog
{
    public function defaults(VehicleVariant $variant): array
    {
        $generation = $variant->generation;
        $model = $generation->model;
        $data = ['brand' => $model->make->name, 'model' => $model->name, 'fuel_type' => $variant->fuel_type];
        if ($model->section === 'cars') {
            $data += ['generation' => $generation->name, 'trim' => $variant->name];
        }
        if ($variant->engine_cc !== null) {
            $data['engine_cc'] = $variant->engine_cc;
        }
        if ($variant->power_kw !== null) {
            $data['power_kw'] = $variant->power_kw;
        }

        return $data;
    }

    public function validYear(VehicleVariant $variant, int $year): bool
    {
        return $year >= $variant->generation->year_from && ($variant->generation->year_to === null || $year <= $variant->generation->year_to);
    }
}
