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
        // OrderItem::truncate();

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
                'name'   => $item['name'],
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
        // ->whereDate("order_date", ">=", date("2025-10-01"))
            ->whereNotIn("order_status", ["cancelled"])
            ->without('customer')
            ->latest('id')
            ->whereHas('invoice', function ($q) {
                $q->whereBetween('created_at', [date("Y-m-d 00:00:00"), date("Y-m-d 23:59:59")]);
            })
            ->get(['order_id', 'order_date', 'items']);

        if ($orders->isEmpty()) {
            return collect();
        }

        $products = Product::pluck('id', 'item_number')->toArray();

        $arr = [];

        $tempItems = [];

        foreach ($orders as $order) {
            $items = $order->items;
            foreach ($items as $key => $item) {

                $product_id = $item['product_id'] ?? 0;

                $arr[] = [
                    'name'       => $product_id . " | " . $item['item'],
                    'quantity'   => $item['quantity'] ?? 0,
                    'rate'       => $item['rate'] ?? 0,
                    'order_id'   => $order->order_id,
                    'order_date' => date("Y-m-d H:i:s", strtotime($order->order_date)) ?? now(),
                    'product_id' => $products[$product_id] ?? 0,
                ];

                // if (! isset($products[$product_id])) {
                //     $tempItem                             = $item;
                //     $tempItem["order_id"]                 = $order->order_id;
                //     $tempItem["order_date"]               = date("Y-m-d H:i:s", strtotime($order->order_date)) ?? now();
                //     $tempItems[$tempItem["order_date"]][] = $tempItem;
                // }
            }
        }
        return collect($arr);
    }

}
