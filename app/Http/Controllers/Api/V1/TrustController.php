<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingPromotion;
use App\Models\User;
use App\Models\VerificationRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TrustController extends Controller
{
    public function verify(Request $request)
    {
        $data = $request->validate([
            'kind' => ['required', 'in:phone,business_documents,vehicle_inspection'],
            'user_id' => ['required_if:kind,phone', 'prohibited_unless:kind,phone', 'nullable', 'integer', 'exists:users,id'],
            'business_id' => ['required_if:kind,business_documents', 'prohibited_unless:kind,business_documents', 'nullable', 'integer', 'exists:businesses,id'],
            'listing_id' => ['required_if:kind,vehicle_inspection', 'prohibited_unless:kind,vehicle_inspection', 'nullable', 'integer', 'exists:listings,id'],
            'status' => ['required', 'in:verified,revoked'], 'evidence_reference' => ['required', 'string', 'between:3,255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);
        if ($data['kind'] === 'phone') {
            abort_unless(User::findOrFail($data['user_id'])->phone, 422, 'Profil telefon raqami yo‘q.');
        }
        if ($data['kind'] === 'vehicle_inspection') {
            abort_if(Listing::findOrFail($data['listing_id'])->section->value === 'parts', 422, 'Bu belgi transport uchun.');
        }
        $target = match ($data['kind']) {
            'phone' => 'user_id', 'business_documents' => 'business_id', 'vehicle_inspection' => 'listing_id'
        };
        if ($data['kind'] === 'phone') {
            $data['verified_value'] = hash('sha256', User::findOrFail($data['user_id'])->phone);
        }
        if (isset($data['expires_at'])) {
            $data['expires_at'] = Carbon::parse($data['expires_at'])->setTimezone(config('app.timezone'));
        }
        $record = VerificationRecord::updateOrCreate(['kind' => $data['kind'], $target => $data[$target]], $data + ['checked_by' => $request->user()->id, 'checked_at' => now(), 'expires_at' => null]);

        return response()->json(['data' => $record], 201);
    }

    public function promote(Request $request, Listing $listing)
    {
        $data = $request->validate(['starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'is_active' => ['required', 'boolean']]);
        foreach (['starts_at', 'ends_at'] as $key) {
            $data[$key] = Carbon::parse($data[$key])->setTimezone(config('app.timezone'));
        }

        return response()->json(['data' => ListingPromotion::updateOrCreate(['listing_id' => $listing->id], $data + ['created_by' => $request->user()->id])], 201);
    }
}
