<?php

namespace App\Services;

use App\Enums\BusinessType;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;

class ListingSearch
{
    public function query(array $filters): Builder
    {
        $query = Listing::query()->published()->with(Listing::RELATIONS);
        foreach (['section', 'condition', 'offer_type', 'business_id', 'business_branch_id', 'vehicle_variant_id', 'part_product_id', 'currency', 'city', 'region'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (isset($filters['seller_type'])) {
            $filters['seller_type'] === 'private'
                ? $query->whereNull('business_id')
                : $query->whereNotNull('business_id')->where('section', BusinessType::from($filters['seller_type'])->section()->value);
        }
        if (isset($filters['deal_term'])) {
            $query->where(function (Builder $terms) use ($filters) {
                $terms->whereJsonContains('deal_terms', $filters['deal_term']);
                if ($filters['deal_term'] === 'cash') {
                    $terms->orWhereNull('deal_terms');
                }
            });
        }
        $carFilters = array_intersect_key($filters, array_flip(['generation', 'trim', 'body_type', 'technical_condition']));
        if ($carFilters) {
            $query->whereHas('carDetails', function (Builder $details) use ($carFilters) {
                foreach ($carFilters as $key => $value) {
                    $details->where($key, $value);
                }
            });
        }
        $partFilters = array_intersect_key($filters, array_flip(['manufacturer', 'oem_number', 'part_number', 'tyre_width_mm', 'tyre_aspect_ratio', 'tyre_diameter_inch', 'tyre_season']));
        foreach (['part_category' => 'category', 'part_origin' => 'origin'] as $filter => $column) {
            if (isset($filters[$filter])) {
                $partFilters[$column] = $filters[$filter];
            }
        }
        if ($partFilters || isset($filters['in_stock'])) {
            $query->whereHas('partDetails', function (Builder $details) use ($partFilters, $filters) {
                foreach ($partFilters as $key => $value) {
                    $details->where($key, $value);
                }
                if (isset($filters['in_stock'])) {
                    $details->where('stock_quantity', $filters['in_stock'] ? '>' : '=', 0);
                }
            });
        }
        if (isset($filters['abs'])) {
            $query->whereHas('motoDetails', fn (Builder $details) => $details->where('abs', $filters['abs']));
        }
        if (isset($filters['driver_option'])) {
            $query->whereHas('rentalDetails', fn (Builder $rental) => $rental->where('driver_option', $filters['driver_option']));
        }
        if (isset($filters['q'])) {
            $query->where(fn (Builder $search) => $search->where('title', 'like', '%'.$filters['q'].'%')
                ->orWhere('description', 'like', '%'.$filters['q'].'%'));
        }
        foreach (['price_min' => '>=', 'price_max' => '<='] as $field => $operator) {
            if (isset($filters[$field])) {
                $query->where('price', $operator, $filters[$field]);
            }
        }
        if (collect($filters)->hasAny(['brand', 'model', 'year_min', 'year_max', 'moto_type', 'fuel_type', 'transmission'])) {
            $query->where(function (Builder $vehicles) use ($filters) {
                $apply = function (Builder $details) use ($filters) {
                    foreach (['brand', 'model', 'fuel_type', 'transmission'] as $field) {
                        if (isset($filters[$field])) {
                            $details->where($field, $filters[$field]);
                        }
                    }
                    if (isset($filters['year_min'])) {
                        $details->where('year', '>=', $filters['year_min']);
                    }
                    if (isset($filters['year_max'])) {
                        $details->where('year', '<=', $filters['year_max']);
                    }
                };
                if (! isset($filters['moto_type'])) {
                    $vehicles->whereHas('carDetails', $apply);
                }
                $vehicles->orWhereHas('motoDetails', function (Builder $details) use ($apply, $filters) {
                    $apply($details);
                    if (isset($filters['moto_type'])) {
                        $details->where('type', $filters['moto_type']);
                    }
                });
            });
        }
        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('published_at'),
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            default => $query->orderByDesc('published_at'),
        };

        return $query->orderByDesc('id');
    }
}
