<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveSellerProfileRequest;
use App\Models\Business;
use App\Models\User;
use App\Services\BusinessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SellerOnboardingController extends Controller
{
    public function show(Request $request)
    {
        if ($request->user()->hasCompletedSellerProfile()) {
            return redirect()->route('cabinet');
        }

        return view('seller.onboarding', ['editing' => false, 'business' => $this->ownedBusiness($request->user())]);
    }

    public function edit(Request $request)
    {
        return view('seller.onboarding', ['editing' => true, 'business' => $this->ownedBusiness($request->user())]);
    }

    public function store(SaveSellerProfileRequest $request, BusinessService $service)
    {
        $this->save($request, $service, false);

        return redirect()->to($this->intendedDestination($request))->with('status', 'Sotuvchi profilingiz tayyor. Endi e’lon joylashtirishingiz mumkin.');
    }

    public function update(SaveSellerProfileRequest $request, BusinessService $service)
    {
        $this->save($request, $service, true);

        return redirect()->route('seller.profile')->with('status', 'Profil ma’lumotlari saqlandi.');
    }

    private function save(SaveSellerProfileRequest $request, BusinessService $service, bool $editing): void
    {
        $data = $request->validated();
        DB::transaction(function () use ($request, $service, $data, $editing) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($user->hasVerifiedPhone(), 403);
            // Serializing on the user makes repeated onboarding submissions safe.
            if (! $editing && $user->hasCompletedSellerProfile()) {
                return;
            }
            if ($data['seller_type'] === 'shop') {
                $business = $this->ownedBusiness($user);
                $businessData = [
                    'name' => $data['business_name'], 'type' => $data['business_type'],
                    'sections' => $data['sections'], 'city' => $data['city'],
                    'region' => $business?->region,
                    'address' => $data['address'], 'description' => $data['description'] ?? null,
                    'phone' => $user->phone,
                ];
                if (! $business || $this->businessChanged($business, $businessData)) {
                    $service->save($user, $businessData, $business);
                }
            }
            $user->forceFill([
                'name' => $data['name'], 'city' => $data['city'], 'seller_type' => $data['seller_type'],
                'seller_onboarded_at' => $user->seller_onboarded_at ?? now(),
            ])->save();
            $request->user()->setRawAttributes($user->getAttributes(), true);
        });
    }

    private function ownedBusiness(User $user): ?Business
    {
        return $user->businesses()->orderBy('id')->first();
    }

    private function businessChanged(Business $business, array $data): bool
    {
        $sections = $data['sections'];
        $existingSections = $business->sectionCodes();
        sort($sections);
        sort($existingSections);
        if ($sections !== $existingSections) {
            return true;
        }
        foreach (array_diff_key($data, ['sections' => true]) as $field => $value) {
            $current = $field === 'type' ? $business->type->value : $business->{$field};
            if ($current !== $value) {
                return true;
            }
        }

        return false;
    }

    private function intendedDestination(Request $request): string
    {
        $intended = $request->session()->pull('url.intended');
        if (! is_string($intended) || preg_match('/[\\\\\x00-\x20]/', $intended)) {
            return route('cabinet');
        }
        if (str_starts_with($intended, '/') && ! str_starts_with($intended, '//')) {
            return $intended;
        }
        if (str_starts_with($intended, $request->getSchemeAndHttpHost().'/')) {
            return $intended;
        }

        return route('cabinet');
    }
}
