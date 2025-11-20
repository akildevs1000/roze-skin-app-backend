<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
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
            ->paginate(1000);
    }

    public function store(Request $request)
    {
        $tracerId = 'Store TRC-' . bin2hex(random_bytes(8));

        info("Process Start with $tracerId");

        $ids = json_decode($request->inventory_item_ids ?? json_encode([]), true);

        $validated = $request->validate([
            "name"                => "required|min:5|max:255",
            "description"         => "required|min:5|max:255",
            "price"               => "numeric|required",
            "product_category_id" => "required",
            "image"               => "nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048",
            'qty'                 => 'required|integer|min:0',
            "item_number"         => "required|unique:products,item_number",
        ]);

        if ($request->hasFile('image')) {
            $image    = $request->file('image');
            $filename = time() . '_' . Str::slug(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $image->getClientOriginalExtension();

            $image->move(public_path('products'), $filename); // Save in public/products
            $validated['image'] = 'products/' . $filename;
        }

        $validated['purchase_price'] = $validated['price'];

        $product = Product::create($validated);

        $mappings = [];

        foreach ($ids as $id) {

            $mappings[] = [
                "product_id"        => $product->id,
                "inventory_item_id" => $id,
            ];
        }

        if (count($ids)) {
            info(json_encode($mappings, JSON_PRETTY_PRINT));

            ProductMapping::insert($mappings);

            info("Mapping inserted for product {$product->id}");

            info("Store Process End with $tracerId");
        }

        return $product;
    }

    public function updateProduct(Request $request)
    {
        $tracerId = 'Update TRC-' . bin2hex(random_bytes(8));

        info("Process Start with $tracerId");

        $ids = json_decode($request->inventory_item_ids ?? json_encode([]), true);

        $validated = $request->validate([
            "name"                => "required|min:5|max:255",
            "description"         => "required|min:5|max:255",
            "price"               => "numeric|required",
            "product_category_id" => "required",
            "image"               => "nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048",
            'qty'                 => 'required|integer|min:0',
        ]);

        $product = Product::where("item_number", $request->item_number)->first();

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

        $validated['purchase_price'] = $validated['price'];

        $product->update($validated);

        $mappings = [];

        foreach ($ids as $id) {

            $mappings[] = [
                "product_id"        => $product->id,
                "inventory_item_id" => $id,
            ];
        }

        if (count($ids)) {
            ProductMapping::where("product_id", $product->id)->delete();
            info("Delete existing mapping for product {$product->id}");
            info("Request id: " . $request->item_number);

            info("New mapping inserted for product {$product->id}");

            ProductMapping::insert($mappings);

            info(json_encode($mappings, JSON_PRETTY_PRINT));

            info("Update Process End with $tracerId");
        }

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
        $from  = request('from') ? request('from') . " 00:00:00" : date("Y-m-d 00:00:00");
        $to    = request('to') ? request('to') . " 23:59:59" : date("Y-m-d 23:59:59");

        // item_number => name
        $productNames = Product::when(request()->filled('product_id'), function ($query) {
            $query->where('id', request('product_id'));
        })
            ->pluck('name', 'item_number')
            ->toArray();

        $orders = Order::whereBetween('order_date', [$from, $to])
            ->orderByDesc('id')
            ->get();

        $counts = [];

        foreach ($orders as $order) {

            $items = $order->items; // items already cast to array in model

            if (!is_array($items)) continue;

            foreach ($items as $item) {

                // product_id in items JSON = item_number in product table
                $productId = (string) $item["product_id"];

                if (!isset($productNames[$productId])) {
                    continue;
                }

                $productName = $productNames[$productId];

                if (!isset($counts[$productId])) {
                    $counts[$productId] = [
                        'qty'           => 0,
                        'item_number'   => $productId,
                        'item_name'     => $productName,
                        'display_image' => null // will fill later
                    ];
                }

                // Add quantity
                $counts[$productId]['qty'] += (int) $item["quantity"];
            }
        }

        // Attach display image
        foreach ($counts as $itemNumber => &$data) {

            $product = Product::where('item_number', $itemNumber)->first();

            $data['display_image'] = $product->display_image ?? null;
        }

        return array_values($counts);
    }
}
