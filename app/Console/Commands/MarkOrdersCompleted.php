<?php
namespace App\Console\Commands;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MarkOrdersCompleted extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:mark-completed {date}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark orders as completed if they have an invoice';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get start and end of current month
        $startOfMonth = $this->argument("date") . ' 00:00:00';
        $endOfMonth   = $this->argument("date") . ' 23:59:59';

        $this->info($startOfMonth);
        $this->info($endOfMonth);

        $orders = Order::whereHas('invoice')
            ->whereBetween('order_date', [$startOfMonth, $endOfMonth])
            ->get();

        $count = 0;

        foreach ($orders as $order) {
            if ($order->order_status == "processing" || $order->order_status == "pending") {
                $order->order_status = 'completed';
                $order->save();
                $count++;
            } 
        }

        $this->info("$count orders marked as completed.");
    }
}
