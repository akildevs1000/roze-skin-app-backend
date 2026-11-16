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

    /**
     * What each fixed bundle contains, for printing underneath its line on an
     * invoice: wp_product_id => [['name' => ..., 'qty' => ...], ...].
     *
     * The pick-your-own bundles (Any 3, Any 4) already carry their contents on
     * the order line as bundle_note, because what is inside them differs per
     * order. A fixed bundle - Glow Routine, Combo Pack, Trio Pack and the rest
     * - contains the same things every time, so nothing was ever written down
     * and the invoice printed one line with no way for a packer to know what
     * goes in the box.
     *
     * It is read from the stock mapping rather than a second list of its own.
     * That mapping already has to be right or stock does not deduct, so this
     * cannot quietly drift out of step with what is actually shipped.
     *
     * Only maps with two or more components are returned: a one-to-one map is
     * an ordinary product, not a bundle, and listing its single component
     * under itself would just repeat the line above.
     */
    public function contents()
    {
        $maps = WpProductMap::with('items.inventoryItem:id,name')
            ->where('skip_stock', false)
            ->get();

        $result = [];
        foreach ($maps as $map) {
            if ($map->items->count() < 2) {
                continue;
            }

            $result[(string) $map->wp_product_id] = $map->items
                ->map(fn ($i) => [
                    'name' => optional($i->inventoryItem)->name,
                    'qty'  => (int) $i->qty,
                ])
                ->filter(fn ($i) => ! empty($i['name']))
                ->values();
        }

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
