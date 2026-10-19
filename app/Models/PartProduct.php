<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartProduct extends Model
{
    public const TECHNICAL_FIELDS = ['tyre_width_mm', 'tyre_aspect_ratio', 'tyre_diameter_inch', 'tyre_season', 'side', 'position'];

    public static function articleKey(string $value): string
    {
        return mb_strtoupper(preg_replace('/[\s\-_.]+/u', '', $value));
    }

    public function detailDefaults(): array
    {
        $defaults = ['manufacturer' => $this->manufacturer, 'part_number' => $this->part_number, 'oem_number' => $this->oem_number, 'category' => $this->category] + ($this->getAttribute('attributes') ?? []);
        if ($this->fitments()->exists()) {
            $defaults['compatible_vehicles'] = $this->fitments()->with('variant')->get()->map(function ($fitment) {
                $variant = $fitment->variant;

                return ['brand' => $variant->generation->model->make->name, 'model' => $variant->generation->model->name, 'generation' => $variant->generation->name, 'year_from' => $fitment->year_from ?? $variant->generation->year_from, 'year_to' => $fitment->year_to ?? $variant->generation->year_to, 'engine' => mb_substr($variant->engine_code ?? $variant->name, 0, 100)];
            })->all();
        }

        return $defaults;
    }

    protected $fillable = ['created_by', 'identity_key', 'title', 'manufacturer', 'part_number', 'article_key', 'oem_number', 'category', 'attributes', 'status', 'moderation_reason'];

    protected function casts(): array
    {
        return ['attributes' => 'array'];
    }

    public function fitments(): HasMany
    {
        return $this->hasMany(PartFitment::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Listing::class, 'part_product_id');
    }
}
