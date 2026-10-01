<?php

namespace App\Http\Controllers;

use App\Models\ReportRefund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Month-end sales summary.
 *
 * Read-only over the existing orders data - nothing here writes to orders,
 * invoices or stock. The only thing it stores is the hand-entered refund
 * figure for a month, which lives in its own table.
 */
class MonthlySalesReportController extends Controller
{
    /**
     * Business sources that are really sales platforms. An order placed through
     * one of these is reported under the platform rather than under whatever
     * payment method it carries, so the rows stay mutually exclusive and still
     * add up to the month's total.
     */
    private const PLATFORMS = ["amazon" => "Amazon", "noon" => "Noon", "trendyol" => "Trendyol"];

    /**
     * payment_method is free text and has been written several ways over time
     * ("cod" and "COD", "tabby_installments" and "Tabby"). Fold them together
     * for reporting only - the orders themselves are left exactly as they are.
     */
    private function normaliseMethod($method)
    {
        $key = strtolower(trim(preg_replace('/\s+/', ' ', (string) $method)));

        $map = [
            'cod'                     => 'COD',
            'cash on delivery'        => 'COD',
            'stripe_cc'               => 'Stripe',
            'stripe'                  => 'Stripe',
            'tabby_installments'      => 'Tabby',
            'tabby'                   => 'Tabby',
            'tamara-gateway-checkout' => 'Tamara',
            'tamara-gateway'          => 'Tamara',
            'tamara'                  => 'Tamara',
            'bank transfer'           => 'Bank/Courier',
            'bank/courier'            => 'Bank/Courier',
            'complimentary'           => 'Complimentary',
        ];

        if (isset($map[$key])) {
            return $map[$key];
        }

        // Never silently drop an unknown method - showing it is how we find out
        // a new one has appeared.
        return $key === '' ? 'Unspecified' : ucfirst($key);
    }

    private function emptyBucket($label)
    {
        return [
            "channel"       => $label,
            "orders"        => 0,
            "gross"         => 0.0,
            "delivered"     => 0.0,
            "rto"           => 0.0,
            "pending"       => 0.0,
            "cancelled"     => 0.0,
            "delivered_qty" => 0,
            "pending_qty"   => 0,
            "rto_qty"       => 0,
        ];
    }

    public function index(Request $request)
    {
        $month = $request->query("month");

        if (! $month || ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date("Y-m");
        }

        $start = $month . "-01 00:00:00";
        $end   = date("Y-m-d 00:00:00", strtotime($month . "-01 +1 month"));

        // "order" counts what was sold this month; "collection" counts what was
        // invoiced this month, which is the closest thing the system has to the
        // date cash arrived. The two legitimately disagree: an order placed in
        // one month and delivered in the next belongs to a different month in
        // each view.
        $basis = $request->query("basis") === "collection" ? "collection" : "order";

        $query = DB::table("orders as o")
            ->leftJoin("business_sources as bs", "bs.id", "=", "o.business_source_id")
            ->selectRaw("COALESCE(bs.name, '') as source")
            ->selectRaw("COALESCE(o.payment_method, '') as method")
            ->selectRaw("COALESCE(o.order_status, '') as status")
            ->selectRaw("COUNT(*) as orders")
            ->selectRaw("COALESCE(SUM(o.total), 0) as amount")
            ->groupBy("bs.name", "o.payment_method", "o.order_status");

        if ($basis === "collection") {
            // Orders with no invoice have collected nothing yet, so an inner
            // join is the filter: they simply do not belong to any month here.
            $query->join("invoices as i", "i.order_id", "=", "o.id")
                ->where("i.converted_to_invoice_at", ">=", $start)
                ->where("i.converted_to_invoice_at", "<", $end);
        } else {
            $query->where("o.created_at", ">=", $start)
                ->where("o.created_at", "<", $end);
        }

        $rows = $query->get();

        $channels = [];

        $summary = [
            "orders"    => 0,
            "delivered" => 0,
            "pending"   => 0,
            "cancelled" => 0,
            "rto"       => 0,
        ];

        foreach ($rows as $r) {
            $platformKey = strtolower(trim($r->source));
            $label = isset(self::PLATFORMS[$platformKey])
                ? self::PLATFORMS[$platformKey]
                : $this->normaliseMethod($r->method);

            if (! isset($channels[$label])) {
                $channels[$label] = $this->emptyBucket($label);
            }

            $count  = (int) $r->orders;
            $amount = (float) $r->amount;
            $status = strtolower($r->status);

            $channels[$label]["orders"] += $count;
            $summary["orders"] += $count;

            // A cancelled order never became a sale, so it is counted but kept
            // out of gross entirely.
            if ($status !== "cancelled") {
                $channels[$label]["gross"] += $amount;
            }

            if ($status === "completed") {
                $channels[$label]["delivered"] += $amount;
                $channels[$label]["delivered_qty"] += $count;
                $summary["delivered"] += $count;
            } elseif ($status === "processing") {
                $channels[$label]["pending"] += $amount;
                $channels[$label]["pending_qty"] += $count;
                $summary["pending"] += $count;
            } elseif ($status === "returned") {
                $channels[$label]["rto"] += $amount;
                $channels[$label]["rto_qty"] += $count;
                $summary["rto"] += $count;
            } elseif ($status === "cancelled") {
                $channels[$label]["cancelled"] += $amount;
                $summary["cancelled"] += $count;
            }
        }

        $channels = array_values($channels);

        usort($channels, function ($a, $b) {
            return $b["gross"] <=> $a["gross"];
        });

        $refundRow = ReportRefund::where("period", $month)->first();
        $refunds   = $refundRow ? (float) $refundRow->amount : 0.0;

        $totals = [
            "gross"     => round(array_sum(array_column($channels, "gross")), 2),
            "delivered" => round(array_sum(array_column($channels, "delivered")), 2),
            "rto"       => round(array_sum(array_column($channels, "rto")), 2),
            "pending"   => round(array_sum(array_column($channels, "pending")), 2),
            "cancelled" => round(array_sum(array_column($channels, "cancelled")), 2),
            "refunds"   => round($refunds, 2),
        ];

        // Money actually collected: delivered orders, less anything refunded.
        $totals["net_revenue"] = round($totals["delivered"] - $refunds, 2);

        foreach ($channels as &$c) {
            foreach (["gross", "delivered", "rto", "pending", "cancelled"] as $k) {
                $c[$k] = round($c[$k], 2);
            }
        }
        unset($c);

        return response()->json([
            "month"       => $month,
            "basis"       => $basis,
            "month_label" => date("F Y", strtotime($start)),
            "summary"     => $summary,
            "channels"    => $channels,
            "totals"      => $totals,
            "refund_note" => $refundRow ? $refundRow->note : null,
        ]);
    }

    /** Store the hand-entered refund total for a month. */
    public function saveRefund(Request $request)
    {
        $validated = $request->validate([
            "period" => "required|regex:/^\d{4}-\d{2}$/",
            "amount" => "required|numeric|min:0",
            "note"   => "nullable|string|max:255",
        ]);

        $row = ReportRefund::updateOrCreate(
            ["period" => $validated["period"]],
            ["amount" => $validated["amount"], "note" => $validated["note"] ?? null]
        );

        return response()->json(["success" => true, "refund" => $row]);
    }
}
