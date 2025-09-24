<?php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InventoryItemController extends Controller
{
    public function dropDown()
    {
        return InventoryItem::get();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return InventoryItem::where("name", "LIKE", "%" . request("search", null) . "%")
            ->orderByDesc("id")
            ->paginate(request("per_page"));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            "name"                => "required|min:5|max:255",
            "description"         => "required|min:5|max:255",
            "item_number"         => "required|min:5|max:100",
            "image"               => "nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048",
        ]);

        if ($request->hasFile('image')) {
            $image    = $request->file('image');
            $filename = time() . '_' . Str::slug(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $image->getClientOriginalExtension();

            $image->move(public_path('inventory_items'), $filename);
            $validated['image'] = 'inventory_items/' . $filename;
        }

        $inventory = InventoryItem::create($validated);

        return $inventory;
    }

    public function updateProduct(Request $request)
    {
        $validated = $request->validate([
            "id"                  => "required|exists:inventory_items,id",
            "name"                => "required|min:5|max:255",
            "description"         => "required|min:5|max:255",
            "item_number"         => [
                "required",
                "min:5",
                "max:100",
                Rule::unique('inventory_items', 'item_number')->ignore($request->id),
            ],
            "image"               => "nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048",
        ]);

        $inventory = InventoryItem::findOrFail($request->id);

        if ($request->hasFile('image')) {
            // Delete old image if it exists
            if ($inventory->image && File::exists(public_path($inventory->image))) {
                File::delete(public_path($inventory->image));
            }

            // Save new image
            $image    = $request->file('image');
            $filename = time() . '_' . Str::slug(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('inventory_items'), $filename);
            $validated['image'] = 'inventory_items/' . $filename;
        }

        $inventory->update($validated);

        return $inventory->fresh();
    }

    public function destroy(InventoryItem $inventory)
    {
        // Delete the image file if it exists
        if ($inventory->image && File::exists(public_path($inventory->image))) {
            File::delete(public_path($inventory->image));
        }

        $inventory->delete();

        return response()->noContent();
    }

    public function report()
    {
        $inventory_id = request('inventory_id');
        $from       = request('from') ? request('from') . " 00:00:00" : date("Y-m-d 00:00:00");
        $to         = request('to') ? request('to') . " 23:59:59" : date("Y-m-d 23:59:59");
        $dates      = [$from, $to];

        $orderIds = Order::whereBetween("order_date", $dates)->whereNotIn("order_status", ["cancelled"])->pluck("order_id");

        return InventoryItem::query()
            ->when($inventory_id, function ($query, $id) {
                $query->whereHas("order_items", fn($q) => $q->where('inventory_id', $id));
            })
            ->whereHas("order_items", function ($q) use ($orderIds) {
                $q->whereIn('order_id', $orderIds);
            })
            ->withCount([
                'order_items as orders_count' => function ($q) use ($inventory_id, $dates) {
                    $q->whereBetween('order_date', $dates);
                    if ($inventory_id) {
                        $q->where('inventory_id', $inventory_id);
                    }
                },
            ])
            ->withSum([
                'order_items as orders_sum_total' => function ($q) use ($inventory_id, $dates) {
                    $q->whereBetween('order_date', $dates);
                    if ($inventory_id) {
                        $q->where('inventory_id', $inventory_id);
                    }
                },
            ], 'rate')
            ->get();
    }
}
