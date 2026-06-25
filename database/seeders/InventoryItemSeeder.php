<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use Illuminate\Database\Seeder;

class InventoryItemSeeder extends Seeder
{
    /**
     * Default stockable inventory items.
     * php artisan db:seed --class=InventoryItemSeeder
     */
    public function run()
    {
        $items = [
            'Rice cleanser',
            'Rice moisturizer',
            'Roze Coconut Milk Keratin Shampoo',
            'Acne cleanser',
            'Body Lotion',
            'Kids Moisturizer with Sunscreen',
            'Lip balm',
            '7 Days glow serum',
            'Black Gold Luxury Body Wash',
            'Hair Serum',
        ];

        foreach ($items as $i => $name) {
            InventoryItem::firstOrCreate(
                ['name' => $name],
                ['sku' => sprintf('ITM-%03d', $i + 1), 'status' => 'active', 'unit_cost' => 0]
            );
        }
    }
}
