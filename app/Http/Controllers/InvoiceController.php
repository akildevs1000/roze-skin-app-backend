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

        $customer_id = request('customer_id');

        $from = request('from') ? request('from') . " 00:00:00" : date("Y-m-d 00:00:00");
        $to = request('to') ? request('to') . " 23:59:59" : date("Y-m-d 23:59:59");

        $dates = [$from, $to];

        $perPage = min((int) request('per_page', 15), 100); // Limit max results per page

        return Invoice::with([
            'customer' => function ($q) {
                $q->with(['shipping_address', 'billing_address']);
            },
            'payments' => function ($q) {
                $q->with(['payment_mode']);
            },
            'order',
            'business_source',
            'delivery_service'
        ])
            ->when($customer_id, function ($q) use ($customer_id) {
                $q->where('customer_id', $customer_id);
            })
            ->when(count($dates) > 0, function ($q) use ($dates) {
                $q->whereBetween('created_at', $dates);
            })
            ->when($status, function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->when($search, function ($q) use ($search) {

                $order_id = Order::where("order_id", $search)->value("id");

                if ($order_id) {
                    $q->where('order_id', $order_id);
                } else {
                    // check it value is less then 1000 and remove all the zeros
                    $q->where('id', env("WILD_CARD") ?? 'ILIKE', '%' . ltrim($search, '0') . '%')
                        ->orWhereHas('order', function ($q2) use ($search) {
                            $q2->where('tracking_number', $search);
                        });
                }
            })
            ->orderByDesc('id')
            ->paginate($perPage);
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
