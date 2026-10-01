<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Refund;
use App\Services\StockSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RefundController extends Controller
{
    /** Refunds for a month, newest first. */
    public function index(Request $request)
    {
        $month = $request->query("month");

        $query = Refund::query()->orderByDesc("id");

        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $start = $month . "-01 00:00:00";
            $end   = date("Y-m-d 00:00:00", strtotime($month . "-01 +1 month"));
            $query->where("created_at", ">=", $start)->where("created_at", "<", $end);
        }

        $rows = $query->get();

        return response()->json([
            "data"  => $rows,
            "total" => round((float) $rows->sum("refund_value"), 2),
        ]);
    }

    /**
     * Look up an order so the refund form can fill itself in. Accepts the
     * store's order reference, which is what staff actually read off a parcel.
     */
    public function lookup(Request $request)
    {
        $ref = $request->query("order_id");

        $order = Order::with("customer")
            ->where("order_id", $ref)
            ->orderByDesc("id")
            ->first();

        if (! $order) {
            $order = Order::with("customer")->where("id", $ref)->first();
        }

        if (! $order) {
            return response()->json(["message" => "No order found with that reference."], 404);
        }

        $customer = $order->customer;

        return response()->json([
            "order_pk"      => $order->id,
            "order_id"      => $order->order_id,
            "order_status"  => $order->order_status,
            "order_value"   => (float) $order->total,
            "customer_id"   => $customer ? $customer->id : 0,
            "customer_name" => $customer ? trim($customer->first_name . " " . $customer->last_name) : null,
            "phone"         => $customer ? ($customer->phone ?: $customer->whatsapp) : null,
            "payment_method" => $order->payment_method,
            "already_refunded" => round((float) Refund::where("order_id", $order->id)->sum("refund_value"), 2),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            "order_id"     => "required|integer",
            "refund_value" => "required|numeric|min:0.01",
            "reason"       => "nullable|string|max:255",
            "restock"      => "nullable|boolean",
        ]);

        $order = Order::with("customer")->find($validated["order_id"]);

        if (! $order) {
            return response()->json(["message" => "Order not found."], 404);
        }

        $customer = $order->customer;
        $invoice  = $order->invoice;

        $restockWanted = ! empty($validated["restock"]);
        $restocked     = 0;

        $refund = DB::transaction(function () use ($order, $customer, $invoice, $validated, $restockWanted, &$restocked) {
            if ($restockWanted && $invoice) {
                try {
                    // Same path the Return action uses, so units land back in
                    // sellable stock and the ledger keeps one consistent story.
                    // It is idempotent: an order already restocked stays put.
                    $restocked = app(StockSyncService::class)->returnForInvoice(
                        $invoice,
                        $validated["reason"] ?? "Refund"
                    );
                } catch (\Throwable $e) {
                    // A stock problem must not swallow the refund record itself.
                    Log::error("Refund restock failed for order {$order->id}: " . $e->getMessage());
                }
            }

            return Refund::create([
                "order_id"        => $order->id,
                "invoice_id"      => $invoice ? $invoice->id : null,
                "customer_id"     => $customer ? $customer->id : 0,
                "customer_name"   => $customer ? trim($customer->first_name . " " . $customer->last_name) : null,
                "phone"           => $customer ? ($customer->phone ?: $customer->whatsapp) : null,
                "order_value"     => $order->total,
                "refund_value"    => $validated["refund_value"],
                "reason"          => $validated["reason"] ?? null,
                "restocked"       => $restockWanted && $restocked > 0,
                "restocked_units" => $restocked,
            ]);
        });

        $message = "Refund recorded.";

        if ($restockWanted && ! $invoice) {
            $message .= " Stock was not changed: this order has no invoice, so there are no stock movements to reverse.";
        } elseif ($restockWanted && $restocked === 0) {
            $message .= " Stock was not changed: nothing was deducted for this order, or it has already been put back.";
        } elseif ($restocked > 0) {
            $message .= " {$restocked} stock movement(s) returned to sellable.";
        }

        return response()->json(["success" => true, "message" => $message, "refund" => $refund]);
    }

    public function destroy($id)
    {
        $refund = Refund::find($id);

        if (! $refund) {
            return response()->json(["message" => "Refund not found."], 404);
        }

        // Deleting the record deliberately does not re-deduct stock: the units
        // are physically wherever the warehouse put them, and guessing would be
        // worse than leaving the correction to a stock adjustment.
        $refund->delete();

        return response()->json(["success" => true, "message" => "Refund removed. Stock was not changed."]);
    }
}
