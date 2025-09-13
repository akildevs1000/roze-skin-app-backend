<?php
namespace App\Http\Controllers;

use App\Http\Requests\Customer\ValidationRequest;
use App\Models\Customer;

class CustomerController extends Controller
{
    public function dropDown()
    {
        $allItem = ["id" => null, "customer_with_phone" => "All Customers"];

        $data = Customer::whereHas("orders")->orderBy("id", "desc")->get()->toArray();

        return [$allItem, ...$data];
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $order_status        = request('order_status');
        $customer_id         = request('customer_id');
        $business_source_id  = request('business_source_id');
        $delivery_service_id = request('delivery_service_id');
        $payment_method      = request('payment_method');

        $from = request('from') ? request('from') . " 00:00:00" : date("Y-m-d 00:00:00");
        $to   = request('to') ? request('to') . " 23:59:59" : date("Y-m-d 23:59:59");

        $dates = [$from, $to];

        $perPage = request('per_page', 15); // default 15 per page

        return Customer::query()
            ->orderByDesc('id')

        // ✅ Fix: Use id instead of customer_id
            ->when($customer_id, function ($q) use ($customer_id) {
                $q->where('id', $customer_id);
            })

        // ✅ Apply filters on orders relation
            ->when($order_status, function ($q) use ($order_status) {
                $q->whereHas("orders", fn($q) => $q->where('order_status', $order_status));
            })
            ->when($business_source_id, function ($q) use ($business_source_id) {
                $q->whereHas("orders", fn($q) => $q->where('business_source_id', $business_source_id));
            })
            ->when($delivery_service_id, function ($q) use ($delivery_service_id) {
                $q->whereHas("orders", fn($q) => $q->where('delivery_service_id', $delivery_service_id));
            })
            ->when($payment_method, function ($q) use ($payment_method) {
                $q->whereHas("orders", fn($q) => $q->where('payment_method', $payment_method));
            })

        // ✅ Always filter orders by date
            ->whereHas("orders", fn($q) => $q->whereBetween('order_date', $dates))

        // ✅ Eager load relations
            ->with([
                "orders" => function ($q) use ($dates, $order_status, $business_source_id, $delivery_service_id, $payment_method) {
                    $q->whereBetween('order_date', $dates);

                    if ($order_status) {
                        $q->where('order_status', $order_status);
                    }
                    if ($business_source_id) {
                        $q->where('business_source_id', $business_source_id);
                    }
                    if ($delivery_service_id) {
                        $q->where('delivery_service_id', $delivery_service_id);
                    }
                    if ($payment_method) {
                        $q->where('payment_method', $payment_method);
                    }
                },
                "billing_address",
                "shipping_address",
            ])

        // ✅ Aggregates
            ->withCount("orders")
            ->withSum("orders", "total")

            ->paginate($perPage);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(ValidationRequest $request)
    {
        // update if email or phone is already registered
        $customer = Customer::storeOrUpdateCustomerWithAddresses($request->validated());
        return $customer;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Customer  $Customer
     * @return \Illuminate\Http\Response
     */

    public function update(ValidationRequest $request, Customer $customer)
    {
        $customer = Customer::storeOrUpdateCustomerWithAddresses($request->validated());
        return $customer;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Customer  $Customer
     * @return \Illuminate\Http\Response
     */
    public function destroy(Customer $Customer)
    {
        $Customer->delete();

        return response()->json();
    }

    public function getCustomer()
    {
        $model = Customer::with("shipping_address", "billing_address")->where("phone", request("phone") ?? null)->first() ?? null;

        return [
            "customer"         => [
                "first_name" => $model->first_name ?? null,
                "last_name"  => $model->last_name ?? null,
                "email"      => $model->email ?? null,
                "dob"        => $model->dob ?? null,
                "phone"      => $model->phone ?? null,
                "whatsapp"   => $model->whatsapp ?? null,
            ],
            "shipping_address" => $model->shipping_address ?? null,
            "billing_address"  => $model->billing_address ?? null,
        ];
    }

    public function report()
    {
        $customer_id = request('customer_id');

        $from = request('from') ? request('from') . " 00:00:00" : date("Y-m-d 00:00:00");
        $to   = request('to') ? request('to') . " 23:59:59" : date("Y-m-d 23:59:59");

        $dates = [$from, $to];

        return Customer::query()
            ->orderByDesc('id')

        // ✅ Fix: Use id instead of customer_id
            ->when($customer_id, function ($q) use ($customer_id) {
                $q->where('id', $customer_id);
            })

        // ✅ Always filter orders by date
            ->whereHas("orders", fn($q) => $q->whereBetween('order_date', $dates))

        // ✅ Eager load relations
            ->with([
                "orders" => function ($q) use ($dates) {
                    $q->whereBetween('order_date', $dates);

                },
                "billing_address",
                "shipping_address",
            ])

        // ✅ Aggregates
            ->withCount("orders")
            ->withSum("orders", "total")

            ->get();
    }

    public function repeatedCustomerReport()
    {
        $customer_id = request('customer_id');

        $from = request('from') ? request('from') . " 00:00:00" : date("Y-m-d 00:00:00");
        $to   = request('to') ? request('to') . " 23:59:59" : date("Y-m-d 23:59:59");

        $dates = [$from, $to];

        return Customer::query()

        // ✅ Fix: Use id instead of customer_id
            ->when($customer_id, function ($q) use ($customer_id) {
                $q->where('id', $customer_id);
            })

        // ✅ Always filter orders by date
            ->whereHas('orders', function ($q) use ($dates) {
                $q->whereBetween('order_date', $dates);
            }, '>', 1)

        // ✅ Eager load relations
            ->with([
                "orders" => function ($q) use ($dates) {
                    $q->whereBetween('order_date', $dates);

                },
                "billing_address",
                "shipping_address",
            ])

        // ✅ Aggregates
            ->withCount([
                'orders as orders_count' => function ($q) use ($customer_id, $dates) {
                    $q->whereBetween('order_date', $dates);
                    if ($customer_id) {
                        $q->where('customer_id', $customer_id);
                    }
                },
            ])
            ->withSum([
                'orders as orders_sum_total' => function ($q) use ($customer_id, $dates) {
                    $q->whereBetween('order_date', $dates);
                    if ($customer_id) {
                        $q->where('customer_id', $customer_id);
                    }
                },
            ], 'total')

            ->get();
    }
}
