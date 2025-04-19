<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function dropDown()
    {
        return Payment::get();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Payment::where("id", "LIKE", "%" . request("search", null) . "%")
            ->paginate(request("per_page"));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_id' => 'required|integer',
            'order_id' => 'required|integer',
            'customer_id' => 'required|integer',
            'payment_mode_id' => 'required|integer',
            'payment_reference' => 'nullable|string',
            'paid_amount' => 'required|numeric',
            'status' => 'required',
        ]);

        Invoice::where("id", $validated['invoice_id'])->update(["status" => $validated['status']]);

        return Payment::create($validated);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Payment  $Payment
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Payment $Payment)
    {
        $validated = $request->validate([
            'invoice_id' => 'required|integer',
            'order_id' => 'required|integer',
            'customer_id' => 'required|integer',
            'payment_mode_id' => 'required|integer',
            'payment_reference' => 'nullable|string',
            'paid_amount' => 'required|numeric',
            'status' => 'required',
        ]);

        return $Payment->update($validated);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Payment  $Payment
     * @return \Illuminate\Http\Response
     */
    public function destroy(Payment $Payment)
    {
        $Payment->delete();

        return response()->json();
    }
}
