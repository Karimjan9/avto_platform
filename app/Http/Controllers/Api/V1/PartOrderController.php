<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\PartOrder;
use App\Services\BusinessAccess;
use App\Services\PartOrderService;
use Illuminate\Http\Request;

class PartOrderController extends Controller
{
    public function index(Request $request)
    {
        return PartOrder::where('user_id', $request->user()->id)->with('items')->latest('id')->paginate(50);
    }

    public function store(Request $request, PartOrderService $service)
    {
        $request->validate(['client_request_id' => ['sometimes', 'uuid']]);
        $data = $request->validate(['items' => ['required', 'array', 'list', 'min:1', 'max:20'], 'items.*' => ['array:listing_id,quantity'], 'items.*.listing_id' => ['required', 'integer', 'min:1', 'distinct'], 'items.*.quantity' => ['required', 'integer', 'between:1,1000'], 'fulfillment' => ['required', 'in:delivery,pickup'], 'recipient_name' => ['required', 'string', 'max:100'], 'contact_phone' => ['required', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/'], 'delivery_address' => ['required_if:fulfillment,delivery', 'nullable', 'string', 'max:1000']]);

        if ($request->has('client_request_id')) {
            $data['client_request_id'] = $request->input('client_request_id');
        }

        return response()->json(['data' => $service->create($request->user(), $data)], 201);
    }

    public function show(Request $request, PartOrder $order)
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        return response()->json(['data' => $order->load('items')]);
    }

    public function cancel(Request $request, PartOrder $order, PartOrderService $service)
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        abort_unless(in_array($order->status, ['pending', 'cancelled'], true), 409, 'Tasdiqlangan buyurtma uchun do‘konga murojaat qiling.');

        return response()->json(['data' => $service->transition($order, 'cancelled', ['pending', 'cancelled'])]);
    }

    public function businessOrders(Request $request, Business $business, BusinessAccess $access)
    {
        $access->authorize($request->user(), $business, 'orders');

        return PartOrder::where('business_id', $business->id)->with('items')->latest('id')->paginate(50);
    }

    public function status(Request $request, Business $business, PartOrder $order, BusinessAccess $access, PartOrderService $service)
    {
        $access->authorize($request->user(), $business, 'orders');
        abort_unless($order->business_id === $business->id, 404);
        $data = $request->validate(['status' => ['required', 'in:confirmed,ready,completed,cancelled']]);

        return response()->json(['data' => $service->transition($order, $data['status'])]);
    }
}
