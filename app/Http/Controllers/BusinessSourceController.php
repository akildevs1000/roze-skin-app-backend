<?php
namespace App\Http\Controllers;

use App\Models\BusinessSource;
use Illuminate\Http\Request;

class BusinessSourceController extends Controller
{
    public function dropDown()
    {
        return BusinessSource::get();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return BusinessSource::where("name", "LIKE", "%" . request("search", null) . "%")
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

        return BusinessSource::create($validated);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\BusinessSource  $businessSource
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, BusinessSource $businessSource)
    {
        $validated = $request->validate([
            "name"        => "required|max:255",
            "description" => "required",
        ]);

        return $businessSource->update($validated);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\BusinessSource  $businessSource
     * @return \Illuminate\Http\Response
     */
    public function destroy(BusinessSource $businessSource)
    {
        $businessSource->delete();

        return response()->json();
    }

    public function report()
    {
        $business_source_id = request('business_source_id');
        $from               = request('from') ? request('from') . " 00:00:00" : date("Y-m-d 00:00:00");
        $to                 = request('to') ? request('to') . " 23:59:59" : date("Y-m-d 23:59:59");
        $dates              = [$from, $to];

        return BusinessSource::query()
            ->when($business_source_id, function ($query, $id) {
                $query->whereHas("orders", fn($q) => $q->where('business_source_id', $id));
            })
            ->whereHas("orders", fn($q) => $q->whereBetween('order_date', $dates))
            ->withCount([
                'orders as orders_count' => function ($q) use ($business_source_id, $dates) {
                    $q->whereBetween('order_date', $dates);
                    if ($business_source_id) {
                        $q->where('business_source_id', $business_source_id);
                    }
                },
            ])
            ->withSum([
                'orders as orders_sum_total' => function ($q) use ($business_source_id, $dates) {
                    $q->whereBetween('order_date', $dates);
                    if ($business_source_id) {
                        $q->where('business_source_id', $business_source_id);
                    }
                },
            ], 'total')
            ->get();
    }

}
