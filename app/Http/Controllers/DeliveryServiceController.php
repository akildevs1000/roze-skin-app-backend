<?php

namespace App\Http\Controllers;

use App\Models\DeliveryService;
use Illuminate\Http\Request;

class DeliveryServiceController extends Controller
{
    public function dropDown()
    {
        return DeliveryService::get();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return DeliveryService::where("name", "LIKE", "%" . request("search", null) . "%")
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

        return DeliveryService::create($validated);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\DeliveryService  $DeliveryService
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, DeliveryService $DeliveryService)
    {
        $validated = $request->validate([
            "name" => "required|max:255",
            "description" => "required",
        ]);

        return $DeliveryService->update($validated);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\DeliveryService  $DeliveryService
     * @return \Illuminate\Http\Response
     */
    public function destroy(DeliveryService $DeliveryService)
    {
        $DeliveryService->delete();

        return response()->json();
    }
}
