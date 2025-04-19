<?php

namespace App\Http\Controllers;

use App\Models\PaymentMode;
use Illuminate\Http\Request;

class PaymentModeController extends Controller
{
    public function dropDown()
    {
        return PaymentMode::get();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return PaymentMode::where("name", "LIKE", "%" . request("search", null) . "%")
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
            "name" => "required|max:255",
            "description" => "required",
        ]);

        return PaymentMode::create($validated);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\PaymentMode  $PaymentMode
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PaymentMode $PaymentMode)
    {
        $validated = $request->validate([
            "name" => "required|max:255",
            "description" => "required",
        ]);

        return $PaymentMode->update($validated);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\PaymentMode  $PaymentMode
     * @return \Illuminate\Http\Response
     */
    public function destroy(PaymentMode $PaymentMode)
    {
        $PaymentMode->delete();

        return response()->json();
    }
}
