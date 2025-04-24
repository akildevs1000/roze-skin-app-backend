<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\ValidationRequest;
use App\Models\Customer;

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
        return Customer::with(["orders", "billing_address", "shipping_address"])
            ->withCount("orders")
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
            "customer" => [
                "first_name" => $model->first_name ?? null,
                "last_name" => $model->last_name ?? null,
                "email" => $model->email ?? null,
                "dob" => $model->dob ?? null,
                "phone" => $model->phone ?? null,
                "whatsapp" => $model->whatsapp ?? null,
            ],
            "shipping_address" => $model->shipping_address ?? null,
            "billing_address" => $model->billing_address ?? null,
        ];
    }
}
