<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invoice\ValidationRequest;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function dropDown()
    {
        return Invoice::orderByDesc('id')->get();
    }

    public function index()
    {
        $search = trim(request('search'));


        $status = request('status');

        $business_source_id = request('business_source_id');
        $delivery_service_id = request('delivery_service_id');
        $payment_method = request('payment_method');


        $from = request('from') ? request('from') . " 00:00:00" : date("Y-m-d 00:00:00");
        $to = request('to') ? request('to') . " 23:59:59" : date("Y-m-d 23:59:59");

        $dates = [$from, $to];

        $perPage = min((int) request('per_page', 15), 100); // Limit max results per page

        return Invoice::with([
            'customer' => function ($q) {
                $q->with(['shipping_address', 'billing_address']);
            },
            'order',
            'business_source',
            'delivery_service'
        ])
            ->when($search, function ($q) use ($search) {

                $order_id = Order::where("order_id", $search)->value("id");

                if ($order_id) {
                    $q->where('order_id', $order_id);
                } else {
                    $q->where('id', $search)
                        ->orWhereHas('order', function ($q2) use ($search) {
                            $q2->where('tracking_number', $search);
                        });
                }
            })
            ->orderByDesc('id')
            ->paginate(request('per_page'));
    }

    public function store(ValidationRequest $request)
    {
        $validatedData = $request->validated();

        $orderPayload = [
            "customer_id" => $validatedData['customer_id'],
            "order_id" => $validatedData['order_id'],
            "business_source_id" => $validatedData['business_source_id'],
            "delivery_service_id" => $validatedData['delivery_service_id'],
            "tracking_number" => $validatedData['tracking_number'],
            "status" => $validatedData['status'],
        ];

        $order = Invoice::create($orderPayload);

        return $order;
    }

    public function update(Request $request, Invoice $Invoice)
    {
        // Validate incoming request data
        $validated = $request->validate([
            'business_source_id' => 'required|integer|exists:business_sources,id',
            'delivery_service_id' => 'required|integer|exists:delivery_services,id',
            'tracking_number' => 'nullable|max:255',
            'payment_method' => 'required|string|max:100',
            'payment_method_title' => 'required|string|max:255',
            'paid_amount' => 'required|numeric|min:0',
            'order_id' => 'required',
            'status' => 'required'
        ]);

        $orderPayload = [
            "business_source_id" => $validated['business_source_id'],
            "delivery_service_id" => $validated['delivery_service_id'],
            "tracking_number" => $validated['tracking_number'],
            "payment_method" => $validated['payment_method'],
            "payment_method_title" => $validated['payment_method_title'],
            "paid_amount" => $validated['paid_amount'],
        ];

        Order::where("id", $validated['order_id'])->update($orderPayload);

        $Invoice->update(['status' => $validated['status']]);

        return $Invoice;
    }

    public function destroy(Invoice $Invoice)
    {
        $Invoice->delete();

        return response()->json();
    }
}
