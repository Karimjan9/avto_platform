<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/** Shared field definitions for API validation, Blade and Flutter form rendering. */
class ListingFormSchema
{
    public function fields(array $context): array
    {
        $section = Arr::get($context, 'section');
        $fuel = Arr::get($context, 'details.fuel_type');
        $category = Arr::get($context, 'details.category');
        $rent = $section === 'cars' && Arr::get($context, 'offer_type') === 'rent';
        $electric = $fuel === 'electric';
        $combustion = in_array($fuel, ['gasoline', 'diesel', 'lpg', 'cng', 'hybrid'], true);
        $battery = in_array($fuel, ['electric', 'hybrid'], true);
        $fields = [
            'section' => $this->choice('Yo‘nalish', ['cars', 'parts', 'moto'], true),
            'business_id' => $this->field('Salon / do‘kon', 'integer', false, ['min' => 1]),
            'business_branch_id' => $this->field('Filial', 'integer', false, ['min' => 1]),
            'vehicle_variant_id' => $this->field('Transport katalogi varianti', 'integer', false, ['min' => 1], [], in_array($section, ['cars', 'moto'], true)),
            'part_product_id' => $this->field('Detal kartochkasi', 'integer', false, ['min' => 1], [], $section === 'parts'),
            'title' => $this->field('Sarlavha', 'string', true, ['min' => 3, 'max' => 160]),
            'description' => $this->field('Tavsif', 'string', true, ['min' => 10, 'max' => 10000]),
            'condition' => $this->choice('Holati', config('marketplace.conditions'), true),
            'offer_type' => $this->choice('Taklif turi', $section === 'cars' ? config('marketplace.offer_types') : ['sale' => 'Sotuv'], true),
            'deal_terms' => $this->field('Kelishuv shartlari', 'list', false, ['min' => 1, 'max' => 3], ['default' => ['cash']]),
            'deal_terms.*' => $this->choice('Shart', config('marketplace.deal_terms'), true, ['distinct' => true]),
            'rental_period' => $this->choice('Asosiy narx muddati', config('marketplace.rental_periods'), $rent, [], $rent),
            'price' => $this->money('Narx', true),
            'currency' => $this->choice('Valyuta', config('marketplace.currencies'), true),
            'is_negotiable' => $this->field('Kelishiladi', 'boolean', false, [], ['default' => false]),
            'region' => $this->field('Viloyat', 'string', false, ['max' => 100]),
            'city' => $this->field('Shahar / tuman', 'string', true, ['max' => 100]),
            'contact_phone' => $this->field('Telefon', 'string', true, ['pattern' => '^\+[1-9][0-9]{7,14}$']),
            'submit' => $this->field('Moderatsiyaga yuborish', 'boolean', false, [], ['default' => false]),
        ];
        if (in_array($section, ['cars', 'moto'], true)) {
            $fields += [
                'details.brand' => $this->field('Marka', 'string', true, ['max' => 80]),
                'details.model' => $this->field('Model', 'string', true, ['max' => 80]),
                'details.year' => $this->field('Yil', 'integer', true, ['min' => 1900, 'max' => now()->year + 1]),
                'details.mileage_km' => $this->field('Probeg (km)', 'integer', false, ['min' => 0, 'max' => 10000000]),
                'details.fuel_type' => $this->choice('Yoqilg‘i', $section === 'moto' ? ['gasoline', 'electric'] : config('marketplace.fuel_types')),
                'details.engine_cc' => $this->field('Motor hajmi (sm³)', 'integer', $combustion, ['min' => 1, 'max' => 20000], [], ! $electric),
                'details.transmission' => $this->choice('Uzatma', config($section === 'moto' ? 'marketplace.moto_transmissions' : 'marketplace.transmissions')),
                'details.power_kw' => $this->field('Quvvat (kW)', 'number', $section === 'moto' && $electric, ['min' => 0.01, 'max' => 10000, 'decimal_places' => 2]),
            ];
        }
        if ($section === 'cars') {
            $fields += [
                'details.generation' => $this->field('Avlod', 'string', false, ['max' => 80]),
                'details.trim' => $this->field('Komplektatsiya', 'string', false, ['max' => 120]),
                'details.technical_condition' => $this->choice('Texnik holat', config('marketplace.technical_conditions')),
                'details.body_type' => $this->choice('Kuzov', config('marketplace.body_types')),
                'details.drive_type' => $this->choice('Tortish turi', config('marketplace.drive_types')),
                'details.color' => $this->field('Rangi', 'string', false, ['max' => 50]),
                'details.gas_equipment' => $this->choice('Gaz uskunasi', config('marketplace.gas_equipment'), false, [], in_array($fuel, ['gasoline', 'lpg', 'cng', 'hybrid'], true)),
                'details.battery_capacity_kwh' => $this->field('Batareya (kWh)', 'number', $electric, ['min' => 0.01, 'max' => 1000, 'decimal_places' => 2], [], $battery),
                'details.range_km' => $this->field('Yurish masofasi (km)', 'integer', $electric, ['min' => 1, 'max' => 3000], [], $battery),
                'details.charging_types' => $this->field('Quvvatlash turlari', 'list', $electric, ['min' => 1, 'max' => 8], [], $battery),
                'details.charging_types.*' => $this->choice('Quvvatlash turi', config('marketplace.charging_types'), true, ['distinct' => true], $battery),
                'details.battery_health_percent' => $this->field('Batareya holati (%)', 'number', $electric && Arr::get($context, 'condition') === 'used', ['min' => 0, 'max' => 100, 'decimal_places' => 2], [], $battery),
            ];
        }
        if ($section === 'moto') {
            $fields += [
                'details.type' => $this->choice('Moto turi', config('marketplace.moto_types'), true),
                'details.abs' => $this->field('ABS', 'boolean'),
                'details.battery_voltage_v' => $this->field('Batareya kuchlanishi (V)', 'number', $electric, ['min' => 1, 'max' => 1000, 'decimal_places' => 2], [], $electric),
                'details.battery_capacity_ah' => $this->field('Batareya sig‘imi (Ah)', 'number', $electric, ['min' => 0.01, 'max' => 10000, 'decimal_places' => 2], [], $electric),
                'details.range_km' => $this->field('Yurish masofasi (km)', 'integer', $electric, ['min' => 1, 'max' => 3000], [], $electric),
            ];
        }
        if ($section === 'parts') {
            $tyre = $category === 'tyres';
            $headlight = $category === 'headlights';
            $set = Arr::get($context, 'details.unit') === 'set';
            $fields += [
                'details.category' => $this->choice('Detal kategoriyasi', config('marketplace.part_categories'), false, ['default' => 'other']),
                'details.manufacturer' => $this->field('Ishlab chiqaruvchi', 'string', false, ['max' => 120]),
                'details.part_number' => $this->field('Artikul', 'string', false, ['max' => 80]),
                'details.oem_number' => $this->field('OEM raqami', 'string', false, ['max' => 80]),
                'details.origin' => $this->choice('Original / analog', config('marketplace.part_origins'), $headlight),
                'details.unit' => $this->choice('Savdo birligi', config('marketplace.part_units'), false, ['default' => 'piece']),
                'details.quantity_per_set' => $this->field('Komplektdagi dona', 'integer', $set, ['min' => 2, 'max' => 100], [], $set),
                'details.stock_quantity' => $this->field('Ombor qoldig‘i (savdo birligida)', 'integer', false, ['min' => 0, 'max' => 1000000], ['default' => 0]),
                'details.delivery_available' => $this->field('Yetkazib berish', 'boolean', false, [], ['default' => false]),
                'details.pickup_available' => $this->field('Olib ketish', 'boolean', false, [], ['default' => true]),
                'details.delivery_terms' => $this->field('Yetkazish shartlari', 'string', false, ['max' => 1000]),
                'details.warranty_months' => $this->field('Kafolat (oy)', 'integer', false, ['min' => 0, 'max' => 120]),
                'details.return_days' => $this->field('Qaytarish muddati (kun)', 'integer', false, ['min' => 0, 'max' => 365]),
                'details.return_terms' => $this->field('Qaytarish shartlari', 'string', false, ['max' => 2000]),
                'details.compatible_brands' => $this->field('Mos markalar', 'list', false, ['max' => 30]),
                'details.compatible_brands.*' => $this->field('Marka', 'string', true, ['max' => 80, 'distinct' => true]),
                'details.compatible_models' => $this->field('Mos modellar', 'list', false, ['max' => 50]),
                'details.compatible_models.*' => $this->field('Model', 'string', true, ['max' => 80, 'distinct' => true]),
                'details.compatible_vehicles' => $this->field('Mos transportlar', 'list', $headlight, ['min' => 1, 'max' => 50]),
                'details.compatible_vehicles.*' => $this->field('Transport', 'object', true, ['allowed_keys' => ['brand', 'model', 'generation', 'year_from', 'year_to', 'engine']]),
                'details.compatible_vehicles.*.brand' => $this->field('Marka', 'string', true, ['max' => 80]),
                'details.compatible_vehicles.*.model' => $this->field('Model', 'string', true, ['max' => 80]),
                'details.compatible_vehicles.*.generation' => $this->field('Avlod', 'string', false, ['max' => 80]),
                'details.compatible_vehicles.*.year_from' => $this->field('Yildan', 'integer', false, ['min' => 1900, 'max' => now()->year + 1]),
                'details.compatible_vehicles.*.year_to' => $this->field('Yilgacha', 'integer', false, ['min' => 1900, 'max' => now()->year + 1, 'gte_field' => 'details.compatible_vehicles.*.year_from']),
                'details.compatible_vehicles.*.engine' => $this->field('Motor varianti', 'string', false, ['max' => 100]),
                'details.tyre_width_mm' => $this->field('Shina eni (mm)', 'integer', $tyre, ['min' => 100, 'max' => 500], [], $tyre),
                'details.tyre_aspect_ratio' => $this->field('Shina profil nisbati (%)', 'integer', $tyre, ['min' => 10, 'max' => 100], [], $tyre),
                'details.tyre_diameter_inch' => $this->field('Disk diametri (dyuym)', 'integer', $tyre, ['min' => 8, 'max' => 30], [], $tyre),
                'details.tyre_season' => $this->choice('Mavsum', config('marketplace.tyre_seasons'), $tyre, [], $tyre),
                'details.manufactured_at' => $this->field('Ishlab chiqarilgan sana', 'date', false, ['before_or_equal' => now()->toDateString()], [], $tyre),
                'details.side' => $this->choice('Tomoni', config('marketplace.part_sides'), $headlight, [], $headlight),
                'details.position' => $this->choice('Joylashuvi', config('marketplace.part_positions'), $headlight, [], $headlight),
            ];
        }
        foreach (['day' => 'daily', 'week' => 'weekly', 'month' => 'monthly'] as $period => $prefix) {
            $field = $this->money(config('marketplace.rental_periods.'.$period).' narxi', false, $rent);
            if ($period === Arr::get($context, 'rental_period')) {
                $field['constraints']['equals_field'] = 'price';
                $field['value_from'] = 'price';
            }
            $fields['rental.'.$prefix.'_price'] = $field;
        }
        $fields += [
            'rental.deposit' => $this->money('Depozit (0 — depozitsiz)', $rent, $rent),
            'rental.min_days' => $this->field('Minimal muddat (kun)', 'integer', $rent, ['min' => 1, 'max' => 3650], [], $rent),
            'rental.daily_mileage_limit_km' => $this->field('Kunlik kilometr limiti (null — cheklanmagan)', 'integer', false, ['min' => 1, 'max' => 100000], [], $rent),
            'rental.driver_option' => $this->choice('Haydovchi', config('marketplace.driver_options'), $rent, [], $rent),
        ];

        return $fields;
    }

