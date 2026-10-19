<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListingFormRequest;
use App\Services\ListingFormSchema;

class ListingFormController extends Controller
{
    public function __invoke(ListingFormRequest $request, ListingFormSchema $schema)
    {
        $context = $request->context();
        $data = $schema->describe($context);
        $data['prefill'] = $context;
        $data['read_only_fields'] = array_map(fn ($key) => 'details.'.$key, array_keys($request->catalogDefaults()));

        return response()->json(['data' => $data]);
    }
}
