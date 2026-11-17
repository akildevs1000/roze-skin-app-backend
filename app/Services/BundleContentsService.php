<?php

namespace App\Services;

use App\Models\WpProductMap;
use Illuminate\Support\Facades\DB;

/**
 * What each FIXED bundle contains - Glow Routine, Combo Pack, Trio Pack and
 * the rest - for listing under its line on an invoice and on the shipping
 * label.
 *
 * The pick-your-own bundles (Any 3, Any 4) already carry their contents on the
 * order line as bundle_note, because what is inside them differs per order. A
 * fixed bundle holds the same things every time, so nothing was ever written
 * down against the order and both the invoice and the label printed a single
 * line with no way for a packer to know what goes in the box.
 *
 * Read from the stock mapping rather than a list of its own: that mapping has
 * to be right or stock does not deduct, so this cannot quietly drift out of
 * step with what is actually shipped.
 */
class BundleContentsService
{
    /**
     * wp_product_id => [['name' => ..., 'qty' => ...], ...]
     *
     * Only maps with two or more components: a one-to-one map is an ordinary
     * product, not a bundle, and listing its single component under itself
     * would just repeat the line above.
     *
     * @return array<string,array<int,array{name:string,qty:int}>>
     */
    public function all(): array
    {
        $maps = WpProductMap::with('items.inventoryItem:id,name')
            ->where('skip_stock', false)
            ->get();

        $names = $this->catalogNames($maps);

        $result = [];
        foreach ($maps as $map) {
            if ($map->items->count() < 2) {
                continue;
            }

            $components = [];
            foreach ($map->items as $item) {
                $name = $names[(int) $item->inventory_item_id]
                    ?? optional($item->inventoryItem)->name;

                if (! empty($name)) {
                    $components[] = ['name' => $name, 'qty' => (int) $item->qty];
                }
            }

            if ($components) {
                $result[(string) $map->wp_product_id] = $components;
            }
        }

        return $result;
    }

    /**
     * The full product name to print for each inventory item.
     *
     * Stock items are named for the people picking them - "Rice cleanser",
     * "7 Days glow serum". On an invoice a customer keeps, those read as
     * something other than what was bought, and they did not match the
     * Any 3 / Any 4 lines on the same page, which print the full catalog name.
     *
     * An item earns its name from the map that points at it alone: a map with
     * one component IS that product, so whatever that product is called is
     * what the item should be called.
     *
     * The app's own catalog is preferred over the name stored on the map,
     * because the map keeps a snapshot from whenever it was last saved and
     * those drift - it still held "Botonical" for the hair serum and an old
     * title for the body lotion. Where there is no catalog row the stored name
     * is used, and failing that the stock name, so every component is always
     * named something.
     *
     * @param  \Illuminate\Support\Collection<int,WpProductMap>  $maps
     * @return array<int,string>
     */
    private function catalogNames($maps): array
    {
        $singles = $maps->filter(fn ($m) => $m->items->count() === 1);
        if ($singles->isEmpty()) {
            return [];
        }

        $catalog = DB::table('products')
            ->whereIn('id', $singles->pluck('wp_product_id')
                ->filter(fn ($id) => ctype_digit((string) $id))
                ->all())
            ->pluck('name', 'id');

        $names = [];
        foreach ($singles as $map) {
            $itemId = (int) $map->items->first()->inventory_item_id;

            $fromCatalog = $catalog[(int) $map->wp_product_id] ?? null;
            $name = $fromCatalog ?: $map->wp_name;

            if (empty($name)) {
                continue;
            }

            // A catalog name beats a stored one, and anything beats nothing.
            if (! isset($names[$itemId]) || $fromCatalog) {
                $names[$itemId] = trim($name);
            }
        }

        return $names;
    }
}
