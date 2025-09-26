<?php
namespace App\Console\Commands;

use App\Models\OrderItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InsertOrderItemsCount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:insert-order-items-count';

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

        return;

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
        $orderItems = OrderItem::query()
            ->where("product_id", 17)
            ->whereHas("product.mappings")
            ->without('customer')
            ->with("product.mappings:product_id,inventory_item_id")
            ->with("order")
            ->latest('id')
            ->take(10)
            ->get(['order_id', 'product_id', 'quantity', "order_date"]);

        if ($orderItems->isEmpty()) {
            return collect();
        }

        $arr = [];

        foreach ($orderItems as $orderItem) {

            $mappings = $orderItem->product->mappings;
            info($mappings);
            // foreach ($mappings as $mapp) {

            //     if ($orderItem->order) {

            //     }

            //     # code...
            // }

            # code...
        }

        // $this->info(json_encode($items, JSON_PRETTY_PRINT));
        // die;

        // return $items;
    }

}
