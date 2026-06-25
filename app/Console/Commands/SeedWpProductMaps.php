<?php

namespace App\Console\Commands;

use App\Models\InventoryItem;
use App\Models\WpProductMap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Seeds wp_product_maps from the known WordPress catalogue.
 *
 * Matching is by keyword on the order line's product name (names vary by
 * "- Single" / "- Pack of 2" / hyphen vs en-dash, so exact match is unsafe).
 * Rules are ordered most-specific first; packs win over the simple products
 * whose names they contain. Already-mapped products are left untouched unless
 * --force is given.
 *
 *   php artisan stock:seed-wp-map            # dry run, shows the plan
 *   php artisan stock:seed-wp-map --apply    # write the mappings
 *   php artisan stock:seed-wp-map --apply --force   # also overwrite existing
 */
class SeedWpProductMaps extends Command
{
    protected $signature = 'stock:seed-wp-map {--apply : Persist the mappings (otherwise dry-run)} {--force : Overwrite products that are already mapped}';

    protected $description = 'Seed WordPress product -> inventory item mappings from the known catalogue';

    /**
     * Ordered rules. First whose needle is a substring of the (normalised) name wins.
     * Value is either 'VIRTUAL' or a list of [inventory item name, qty-per-unit].
     */
    private function rules(): array
    {
        $shampoo = 'Roze Coconut Milk Keratin Shampoo';

        return [
            ['any 3 for 99', 'VIRTUAL'],

            // Packs / kits — most specific first.
            ['mega', [['Rice cleanser', 1], ['Rice moisturizer', 1], [$shampoo, 1], ['Acne cleanser', 1], ['Body Lotion', 1], ['Kids Moisturizer with Sunscreen', 1]]],
            ['family pack', [['Rice cleanser', 1], ['Rice moisturizer', 1], [$shampoo, 1], ['Acne cleanser', 1], ['Body Lotion', 1]]],
            ['glow pack', [['Rice cleanser', 1], ['Rice moisturizer', 1], [$shampoo, 1], ['Body Lotion', 1]]],
            ['trio pack', [['Acne cleanser', 1], ['Rice moisturizer', 1], [$shampoo, 1]]],
            ['bundle pack', [['Rice cleanser', 1], ['Rice moisturizer', 1], [$shampoo, 1]]],
            ['skin ritual', [['Rice cleanser', 1], ['Rice moisturizer', 1], ['Body Lotion', 1]]],
            ['combo pack', [['Rice cleanser', 1], ['Rice moisturizer', 1]]],
            ['skincare set', [['Rice cleanser', 1], ['Rice moisturizer', 1]]],

            // Simple products.
            ['rice facial cleanser', [['Rice cleanser', 1]]],
            ['rice moisturizing cream', [['Rice moisturizer', 1]]],
            ['7 day glow serum', [['7 Days glow serum', 1]]],
            ['coconut milk keratin shampoo', [[$shampoo, 1]]],
            ['acne control cleanser', [['Acne cleanser', 1]]],
            ['velvet glow brightening', [['Body Lotion', 1]]],
            ['black gold luxury body wash', [['Black Gold Luxury Body Wash', 1]]],
            ['spf 50 pa+++ moisturizer', [['Kids Moisturizer with Sunscreen', 1]]],
            ['hair growth serum', [['Hair Serum', 1]]],
            ['lip balm', [['Lip balm', 1]]],
        ];
    }

    public function handle(): int
    {
        $apply = $this->option('apply');
        $force = $this->option('force');

        // inventory item name (normalised) -> id
        $itemsByName = [];
        foreach (InventoryItem::all() as $it) {
            $itemsByName[$this->norm($it->name)] = $it->id;
        }

        // distinct WordPress products from order line items
        $products = []; // wp_product_id => name
        foreach (DB::table('orders')->select('items')->get() as $row) {
            $items = json_decode($row->items ?? '', true);
            if (! is_array($items)) {
                continue;
            }
            foreach ($items as $line) {
                $pid = isset($line['product_id']) ? (string) $line['product_id'] : '';
                if ($pid === '' || isset($products[$pid])) {
                    continue;
                }
                $products[$pid] = $line['item'] ?? '';
            }
        }

        $existing = WpProductMap::pluck('wp_product_id')->all();
        $existing = array_flip($existing);

        $rules = $this->rules();
        $planned = 0; $virtual = 0; $skipped = 0; $unmatched = [];

        foreach ($products as $pid => $name) {
            if (isset($existing[$pid]) && ! $force) {
                $skipped++;
                continue;
            }

            $norm = $this->norm($name);
            $match = null;
            foreach ($rules as [$needle, $target]) {
                if (strpos($norm, $needle) !== false) {
                    $match = $target;
                    break;
                }
            }

            if ($match === null) {
                $unmatched[] = "$pid  $name";
                continue;
            }

            if ($match === 'VIRTUAL') {
                $this->line(sprintf('  <comment>VIRTUAL</comment>  %-8s %s', $pid, $this->short($name)));
                $virtual++;
                if ($apply) {
                    $this->writeMap($pid, $name, true, []);
                }
                continue;
            }

            // Resolve component names -> ids; double the qty for "Pack of 2".
            $multiplier = strpos($norm, 'pack of 2') !== false ? 2 : 1;
            $components = [];
            $missing = [];
            foreach ($match as [$itemName, $qty]) {
                $id = $itemsByName[$this->norm($itemName)] ?? null;
                if (! $id) {
                    $missing[] = $itemName;
                } else {
                    $components[] = ['inventory_item_id' => $id, 'qty' => $qty * $multiplier];
                }
            }

            if ($missing) {
                $unmatched[] = "$pid  $name  (missing inventory item: " . implode(', ', $missing) . ')';
                continue;
            }

            $names = implode(', ', array_map(fn ($m) => $m[0] . ($multiplier > 1 ? "×$multiplier" : ''), $match));
            $this->line(sprintf('  <info>MAP</info>      %-8s %s  ->  %s', $pid, $this->short($name), $names));
            $planned++;
            if ($apply) {
                $this->writeMap($pid, $name, false, $components);
            }
        }

        $this->newLine();
        if ($unmatched) {
            $this->warn('Unmatched (left for manual mapping in the UI):');
            foreach ($unmatched as $u) {
                $this->line('  ' . $u);
            }
            $this->newLine();
        }

        $verb = $apply ? 'Wrote' : 'Would write';
        $this->info("$verb: $planned mapped, $virtual virtual.  Skipped (already mapped): $skipped.  Unmatched: " . count($unmatched) . '.');
        if (! $apply) {
            $this->comment('Dry run — re-run with --apply to persist.');
        }

        return self::SUCCESS;
    }

    private function writeMap(string $pid, string $name, bool $skip, array $components): void
    {
        $map = WpProductMap::updateOrCreate(
            ['wp_product_id' => $pid],
            ['wp_name' => $name, 'skip_stock' => $skip]
        );
        $map->items()->delete();
        foreach ($components as $c) {
            $map->items()->create($c);
        }
    }

    /** Lowercase + collapse whitespace for tolerant matching. */
    private function norm(?string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', strtolower($s ?? '')));
    }

    private function short(string $s): string
    {
        return strlen($s) > 42 ? substr($s, 0, 42) . '…' : $s;
    }
}
