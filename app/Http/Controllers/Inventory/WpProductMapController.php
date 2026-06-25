<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\WpProductMap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin screen backing the WordPress-product -> inventory-item mapping.
 *
 * @see \App\Services\StockSyncService
 */
class WpProductMapController extends Controller
{
    /**
     * Every distinct WordPress product seen across orders, with its current
     * mapping (if any). This is the "what still needs mapping" list.
     */
    public function products()
    {
        // Read raw items JSON without the Order model's eager-loads / appends.
        $orders = DB::table('orders')->select('items')->get();

        $seen = []; // wp_product_id => ['name' => ?, 'order_lines' => int]
        foreach ($orders as $row) {
            $items = json_decode($row->items ?? '', true);
            if (! is_array($items)) {
                continue;
            }
            foreach ($items as $line) {
                $pid = isset($line['product_id']) ? (string) $line['product_id'] : '';
                if ($pid === '') {
                    continue;
                }
                if (! isset($seen[$pid])) {
                    $seen[$pid] = ['name' => null, 'order_lines' => 0];
                }
                $seen[$pid]['order_lines']++;
                if (empty($seen[$pid]['name']) && ! empty($line['item'])) {
                    $seen[$pid]['name'] = $line['item'];
                }
            }
        }

        $maps = WpProductMap::with('items.inventoryItem:id,name,sku')
            ->get()
            ->keyBy('wp_product_id');

        $result = [];
        foreach ($seen as $pid => $info) {
            /** @var WpProductMap|null $map */
            $map = $maps->get($pid);

            $result[] = [
                'wp_product_id' => $pid,
                'wp_name'       => $info['name'],
                'order_lines'   => $info['order_lines'],
                'mapped'        => $map ? ($map->skip_stock || $map->items->isNotEmpty()) : false,
                'skip_stock'    => $map ? $map->skip_stock : false,
                'items'         => $map ? $map->items->map(fn ($i) => [
                    'inventory_item_id' => $i->inventory_item_id,
                    'qty'               => $i->qty,
                    'name'              => optional($i->inventoryItem)->name,
                    'sku'               => optional($i->inventoryItem)->sku,
                ])->values() : [],
            ];
        }

        // Unmapped first, then alphabetical — so the work-to-do floats to the top.
        usort($result, function ($a, $b) {
            return [$a['mapped'], strtolower($a['wp_name'] ?? '')]
                <=> [$b['mapped'], strtolower($b['wp_name'] ?? '')];
        });

        return $result;
    }

    /** Create or replace the mapping for one WordPress product. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'wp_product_id'             => 'required',
            'wp_name'                   => 'nullable|string',
            'skip_stock'                => 'boolean',
            'items'                     => 'array',
            'items.*.inventory_item_id' => 'required|integer|exists:inventory_items,id',
            'items.*.qty'               => 'required|integer|min:1',
        ]);

        $skip = (bool) ($data['skip_stock'] ?? false);

        $map = WpProductMap::updateOrCreate(
            ['wp_product_id' => (string) $data['wp_product_id']],
            ['wp_name' => $data['wp_name'] ?? null, 'skip_stock' => $skip]
        );

        // Replace the component rows wholesale.
        $map->items()->delete();
        if (! $skip) {
            foreach ($data['items'] ?? [] as $it) {
                $map->items()->create([
                    'inventory_item_id' => $it['inventory_item_id'],
                    'qty'               => $it['qty'],
                ]);
            }
        }

        return $map->load('items.inventoryItem:id,name,sku');
    }

    /** Remove a mapping (by map id or wp_product_id). */
    public function destroy($id)
    {
        WpProductMap::where('id', $id)
            ->orWhere('wp_product_id', (string) $id)
            ->delete();

        return response()->json(['success' => true]);
    }
}
