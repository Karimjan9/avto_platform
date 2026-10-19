<?php

namespace App\Services;

use App\Enums\BusinessType;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BusinessService
{
    public function save(User $user, array $data, ?Business $business = null): Business
    {
        return DB::transaction(function () use ($user, $data, $business) {
            $model = $business ? Business::query()->lockForUpdate()->findOrFail($business->id) : new Business;
            $model->fill(array_replace(['description' => null, 'region' => null, 'address' => null], Arr::except($data, ['sections'])));
            if (! $model->exists) {
                $model->user()->associate($user);
                $model->slug = (Str::slug($data['name']) ?: 'business').'-'.Str::lower(Str::random(10));
            }
            $model->status = 'pending';
            if ($model->exists && $model->isDirty(['name', 'address'])) {
                $model->verifications()->where('status', 'verified')->update(['status' => 'revoked']);
            }
            $model->moderation_reason = null;
            $model->moderated_by = null;
            $model->moderated_at = null;
            $model->save();

            $sections = $data['sections'] ?? ($business ? $model->sectionCodes() : [BusinessType::from($data['type'])->section()->value]);
            $model->directions()->whereNotIn('section', $sections)->delete();
            foreach ($sections as $section) {
                $model->directions()->firstOrCreate(['section' => $section]);
            }

            return $model->fresh();
        });
    }

    public function moderate(Business $business, User $admin, string $status, ?string $reason): Business
    {
        return DB::transaction(function () use ($business, $admin, $status, $reason) {
            $model = Business::query()->lockForUpdate()->findOrFail($business->id);
            if ($model->status !== 'pending' && ! ($status === 'rejected' && $model->status === 'active')) {
                throw ValidationException::withMessages(['status' => 'Bu holatdagi biznesni moderatsiya qilish mumkin emas.']);
            }
            $model->status = $status;
            $model->moderated_by = $admin->id;
            $model->moderated_at = now();
            $model->moderation_reason = $status === 'rejected' ? $reason : null;
            $model->save();

            return $model;
        });
    }
}
