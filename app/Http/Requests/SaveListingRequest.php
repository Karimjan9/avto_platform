<?php

namespace App\Http\Requests;

use App\Enums\MarketplaceSection;
use App\Models\Business;
use App\Models\Listing;
use App\Models\PartProduct;
use App\Models\VehicleVariant;
use App\Services\BusinessAccess;
use App\Services\ListingFormSchema;
use App\Services\VehicleCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveListingRequest extends FormRequest
{
    private array $catalogDefaults = [];

    protected function prepareForValidation(): void
    {
        if ($this->user()?->hasVerifiedPhone()) {
            $phone = $this->user()->phone;
            if (is_scalar($this->input('business_id')) && ctype_digit((string) $this->input('business_id'))) {
                $business = Business::with('user:id,phone,phone_verified_at')->find($this->integer('business_id'));
                if ($business?->user?->hasVerifiedPhone() && $business->phone === $business->user->phone) {
                    $phone = $business->phone;
                }
            }
            $this->merge(['contact_phone' => $phone]);
        }
        if (! is_array($this->input('details'))) {
            return;
        }
        $id = $this->input('vehicle_variant_id');
        if (in_array($this->input('section'), ['cars', 'moto'], true) && is_scalar($id) && ctype_digit((string) $id) && strlen((string) $id) < 19) {
            $variant = VehicleVariant::find($id);
            if ($variant) {
                $this->catalogDefaults = app(VehicleCatalog::class)->defaults($variant);
            }
        }
        $id = $this->input('part_product_id');
        if ($this->input('section') === 'parts' && is_scalar($id) && ctype_digit((string) $id) && strlen((string) $id) < 19) {
            $product = PartProduct::where('status', 'active')->find($id);
            if ($product) {
                $this->catalogDefaults = $product->detailDefaults();
            }
        }
        $this->merge(['details' => array_replace($this->catalogDefaults, $this->input('details'))]);
    }

    public function authorize(): bool
    {
        $listing = $this->route('listing');

        return $this->user() && (! $listing instanceof Listing || $this->user()->can('update', $listing));
    }

    public function rules(ListingFormSchema $schema): array
    {
        $listing = $this->route('listing');
        $section = is_string($this->input('section')) ? MarketplaceSection::tryFrom($this->input('section')) : null;
        $rules = $schema->rules($this->all());
        $rules['expected_stock_version'] = [Rule::requiredIf($listing instanceof Listing && $this->input('section') === 'parts' && $this->has('details.stock_quantity')), 'nullable', 'integer', 'min:0'];
        $rules['section'][] = Rule::exists('marketplace_sections', 'code')->where('is_active', true);
        $rules['business_id'][] = Rule::exists('businesses', 'id')->where('status', 'active')->whereNull('deleted_at');
        $rules['business_branch_id'][] = Rule::exists('business_branches', 'id')->where('business_id', is_scalar($this->input('business_id')) ? $this->input('business_id') : null)->where('is_active', true);
        $rules['vehicle_variant_id'][] = Rule::exists('vehicle_variants', 'id');
        $rules['part_product_id'][] = Rule::exists('part_products', 'id')->where('status', 'active');
        foreach (['status', 'user_id', 'moderated_by', 'moderated_at', 'moderation_reason', 'published_at', 'seller_type'] as $field) {
            $rules[$field] = ['prohibited'];
        }
        if ($listing instanceof Listing) {
            $rules['section'][] = Rule::in([$listing->section->value]);
        }

        return $rules;
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if ($this->filled('business_id')) {
                $business = Business::find($this->integer('business_id'));
                if (! $business || ! app(BusinessAccess::class)->allows($this->user(), $business, 'catalog') || ! in_array($this->input('section'), $business->sectionCodes(), true)) {
                    $validator->errors()->add('business_id', 'Mos yo‘nalish va mahsulot boshqarish huquqi talab qilinadi.');
                }
            }
            $listing = $this->route('listing');
            if ($listing instanceof Listing && $listing->business && $listing->business->user_id !== $this->user()->id && $listing->business_id !== $this->integer('business_id')) {
                $validator->errors()->add('business_id', 'Xodim e’lonni boshqa kompaniyaga ko‘chira olmaydi.');
            }
            if ($this->filled('vehicle_variant_id')) {
                $variant = VehicleVariant::find($this->integer('vehicle_variant_id'));
                if (! $variant || $variant->generation->model->section !== $this->input('section') || ! app(VehicleCatalog::class)->validYear($variant, $this->integer('details.year'))) {
                    $validator->errors()->add('vehicle_variant_id', 'Katalog varianti yo‘nalish yoki yilga mos emas.');
                }
            }
            foreach ($this->catalogDefaults as $key => $value) {
                if ($this->input('details.'.$key) != $value) {
                    $validator->errors()->add('details.'.$key, 'Qiymat umumiy katalogga mos bo‘lishi kerak.');
                }
            }
            if ($this->input('section') === 'parts' && ! $this->boolean('details.delivery_available') && ! $this->boolean('details.pickup_available', true)) {
                $validator->errors()->add('details.pickup_available', 'Kamida bitta olish usulini tanlang.');
            }
        }];
    }
}
