<?php

namespace App\Services;

use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\StockLedger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for stock movements.
 *
 * Every increase/decrease of stock MUST go through move(), which:
 *   1. updates the product's bucket balance (sellable / non_sellable),
 *   2. writes an immutable stock_ledgers row (rule #12),
 *   3. blocks negative stock unless the caller is explicitly allowed (rule: prevent
 *      negative stock unless admin has special permission).
 */
class StockService
{
    /** Permission name that lets a user push a bucket below zero. */
    const NEGATIVE_STOCK_PERMISSION = 'inventory_allow_negative_stock';

    /**
     * Apply a signed stock movement and record it in the ledger.
     *
     * @param  int     $productId
     * @param  int     $quantity      Signed: positive = stock in, negative = stock out.
     * @param  string  $movementType  One of the StockLedger::* constants.
     * @param  string  $bucket        'sellable' | 'non_sellable'.
     * @param  array   $opts          source_type, source_id, reference, reason, allow_negative, user_id
     * @return StockLedger
     *
     * @throws \RuntimeException when the movement would create negative stock and is not allowed.
     */
    public function move($productId, $quantity, $movementType, $bucket = StockLedger::BUCKET_SELLABLE, array $opts = [])
    {
        $quantity = (int) $quantity;
        $column   = $bucket === StockLedger::BUCKET_NON_SELLABLE ? 'non_sellable_qty' : 'sellable_qty';

        return DB::transaction(function () use ($productId, $quantity, $movementType, $bucket, $column, $opts) {
            // Lock the stock row for the duration of the transaction to avoid race conditions.
            $stock = InventoryStock::where('product_id', $productId)->lockForUpdate()->first();

            if (! $stock) {
                $stock = InventoryStock::create(['product_id' => $productId]);
            }

            $newQty = (int) $stock->{$column} + $quantity;

            if ($newQty < 0 && empty($opts['allow_negative'])) {
                $available = (int) $stock->{$column};
                throw new \RuntimeException(
                    "Insufficient stock for product #{$productId}. Available: {$available}, requested: " . abs($quantity) . "."
                );
            }

            $stock->{$column} = $newQty;
            $stock->save();

            return StockLedger::create([
                'product_id'    => $productId,
                'movement_type' => $movementType,
                'bucket'        => $bucket,
                'quantity'      => $quantity,
                'balance_after' => $newQty,
                'source_type'   => $opts['source_type'] ?? null,
                'source_id'     => $opts['source_id'] ?? null,
                'reference'     => $opts['reference'] ?? null,
                'reason'        => $opts['reason'] ?? null,
                'customer_name' => $opts['customer_name'] ?? null,
                'user_id'       => $opts['user_id'] ?? $this->currentUserId(),
            ]);
        });
    }

    /** Convenience: increase a bucket. */
    public function increase($productId, $quantity, $movementType, $bucket = StockLedger::BUCKET_SELLABLE, array $opts = [])
    {
        return $this->move($productId, abs((int) $quantity), $movementType, $bucket, $opts);
    }

    /** Convenience: decrease a bucket (always from sellable unless overridden). */
    public function decrease($productId, $quantity, $movementType, $bucket = StockLedger::BUCKET_SELLABLE, array $opts = [])
    {
        return $this->move($productId, -abs((int) $quantity), $movementType, $bucket, $opts);
    }

    /**
     * Resolve whether the current request is allowed to drive stock negative.
     * Master users always may; otherwise the user needs the special permission.
     * Tokens are honoured even on routes that aren't behind auth middleware.
     */
    public function userCanAllowNegative()
    {
        $user = Auth::guard('sanctum')->user() ?? Auth::user();

        if (! $user) {
            return false;
        }

        if (! empty($user->master)) {
            return true;
        }

        // permissions is a collection of Permission models with a `name` column.
        try {
            return $user->permissions->contains('name', self::NEGATIVE_STOCK_PERMISSION);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function currentUserId()
    {
        $user = Auth::guard('sanctum')->user() ?? Auth::user();

        return $user ? $user->id : null;
    }
}
