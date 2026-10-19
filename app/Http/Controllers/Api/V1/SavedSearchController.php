<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListingFilterRequest;
use App\Models\SavedSearch;
use App\Services\PayloadValidator;
use Illuminate\Http\Request;

class SavedSearchController extends Controller
{
    public function index(Request $request)
    {
        return SavedSearch::where('user_id', $request->user()->id)->latest('id')->paginate(50);
    }

    public function store(Request $request, PayloadValidator $validator)
    {
        abort_if(SavedSearch::where('user_id', $request->user()->id)->count() >= 50, 422, 'Saqlangan qidiruv limiti 50 ta.');
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'filters' => ['required', 'array', 'min:1'], 'is_active' => ['sometimes', 'boolean']]);
        $filters = $validator->validate(ListingFilterRequest::class, $request, $data['filters']);
        unset($filters['page'], $filters['per_page'], $filters['sort']);
        abort_if($filters === [], 422, 'Kamida bitta qidiruv filtri kerak.');

        return response()->json(['data' => SavedSearch::create(['user_id' => $request->user()->id, 'name' => $data['name'], 'filters' => $filters, 'is_active' => $data['is_active'] ?? true])], 201);
    }

    public function update(Request $request, SavedSearch $search)
    {
        abort_unless($search->user_id === $request->user()->id, 403);
        $search->update($request->validate(['is_active' => ['required', 'boolean']]));

        return response()->json(['data' => $search]);
    }

    public function destroy(Request $request, SavedSearch $search)
    {
        abort_unless($search->user_id === $request->user()->id, 403);
        $search->delete();

        return response()->noContent();
    }
}
