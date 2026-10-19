<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListingFilterRequest;
use App\Http\Requests\SaveListingRequest;
use App\Http\Resources\ListingResource;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessInquiry;
use App\Models\BusinessMember;
use App\Models\Listing;
use App\Services\BusinessAccess;
use App\Services\ListingSearch;
use App\Services\ListingService;
use App\Services\MarketplaceNotifier;
use App\Services\PayloadValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class BusinessCabinetController extends Controller
{
    public function listings(ListingFilterRequest $request, string $business, ListingSearch $search)
    {
        $company = Business::active()->findOrFail($business);

        return ListingResource::collection($search->query($request->validated())->where('business_id', $company->id)->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function inventory(Request $request, Business $business, BusinessAccess $access)
    {
        abort_unless($access->allows($request->user(), $business, 'catalog') || $access->allows($request->user(), $business, 'inventory'), 403);

        return ListingResource::collection($business->listings()->with(Listing::RELATIONS)->latest('id')->paginate(50));
    }

    public function saveBranch(Request $request, Business $business, ?BusinessBranch $branch = null)
    {
        Gate::authorize('update', $business);
        abort_if($branch && $branch->business_id !== $business->id, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'city' => ['required', 'string', 'max:100'], 'address' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/'], 'is_active' => ['sometimes', 'boolean'],
            'working_hours' => ['nullable', 'array', 'list', 'max:7'], 'working_hours.*' => ['array:day,closed,opens,closes'],
            'working_hours.*.day' => ['required', 'integer', 'between:1,7', 'distinct'], 'working_hours.*.closed' => ['required', 'boolean'],
            'working_hours.*.opens' => ['nullable', 'date_format:H:i'], 'working_hours.*.closes' => ['nullable', 'date_format:H:i'],
        ]);
        foreach ($data['working_hours'] ?? [] as $index => $hours) {
            if (! $hours['closed'] && (empty($hours['opens']) || empty($hours['closes']))) {
                throw ValidationException::withMessages(['working_hours.'.$index => 'Ish kunida ochilish va yopilish vaqti kerak.']);
            }
        }
        abort_if(! $branch && $business->branches()->count() >= 50, 422, 'Filial limiti 50 ta.');
        $model = $branch ?? new BusinessBranch(['business_id' => $business->id]);
        $model->fill($data + ['phone' => null, 'working_hours' => null, 'is_active' => true])->save();

        return response()->json(['data' => $model], $branch ? 200 : 201);
    }

    public function members(Request $request, Business $business)
    {
        Gate::authorize('update', $business);

        return response()->json(['data' => $business->members()->with('user:id,name')->get()]);
    }

    public function saveMember(Request $request, Business $business)
    {
        Gate::authorize('update', $business);
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id'], 'permissions' => ['required', 'array', 'list', 'min:1', 'max:5'], 'permissions.*' => ['required', 'in:catalog,inventory,requests,orders,rentals', 'distinct']]);
        abort_if((int) $data['user_id'] === $business->user_id, 422, 'Egasi xodim sifatida qo‘shilmaydi.');
        abort_if($business->members()->count() >= 25 && ! $business->members()->where('user_id', $data['user_id'])->exists(), 422, 'Xodim limiti 25 ta.');

        return response()->json(['data' => $business->members()->updateOrCreate(['user_id' => $data['user_id']], ['permissions' => $data['permissions']])], 201);
    }

    public function removeMember(Request $request, Business $business, BusinessMember $member)
    {
        Gate::authorize('update', $business);
        abort_unless($member->business_id === $business->id, 404);
        $member->delete();

        return response()->noContent();
    }

    public function stock(Request $request, Business $business, Listing $listing, BusinessAccess $access)
    {
        $access->authorize($request->user(), $business, 'inventory');
        abort_unless($listing->business_id === $business->id && $listing->section->value === 'parts', 404);
        $data = $request->validate(['stock_quantity' => ['required', 'integer', 'between:0,1000000'], 'expected_version' => ['required', 'integer', 'min:0']]);
        DB::transaction(function () use ($listing, $data) {
            Listing::whereKey($listing->id)->lockForUpdate()->firstOrFail();
            $detail = $listing->partDetails()->lockForUpdate()->firstOrFail();
            abort_if($detail->stock_version !== (int) $data['expected_version'], 409, 'Qoldiq boshqa amal bilan o‘zgargan. Qayta oling.');
            $detail->stock_quantity = $data['stock_quantity'];
            $detail->stock_version++;
            $detail->stock_updated_at = now();
            $detail->save();
        });

        return new ListingResource($listing->fresh(Listing::RELATIONS));
    }

    public function import(Request $request, Business $business, BusinessAccess $access, PayloadValidator $validator, ListingService $service)
    {
        $access->authorize($request->user(), $business, 'catalog');
        $request->validate(['rows' => ['required', 'array', 'list', 'min:1', 'max:50'], 'rows.*' => ['required', 'array'], 'rows.*.id' => ['sometimes', 'integer', 'min:1', 'distinct']]);
        // Each complete row is validated below; the envelope validates only shape and IDs.
        $rows = $request->input('rows');
        $ids = DB::transaction(function () use ($request, $business, $rows, $validator, $service) {
            $ids = [];
            foreach ($rows as $index => $row) {
                $listing = isset($row['id']) ? $business->listings()->findOrFail($row['id']) : null;
                unset($row['id']);
                $row['business_id'] = $business->id;
                try {
                    $valid = $validator->validate(SaveListingRequest::class, $request, $row, ['listing' => $listing]);
                    $ids[] = $service->save($request->user(), $valid, $listing)->id;
                } catch (ValidationException $exception) {
                    $errors = [];
                    foreach ($exception->errors() as $key => $messages) {
                        $errors['rows.'.$index.'.'.$key] = $messages;
                    }
                    throw ValidationException::withMessages($errors);
                }
            }

            return $ids;
        });

        return response()->json(['data' => ['listing_ids' => $ids, 'count' => count($ids)]], 201);
    }

    public function inquire(Request $request, string $business, MarketplaceNotifier $notifier)
    {
        $company = Business::active()->findOrFail($business);
        $data = $request->validate(['message' => ['required', 'string', 'between:10,5000'], 'listing_id' => ['nullable', 'integer']]);
        if (isset($data['listing_id'])) {
            $company->listings()->published()->findOrFail($data['listing_id']);
        }
        $inquiry = DB::transaction(function () use ($request, $company, $data, $notifier) {
            $inquiry = BusinessInquiry::create($data + ['business_id' => $company->id, 'user_id' => $request->user()->id]);
            $notifier->business($company, 'requests', 'inquiry', 'Yangi murojaat', ['inquiry_id' => $inquiry->id], 'inquiry:'.$inquiry->id);

            return $inquiry;
        });

        return response()->json(['data' => $inquiry], 201);
    }

    public function inquiries(Request $request, Business $business, BusinessAccess $access)
    {
        $access->authorize($request->user(), $business, 'requests');

        return BusinessInquiry::where('business_id', $business->id)->latest('id')->paginate(50);
    }

    public function reply(Request $request, Business $business, BusinessInquiry $inquiry, BusinessAccess $access, MarketplaceNotifier $notifier)
    {
        $access->authorize($request->user(), $business, 'requests');
        abort_unless($inquiry->business_id === $business->id, 404);
        $data = $request->validate(['reply' => ['required', 'string', 'between:3,5000'], 'status' => ['required', 'in:in_progress,closed']]);
        DB::transaction(function () use ($inquiry, $data, $notifier) {
            $inquiry->update($data);
            $notifier->user($inquiry->user_id, 'inquiry_reply', 'Murojaatingizga javob keldi', ['inquiry_id' => $inquiry->id]);
        });

        return response()->json(['data' => $inquiry]);
    }

    public function myInquiries(Request $request)
    {
        return BusinessInquiry::where('user_id', $request->user()->id)->latest('id')->paginate(50);
    }
}
