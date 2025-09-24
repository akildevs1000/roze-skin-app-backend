<?php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductMapping;
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
        return Product::with(["product_category", "mappings"])->where("name", "LIKE", "%" . request("search", null) . "%")
            ->orderByDesc("id")
            ->paginate(request("per_page"));
    }

    public function store(Request $request)
    {
        $tracerId = 'Store TRC-' . bin2hex(random_bytes(8));

        info("Process Start with $tracerId");

        $ids = json_decode($request->inventory_item_ids ?? [], true);

        $validated = $request->validate([
            "name"                => "required|min:5|max:255",
            "description"         => "required|min:5|max:255",
            "price"               => "numeric|required",
            "product_category_id" => "required",
            "image"               => "nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048",
        ]);

        if ($request->hasFile('image')) {
            $image    = $request->file('image');
            $filename = time() . '_' . Str::slug(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $image->getClientOriginalExtension();

            $image->move(public_path('products'), $filename); // Save in public/products
            $validated['image'] = 'products/' . $filename;
        }

        $product = Product::create($validated);

        $mappings = [];

        foreach ($ids as $id) {

            $mappings[] = [
                "product_id"        => $product->id,
                "inventory_item_id" => $id,
            ];
        }

        info(json_encode($mappings, JSON_PRETTY_PRINT));

        ProductMapping::insert($mappings);

        info("Mapping inserted for product {$product->id}");

        info("Store Process End with $tracerId");

        return $product;
    }

    public function updateProduct(Request $request)
    {
        $tracerId = 'Update TRC-' . bin2hex(random_bytes(8));

        info("Process Start with $tracerId");

        $ids = json_decode($request->inventory_item_ids ?? [], true);

        $validated = $request->validate([
            "id"                  => "required|exists:products,id",
            "name"                => "required|min:5|max:255",
            "description"         => "required|min:5|max:255",
            "price"               => "numeric|required",
            "product_category_id" => "required",
            "image"               => "nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048",
        ]);

        $product = Product::findOrFail($request->id);

        if ($request->hasFile('image')) {
            // Delete old image if it exists
            if ($product->image && File::exists(public_path($product->image))) {
                File::delete(public_path($product->image));
            }

            // Save new image
            $image    = $request->file('image');
            $filename = time() . '_' . Str::slug(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('products'), $filename);
            $validated['image'] = 'products/' . $filename;
        }

        $product->update($validated);

        $mappings = [];

        foreach ($ids as $id) {

            $mappings[] = [
                "product_id"        => $product->id,
                "inventory_item_id" => $id,
            ];
        }

        info(json_encode($mappings, JSON_PRETTY_PRINT));

        $mappingFound = ProductMapping::where("product_id", $request->id)->first();

        if ($mappingFound) {

            info("Delete existing mapping for product {$product->id}");

            $mappingFound->delete();
        }

        info("New mapping inserted for product {$product->id}");

        ProductMapping::insert($mappings);

        info("Update Process End with $tracerId");

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

    public function report()
    {
        $product_id = request('product_id');
        $from       = request('from') ? request('from') . " 00:00:00" : date("Y-m-d 00:00:00");
        $to         = request('to') ? request('to') . " 23:59:59" : date("Y-m-d 23:59:59");
        $dates      = [$from, $to];

        $orderIds = Order::whereBetween("order_date", $dates)->whereNotIn("order_status", ["cancelled"])->pluck("order_id");

        return Product::query()
            ->when($product_id, function ($query, $id) {
                $query->whereHas("order_items", fn($q) => $q->where('product_id', $id));
            })
            ->whereHas("order_items", function ($q) use ($orderIds) {
                $q->whereIn('order_id', $orderIds);
            })
            ->withCount([
                'order_items as orders_count' => function ($q) use ($product_id, $dates) {
                    $q->whereBetween('order_date', $dates);
                    if ($product_id) {
                        $q->where('product_id', $product_id);
                    }
                },
            ])
            ->withSum([
                'order_items as orders_sum_total' => function ($q) use ($product_id, $dates) {
                    $q->whereBetween('order_date', $dates);
                    if ($product_id) {
                        $q->where('product_id', $product_id);
                    }
                },
            ], 'rate')
            ->with("product_category")
            ->get();
    }
}
