<?php
namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InsertOrderItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:insert-order-items';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $orders = $this->getItemsWithOrderId();

        if (! count($orders)) {
            $this->info('No Order Found');
            return Command::SUCCESS;
        }

        // Filter out items for orders that are already inserted
        $orders = $orders->filter(function ($item) {
            return ! DB::table('order_items')
                ->where('order_id', $item['order_id'])
                ->where('product_id', $item['product_id'])
                ->exists();
        });

        if ($orders->isEmpty()) {
            $this->info('All orders are already processed.');
            return Command::SUCCESS;
        }

        // Insert in chunks of 100
        $orders->chunk(100)->each(function ($chunk) {
            $insertData = $chunk->map(fn($item) => [
                'order_id'   => $item['order_id'],
                'product_id' => $item['product_id'] ?? 0, // default to 0 if null
                'quantity'   => $item['quantity'],
                'rate'       => $item['rate'],
                'order_date' => $item['order_date'],
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray();

            OrderItem::insert($insertData);
        });

        $this->info('All order items inserted successfully.');
        return Command::SUCCESS;

    }

    public function getItemsWithOrderId()
    {
        $orders = Order::query()
            ->whereNotIn("order_status", ["cancelled"])
            ->without('customer')
            ->latest('id')
            ->get(['order_id', 'order_date', 'items']);

        if ($orders->isEmpty()) {
            return collect();
        }

// Get all unique, normalized item descriptions
        $itemNames = $orders->flatMap(fn($order) =>
            collect($order->items)->pluck('item')
        )->map(function ($item) {
            // Normalize spaces
            $item = preg_replace('/\s+/', ' ', $item);

            // Normalize dash variations to "-"
            $item = str_replace(['–', '—'], '-', $item);

            // Clean spacing around "-" and "|"
            $item = preg_replace('/\s*-\s*/', ' - ', $item);
            $item = preg_replace('/\s*\|\s*/', ' | ', $item);

            return trim($item);
        })->unique();

        // Fetch existing product IDs mapped by description
        $products = Product::whereIn('description', $itemNames)
            ->pluck('id', 'description')
            ->toArray();

        $items = $orders->flatMap(function ($order) use (&$products) {
            return collect($order->items)->map(function ($item) use ($order, &$products) {
                $description = $item['item'];
                return [
                    'quantity'   => $item['quantity'] ?? 0,
                    'rate'       => $item['rate'] ?? 0,
                    'order_id'   => $order->order_id,
                    'order_date' => date("Y-m-d H:i:s", strtotime($order->order_date)) ?? now(),
                    'product_id' => $products[$description] ?? 0,
                ];
            });
        });

        // $this->info(json_encode($items, JSON_PRETTY_PRINT));
        // die;

        return $items;
    }

}
