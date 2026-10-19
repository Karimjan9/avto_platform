<?php

namespace App\Services;

use App\Enums\ListingStatus;
use App\Enums\MarketplaceSection;
use App\Jobs\GenerateListingImageVariants;
use App\Models\Business;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ListingService
{
    public function save(User $user, array $data, ?Listing $listing = null): Listing
    {
        return DB::transaction(function () use ($user, $data, $listing) {
            $model = $listing ? Listing::query()->lockForUpdate()->findOrFail($listing->id) : new Listing;
            if ($listing && $model->section === MarketplaceSection::Parts && array_key_exists('stock_quantity', $data['details'])) {
                abort_if(! isset($data['expected_stock_version']) || (int) $data['expected_stock_version'] !== $model->partDetails?->stock_version, 409, 'Qoldiq versiyasi o‘zgargan. Taklifni qayta oling.');
            }
            if ($listing && $model->hasFutureReservations() && (($data['offer_type'] ?? null) !== 'rent' || ($data['business_id'] ?? null) != $model->business_id || ($data['business_branch_id'] ?? null) != $model->business_branch_id)) {
                throw ValidationException::withMessages(['offer_type' => 'Avval kelgusi arenda bandlovlarini bekor qiling.']);
            }
            $model->fill(array_replace(['business_id' => null, 'business_branch_id' => null, 'part_product_id' => null, 'vehicle_variant_id' => null, 'region' => null, 'rental_period' => null, 'is_negotiable' => false, 'deal_terms' => ['cash']], Arr::except($data, ['details', 'rental', 'submit', 'expected_stock_version'])));
            if (! $model->exists) {
                $model->user()->associate($user);
                $model->status = ListingStatus::Draft;
            }
            if ($model->business_id) {
                $model->user_id = Business::findOrFail($model->business_id)->user_id;
            }
            if ($data['submit'] ?? false) {
                $model->status = ListingStatus::Pending;
            } elseif ($model->status === ListingStatus::Published) {
                $model->status = ListingStatus::Pending;
            } elseif ($model->status === ListingStatus::Rejected) {
                $model->status = ListingStatus::Draft;
            }
            $this->clearModeration($model);
            if ($model->exists) {
                $model->verifications()->where('status', 'verified')->update(['status' => 'revoked']);
            }
            $model->save();
            $relation = $model->{$model->detailRelation()}();
            $detailModel = $relation->getRelated();
            $details = array_fill_keys($detailModel->getFillable(), null);
            if ($model->section === MarketplaceSection::Parts) {
                $details = array_replace($details, ['category' => 'other', 'unit' => 'piece', 'stock_quantity' => $model->partDetails?->stock_quantity ?? 0, 'delivery_available' => false, 'pickup_available' => true]);
            }
            $detail = $relation->updateOrCreate([], array_replace($details, $data['details']));
            if ($model->section === MarketplaceSection::Parts && (! $listing || array_key_exists('stock_quantity', $data['details']))) {
                $detail->stock_updated_at = now();
                $detail->stock_version++;
                $detail->save();
            }
            if ($model->offer_type === 'rent') {
                $rental = array_fill_keys($model->rentalDetails()->getRelated()->getFillable(), null);
                $rental = array_replace($rental, $data['rental']);
                $priceKey = ['day' => 'daily_price', 'week' => 'weekly_price', 'month' => 'monthly_price'][$model->rental_period];
                $rental[$priceKey] = $model->price;
                $model->rentalDetails()->updateOrCreate([], $rental);
            } else {
                $model->rentalDetails()->delete();
            }

            return $model->load(Listing::RELATIONS);
        });
    }

    public function transition(Listing $listing, ListingStatus $next): Listing
    {
        return DB::transaction(function () use ($listing, $next) {
            $model = Listing::query()->lockForUpdate()->findOrFail($listing->id);
            if (! $model->status->allowsOwnerTransition($next)) {
                throw ValidationException::withMessages(['status' => 'Bu holatga o‘tish mumkin emas.']);
            }
            if ($next !== ListingStatus::Pending && $model->hasFutureReservations()) {
                throw ValidationException::withMessages(['status' => 'Kelgusi bandlovlar mavjud.']);
            }
            if ($next === ListingStatus::Pending) {
                $this->ensureBusinessActive($model);
            }
            $model->status = $next;
            $this->clearModeration($model);
            $model->save();

            return $model->load(Listing::RELATIONS);
        });
    }

    public function moderate(Listing $listing, User $admin, string $status, ?string $reason): Listing
    {
        return DB::transaction(function () use ($listing, $admin, $status, $reason) {
            $model = Listing::query()->lockForUpdate()->findOrFail($listing->id);
            if ($model->status !== ListingStatus::Pending && ! ($status === 'rejected' && $model->status === ListingStatus::Published)) {
                throw ValidationException::withMessages(['status' => 'Bu holatdagi e’lonni moderatsiya qilish mumkin emas.']);
            }
            if ($status === ListingStatus::Published->value) {
                $this->ensureBusinessActive($model);
            }
            $model->status = ListingStatus::from($status);
            $model->moderated_by = $admin->id;
            $model->moderated_at = now();
            $model->moderation_reason = $status === 'rejected' ? $reason : null;
            $model->published_at = $status === 'published' ? now() : null;
            $model->save();

            return $model->load(Listing::RELATIONS);
        });
    }

    public function addImages(Listing $listing, array $files): Listing
    {
        $paths = [];
        try {
            return DB::transaction(function () use ($listing, $files, &$paths) {
                $model = Listing::query()->lockForUpdate()->findOrFail($listing->id);
                if ($model->images()->count() + count($files) > config('marketplace.max_images')) {
                    throw ValidationException::withMessages(['images' => 'Bitta e’londa ko‘pi bilan 10 ta rasm bo‘ladi.']);
                }
                $position = (int) $model->images()->max('sort_order');
                foreach ($files as $file) {
                    $path = $file->store('listings/'.$model->id, 'public');
                    if (! $path) {
                        throw new \RuntimeException('Rasmni saqlab bo‘lmadi.');
                    }
                    $paths[] = $path;
                    $image = $model->images()->create(['path' => $path, 'sort_order' => ++$position]);
                    GenerateListingImageVariants::dispatch($image->id)->afterCommit();
                }
                $this->reviewAfterImageChange($model);

                return $model->load(Listing::RELATIONS);
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($paths);
            throw $exception;
        }
    }

    public function removeImage(Listing $listing, ListingImage $image): Listing
    {
        return DB::transaction(function () use ($listing, $image) {
            $model = Listing::query()->lockForUpdate()->findOrFail($listing->id);
            abort_unless($image->listing_id === $model->id, 404);
            $paths = array_values(array_filter([$image->path, $image->thumbnail_path, $image->display_path]));
            $image->delete();
            $this->reviewAfterImageChange($model);
            DB::afterCommit(fn () => Storage::disk('public')->delete($paths));

            return $model->load(Listing::RELATIONS);
        });
    }

    private function reviewAfterImageChange(Listing $listing): void
    {
        if ($listing->status === ListingStatus::Published) {
            $listing->status = ListingStatus::Pending;
            $this->clearModeration($listing);
            $listing->save();
        }
    }

    private function clearModeration(Listing $listing): void
    {
        $listing->moderation_reason = null;
        $listing->moderated_by = null;
        $listing->moderated_at = null;
        $listing->published_at = null;
    }

    private function ensureBusinessActive(Listing $listing): void
    {
        if ($listing->business_id && ! Business::query()->active()->offersSection($listing->section->value)->whereKey($listing->business_id)->exists()) {
            throw ValidationException::withMessages(['business_id' => 'Salon yoki do‘kon faol bo‘lishi kerak.']);
        }
    }
}
