<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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

    public function store(Request $request)
    {
        $validated = $request->validate([
            "name" => "required|min:5|max:255",
            "description" => "required|min:5|max:255",
            "price" => "numeric|required",
            "product_category_id" => "required",
            "item_number" => "required|min:5|max:100",
            "qty" => "required|numeric|min:1|max:1000",
            "image" => "nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048",
            "purchase_price" => "required"
        ]);

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $filename = time() . '_' . Str::slug(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $image->getClientOriginalExtension();

            $image->move(public_path('products'), $filename); // Save in public/products
            $validated['image'] = 'products/' . $filename;
        }

        $product = Product::create($validated);

        return $product;
    }

    public function updateProduct(Request $request)
    {
        $validated = $request->validate([
            "id" => "required|exists:products,id",
            "name" => "required|min:5|max:255",
            "description" => "required|min:5|max:255",
            "price" => "numeric|required",
            "product_category_id" => "required",
            "item_number" => "required|min:5|max:100",
            "qty" => "required|numeric|min:1|max:1000",
            "image" => "nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048",
            "purchase_price" => "required"
        ]);

        $product = Product::findOrFail($request->id);

        if ($request->hasFile('image')) {
            // Delete old image if it exists
            if ($product->image && File::exists(public_path($product->image))) {
                File::delete(public_path($product->image));
            }

            // Save new image
            $image = $request->file('image');
            $filename = time() . '_' . Str::slug(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('products'), $filename);
            $validated['image'] = 'products/' . $filename;
        }

        $product->update($validated);

        return $product->fresh();
    }

    public function destroy(Product $product)
    {
        // Delete the image file if it exists
        if ($product->image && File::exists(public_path($product->image))) {
            File::delete(public_path($product->image));
        }

        $product->delete();

        return response()->noContent();
    }
}
