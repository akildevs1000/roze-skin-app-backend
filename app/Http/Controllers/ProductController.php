<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function dropDown()
    {
        return Product::get();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Product::with("product_category")->where("name", "LIKE", "%" . request("search", null) . "%")
            ->orderByDesc("id")
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
            "name" => "required|min:5|max:255",
            "description" => "required|min:5|max:255",
            "price" => "numeric|required",
            "product_category_id" => "required",
            "item_number" => "required|min:5|max:100",
            "qty" => "required|numeric|min:1|max:1000",
        ]);

        return Product::create($validated);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Product  $Product
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Product $Product)
    {
        $validated = $request->validate([
            "name" => "required|min:5|max:255",
            "description" => "required|min:5|max:255",
            "price" => "numeric|required",
            "product_category_id" => "required",
            "item_number" => "required|min:1|max:100",
            "qty" => "required|numeric|min:1|max:1000",
        ]);

        return $Product->update($validated);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Product  $Product
     * @return \Illuminate\Http\Response
     */
    public function destroy(Product $Product)
    {
        $Product->delete();

        return response()->json();
    }
}
