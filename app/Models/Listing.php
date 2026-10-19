<?php

namespace App\Models;

use App\Enums\BusinessType;
use App\Enums\ListingStatus;
use App\Enums\MarketplaceSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Listing extends Model
{
    use HasFactory, SoftDeletes;

    public const RELATIONS = ['user:id,name,phone', 'user.verifications', 'business', 'branch', 'variant', 'partProduct', 'carDetails', 'partDetails', 'motoDetails', 'rentalDetails', 'images', 'verifications', 'promotion'];

    public function verifications(): HasMany
    {
        return $this->hasMany(VerificationRecord::class);
    }

    public function promotion(): HasOne
    {
        return $this->hasOne(ListingPromotion::class);
    }

    protected $fillable = [
        'business_id', 'section', 'title', 'description', 'condition', 'offer_type',
        'rental_period', 'price', 'currency', 'is_negotiable', 'region', 'city', 'contact_phone', 'deal_terms',
        'vehicle_variant_id', 'part_product_id', 'business_branch_id',
    ];

    protected function casts(): array
    {
        return [
            'section' => MarketplaceSection::class,
            'status' => ListingStatus::class,
            'price' => 'decimal:2',
            'is_negotiable' => 'boolean',
            'deal_terms' => 'array',
            'published_at' => 'datetime',
            'moderated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BusinessBranch::class, 'business_branch_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(VehicleVariant::class, 'vehicle_variant_id');
    }

    public function partProduct(): BelongsTo
    {
        return $this->belongsTo(PartProduct::class);
    }

    public function hasFutureReservations(): bool
    {
        return RentalReservation::where('listing_id', $this->id)->where('ends_at', '>', now())->where(fn ($q) => $q->where('status', 'confirmed')->orWhere(fn ($p) => $p->where('status', 'pending')->where('expires_at', '>', now())))->exists();
    }

    public function carDetails(): HasOne
    {
        return $this->hasOne(CarDetail::class);
    }

    public function partDetails(): HasOne
    {
        return $this->hasOne(PartDetail::class);
    }

    public function motoDetails(): HasOne
    {
        return $this->hasOne(MotoDetail::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function coverImage(): HasOne
    {
        return $this->hasOne(ListingImage::class)->ofMany(['sort_order' => 'min', 'id' => 'min']);
    }

    public function rentalDetails(): HasOne
    {
        return $this->hasOne(RentalDetail::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ListingStatus::Published)
            ->whereHas('marketplaceSection', fn (Builder $section) => $section->where('is_active', true))
            ->where(fn (Builder $business) => $business->whereNull('business_id')
                ->orWhereHas('business', function (Builder $active) {
                    $active->active()->where(function (Builder $available) {
                        $available->whereHas('directions', fn (Builder $direction) => $direction->whereColumn('business_sections.section', 'listings.section'))
                            ->orWhere(function (Builder $legacy) {
                                $legacy->doesntHave('directions')->where(function (Builder $types) {
                                    foreach (BusinessType::cases() as $type) {
                                        $types->orWhere(fn (Builder $match) => $match->where('type', $type->value)->where('listings.section', $type->section()->value));
                                    }
                                });
                            });
                    });
                }))
            ->where(fn (Builder $branch) => $branch->whereNull('business_branch_id')->orWhereHas('branch', fn (Builder $b) => $b->where('is_active', true)))
            ->where(fn (Builder $product) => $product->whereNull('part_product_id')->orWhereHas('partProduct', fn (Builder $p) => $p->where('status', 'active')));
    }

    public function marketplaceSection(): BelongsTo
    {
        return $this->belongsTo(\App\Models\MarketplaceSection::class, 'section', 'code');
    }

    public function detailRelation(): string
    {
        return match ($this->section) {
            MarketplaceSection::Cars => 'carDetails',
            MarketplaceSection::Parts => 'partDetails',
            MarketplaceSection::Moto => 'motoDetails',
        };
    }
}
