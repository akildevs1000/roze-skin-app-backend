<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\ValidationRequest;
use App\Http\Requests\Customer\ValidationUpdateRequest;
use App\Models\Customer;
use Illuminate\Support\Facades\Request;

class CustomerController extends Controller
{
    public function dropDown()
    {
        return Customer::get();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Customer::with(["billing_address", "shipping_address"])
            ->where("first_name", "LIKE", "%" . request("search", null) . "%")
            ->orderByDesc("id")
            ->paginate(request("per_page"));
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

    public function update(ValidationUpdateRequest $request, Customer $customer)
    {
        $validated = $request->validated();

        $customer->update([
            "first_name" => $validated['first_name'],
            "last_name" => $validated['last_name'],
            "email" => $validated['email'] ?? null,
            "phone" => $validated['phone'],
        ]);

        if (isset($validated['shipping_address'])) {
            Customer::storeOrUpdateShippingAddress($customer->id, $validated['shipping_address']);
        }

        if (isset($validated['billing_address'])) {
            Customer::storeOrUpdateBillingAddress($customer->id, $validated['billing_address']);
        }

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
            "customer" => [
                "first_name" => $model->first_name ?? null,
                "last_name" => $model->last_name ?? null,
                "email" => $model->email ?? null,
                "phone" => $model->phone ?? null,
            ],
            "shipping_address" => $model->shipping_address ?? null,
            "billing_address" => $model->billing_address ?? null,
        ];
    }
}
