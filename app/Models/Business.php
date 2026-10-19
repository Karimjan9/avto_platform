<?php

namespace App\Models;

use App\Enums\BusinessType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['type', 'name', 'slug', 'description', 'region', 'city', 'address', 'phone'];

    protected $with = ['directions', 'branches', 'verifications'];

    public function verifications(): HasMany
    {
        return $this->hasMany(VerificationRecord::class);
    }

    public function directions(): HasMany
    {
        return $this->hasMany(BusinessSection::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(BusinessBranch::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(BusinessMember::class);
    }

    public function sectionCodes(): array
    {
        return $this->directions->isEmpty() ? [$this->type->section()->value] : $this->directions->pluck('section')->all();
    }

    public function scopeOffersSection(Builder $query, string $section): Builder
    {
        $type = collect(BusinessType::cases())->first(fn ($type) => $type->section()->value === $section)?->value;

        return $query->where(fn (Builder $q) => $q->whereHas('directions', fn (Builder $d) => $d->where('section', $section))
            ->orWhere(fn (Builder $legacy) => $legacy->doesntHave('directions')->where('type', $type)));
    }

    protected function casts(): array
    {
        return ['type' => BusinessType::class, 'moderated_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