    public function describe(array $context): array
    {
        $fields = $this->fields($context);

        return [
            'schema_version' => 1,
            'context' => $context,
            'refresh_on' => ['section', 'condition', 'offer_type', 'rental_period', 'details.fuel_type', 'details.category', 'details.type', 'details.unit'],
            'fields' => (object) array_filter($fields, fn (array $field) => $field['visible']),
            'forbidden_fields' => array_keys(array_filter($fields, fn (array $field) => ! $field['visible'])),
            'seller_type' => ['read_only' => true, 'derived_from' => 'business_id', 'default' => 'private'],
        ];
    }

    public function rules(array $context): array
    {
        $rules = [];
        $fields = $this->fields($context);
        foreach ($fields as $path => $field) {
            if (! $field['visible']) {
                $rules[$path] = ['prohibited'];

                continue;
            }
            $constraints = $field['constraints'];
            $rules[$path] = ['bail', $field['required'] ? 'required' : ($field['nullable'] ? 'nullable' : 'sometimes')];
            $rules[$path][] = match ($field['type']) {
                'list', 'object' => isset($constraints['allowed_keys']) ? 'array:'.implode(',', $constraints['allowed_keys']) : 'array',
                'select', 'date' => 'string',
                'money', 'number' => 'numeric',
                default => $field['type'],
            };
            if ($field['type'] === 'list') {
                $rules[$path][] = 'list';
            }
            if (isset($field['options'])) {
                $rules[$path][] = Rule::in(array_keys($field['options']));
            }
            foreach (['min', 'max', 'before_or_equal'] as $constraint) {
                if (isset($constraints[$constraint])) {
                    $rules[$path][] = $constraint.':'.$constraints[$constraint];
                }
            }
            if ($field['type'] === 'date') {
                $rules[$path][] = 'date_format:Y-m-d';
            }
            if (isset($constraints['decimal_places'])) {
                $rules[$path][] = 'decimal:0,'.$constraints['decimal_places'];
            }
            if (isset($constraints['pattern'])) {
                $rules[$path][] = 'regex:/'.$constraints['pattern'].'/';
            }
            if ($constraints['distinct'] ?? false) {
                $rules[$path][] = 'distinct';
            }
            if (isset($constraints['gte_field'])) {
                $rules[$path][] = 'gte:'.$constraints['gte_field'];
            }
            if (isset($constraints['equals_field'])) {
                $rules[$path][] = 'gte:'.$constraints['equals_field'];
                $rules[$path][] = 'lte:'.$constraints['equals_field'];
            }
        }
        $detailKeys = array_map(fn ($key) => substr($key, 8), array_filter(array_keys($fields), fn ($key) => str_starts_with($key, 'details.') && substr_count($key, '.') === 1));
        $rules['details'] = ['present', 'array:'.implode(',', $detailKeys)];
        $rules['rental'] = Arr::get($context, 'section') === 'cars' && Arr::get($context, 'offer_type') === 'rent'
            ? ['required', 'array:daily_price,weekly_price,monthly_price,deposit,min_days,daily_mileage_limit_km,driver_option'] : ['prohibited'];

        return $rules;
    }

    private function field(string $label, string $type, bool $required = false, array $constraints = [], array $extra = [], bool $visible = true): array
    {
        return ['label' => $label, 'type' => $type, 'required' => $required, 'nullable' => ! $required && ! array_key_exists('default', $extra), 'visible' => $visible, 'constraints' => $constraints] + $extra;
    }

    private function choice(string $label, array $options, bool $required = false, array $extra = [], bool $visible = true): array
    {
        if (array_is_list($options)) {
            $options = array_combine($options, $options);
        }

        return $this->field($label, 'select', $required, Arr::only($extra, ['distinct']), Arr::except($extra, ['distinct']) + ['options' => $options], $visible);
    }

    private function money(string $label, bool $required = false, bool $visible = true): array
    {
        return $this->field($label, 'money', $required, ['min' => 0, 'max' => 99999999999999, 'decimal_places' => 2], [], $visible);
    }
}
