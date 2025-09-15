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
            "name"        => "required|max:255",
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
            "name"        => "required|max:255",
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

    public function report()
    {
        // ->whereNotIn("order_status", ["cancelled"])

        $payment_method = request('payment_method');
        $from           = request('from') ? request('from') . " 00:00:00" : date("Y-m-d 00:00:00");
        $to             = request('to') ? request('to') . " 23:59:59" : date("Y-m-d 23:59:59");
        $dates          = [$from, $to];

        return PaymentMode::query()
            
              ->when($payment_method, function ($query, $id) {
                $query->whereHas("orders", fn($q) => $q->where('payment_method', $id));
            })
            ->whereHas("orders", fn($q) => $q->whereBetween('order_date', $dates))
            ->withCount([
                'orders as orders_count' => function ($q) use ($payment_method, $dates) {
                    $q->whereBetween('order_date', $dates);
                    $q->whereNotIn("order_status", ["cancelled"]);
                    if ($payment_method) {
                        $q->where('payment_method', $payment_method);
                    }
                },
            ])
            ->withSum([
                'orders as orders_sum_total' => function ($q) use ($payment_method, $dates) {
                    $q->whereBetween('order_date', $dates);
                    $q->whereNotIn("order_status", ["cancelled"]);
                    if ($payment_method) {
                        $q->where('payment_method', $payment_method);
                    }
                },
            ], 'total')
            ->get();
    }
}
