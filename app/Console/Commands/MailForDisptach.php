<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use App\Http\Controllers\InvoiceController;

class MailForDispatch extends Command
{
    // Fixed typo from 'disptach' to 'dispatch'
    protected $signature = 'mail:dispatch {tracking_number}';

    protected $description = 'Send a dispatch notification email to the customer based on tracking number';

    public function handle()
    {
        $trackingNumber = $this->argument('tracking_number');

        // 1. Fetch the order with relationships to avoid N+1 issues
        $order = Order::with('customer')
            ->where('tracking_number', $trackingNumber)
            ->first();

        // 2. Error handling if order doesn't exist
        if (!$order) {
            $this->error("Order with tracking number [{$trackingNumber}] not found.");
            return Command::FAILURE;
        }

        // 3. Improved Display/Output
        $this->components->info("Processing Dispatch Email");
        
        $this->table(
            ['Field', 'Value'],
            [
                ['Order ID', $order->order_id],
                ['Customer', $order->customer->first_name . ' ' . $order->customer->last_name],
                ['Tracking', $order->tracking_number],
            ]
        );

        // 4. Trigger Email
        try {
            // Note: It's usually better to use a Mail class or Service class 
            // than to instantiate a Controller manually.
            (new InvoiceController)->dispatchOrderEmail($order->customer, $order);
            
            $this->info("✔ Email successfully queued for {$order->customer->first_name}.");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to send email: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}