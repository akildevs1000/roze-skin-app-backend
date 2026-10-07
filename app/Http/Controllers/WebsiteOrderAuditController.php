<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Website orders that never reached this app.
 *
 * The store pushes each order to the API as it is placed. That is one attempt
 * over the network between two servers, so it can fail for reasons that have
 * nothing to do with the order - a deploy, a timeout, an API change. In early
 * October a validation change rejected every website order for two days and it
 * went unnoticed, because the push treated a rejection as success.
 *
 * This reads both sides and reports the difference, so the question "did every
 * order arrive?" can be answered at any time instead of by hand.
 *
 * Strictly read-only. It touches the WordPress database on the same server
 * through a separate connection that is only ever used for SELECT.
 */
class WebsiteOrderAuditController extends Controller
{
    /**
     * The statuses the store actually pushes. Anything else - an unpaid order
     * someone abandoned at checkout, a cancellation - was never meant to arrive,
     * so reporting it as missing would be noise that hides the real thing.
     */
    private const SENT = ["wc-processing", "wc-completed", "wc-on-hold"];

    /** Never sent, but worth showing separately so the absence is explained. */
    private const NOT_SENT = ["wc-pending", "wc-failed", "wc-cancelled", "wc-checkout-draft"];

    public function index(Request $request)
    {
        $isDay = fn ($d) => $d && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);

        $to   = $isDay($request->query("to")) ? $request->query("to") : date("Y-m-d");
        $from = $isDay($request->query("from"))
            ? $request->query("from")
            : date("Y-m-d", strtotime($to . " -30 day"));

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $start = $from . " 00:00:00";
        $end   = date("Y-m-d 00:00:00", strtotime($to . " +1 day"));

        try {
            $website = $this->websiteOrders($start, $end);
        } catch (\Throwable $e) {
            // The WordPress database lives on the live server; a local copy of
            // this app has no route to it. Say so plainly rather than returning
            // an empty list, which would read as "nothing is missing".
            return response()->json([
                "error"   => "Could not read the website database.",
                "detail"  => $e->getMessage(),
                "from"    => $from,
                "to"      => $to,
            ], 503);
        }

        $known = $this->knownReferences();

        $missing = [];
        $matched = 0;

        foreach ($website["sent"] as $row) {
            if ($this->isPresent((string) $row["order_id"], $known)) {
                $matched++;
                continue;
            }

            $row["candidates"] = $this->candidatesFor((string) $row["order_id"], $known);
            $missing[] = $row;
        }

        // Only the ones actually absent. A cancelled order that is sitting in
        // this app anyway - cancelled here first, or keyed in by hand - would
        // otherwise be listed under "never sent", which reads as a second
        // problem when there is none.
        $notSent = array_values(array_filter(
            $website["not_sent"],
            fn ($row) => ! $this->isPresent((string) $row["order_id"], $known)
        ));

        return response()->json([
            "from"    => $from,
            "to"      => $to,
            "summary" => [
                "website_orders" => count($website["sent"]) + count($website["not_sent"]),
                "sent_by_store"  => count($website["sent"]),
                "received"       => $matched,
                "missing"        => count($missing),
                "not_sent"       => count($notSent),
            ],
            "missing"  => $missing,
            "not_sent" => $notSent,
        ]);
    }

    /**
     * Orders as the store holds them, split by whether the store would have
     * tried to send them at all.
     */
    private function websiteOrders($start, $end)
    {
        $prefix = config("database.connections.wordpress.wp_prefix", "wp_");
        $db     = DB::connection("wordpress");

        $rows = $db->table("{$prefix}wc_orders as o")
            ->leftJoin("{$prefix}wc_order_addresses as a", function ($join) {
                $join->on("a.order_id", "=", "o.id")->where("a.address_type", "=", "billing");
            })
            ->where("o.type", "shop_order")
            ->where("o.date_created_gmt", ">=", $start)
            ->where("o.date_created_gmt", "<", $end)
            ->whereIn("o.status", array_merge(self::SENT, self::NOT_SENT))
            ->orderBy("o.id")
            ->get([
                "o.id",
                "o.status",
                "o.date_created_gmt",
                "o.payment_method_title",
                "o.total_amount",
                "o.billing_email",
                "a.first_name",
                "a.last_name",
                "a.phone",
            ]);

        // One query for the line counts rather than one per order: an order with
        // no items at all is how a test record gives itself away, and that is
        // worth showing beside a real one.
        $ids = $rows->pluck("id")->all();

        $itemCounts = [];

        if ($ids) {
            $itemCounts = $db->table("{$prefix}woocommerce_order_items")
                ->whereIn("order_id", $ids)
                ->where("order_item_type", "line_item")
                ->groupBy("order_id")
                ->selectRaw("order_id, COUNT(*) as n")
                ->pluck("n", "order_id")
                ->all();
        }

        $sent    = [];
        $notSent = [];

        foreach ($rows as $r) {
            $items = (int) ($itemCounts[$r->id] ?? 0);
            $total = (float) $r->total_amount;

            $name = trim(($r->first_name ?? "") . " " . ($r->last_name ?? ""));

            $row = [
                "order_id" => (string) $r->id,
                "date"     => $r->date_created_gmt,
                "status"   => str_replace("wc-", "", $r->status),
                "payment"  => trim((string) $r->payment_method_title),
                "total"    => round($total, 2),
                "customer" => $name,
                "phone"    => (string) ($r->phone ?? ""),
                "email"    => (string) ($r->billing_email ?? ""),
                "items"    => $items,
                // Nothing bought and nothing to pay: a test record, not a sale
                // the shop lost. Flagged rather than hidden, so the judgement
                // stays with whoever is reading the list.
                "is_empty" => $items === 0 && $total <= 0,
            ];

            if (in_array($r->status, self::SENT, true)) {
                $sent[] = $row;
            } else {
                $notSent[] = $row;
            }
        }

        return ["sent" => $sent, "not_sent" => $notSent];
    }

    /**
     * Every reference this app holds, indexed for matching.
     *
     * Not limited to the date range: an order placed at the end of one month
     * and keyed in on the first of the next is still present, and reporting it
     * as missing would send someone looking for an order that is already here.
     */
    private function knownReferences()
    {
        $rows = DB::table("orders as o")
            ->leftJoin("customers as c", "c.id", "=", "o.customer_id")
            ->whereNotNull("o.order_id")
            ->get([
                "o.order_id",
                "o.total",
                "o.order_status",
                "o.created_at",
                "c.first_name",
                "c.last_name",
                "c.phone",
            ]);

        $exact   = [];
        $endings = [];

        foreach ($rows as $r) {
            $ref = (string) $r->order_id;

            if ($ref === "") {
                continue;
            }

            $exact[$ref] = true;

            // An order typed in by hand during an outage sometimes carries a
            // prefix - 1000060994 is website order 60994. Those tails are only
            // ever a *suggestion*: a five-digit tail collides easily, and four
            // of them turned out to be unrelated phone orders belonging to
            // different customers. Recorded as candidates for a human to judge,
            // never as proof the order arrived.
            $len = strlen($ref);

            for ($take = 4; $take < $len; $take++) {
                $endings[$take][substr($ref, -$take)][] = [
                    "order_id" => $ref,
                    "total"    => round((float) $r->total, 2),
                    "status"   => (string) $r->order_status,
                    "customer" => trim(($r->first_name ?? "") . " " . ($r->last_name ?? "")),
                    "phone"    => (string) ($r->phone ?? ""),
                    "date"     => (string) $r->created_at,
                ];
            }
        }

        return ["exact" => $exact, "endings" => $endings];
    }

    /**
     * Present means the reference is here, exactly. Nothing looser: a false
     * "received" is the worst answer this can give, because it retires the
     * question and the sale is never entered.
     */
    private function isPresent($orderId, array $known)
    {
        return isset($known["exact"][$orderId]);
    }

    /** App orders whose reference merely ends with this one. Needs a human. */
    private function candidatesFor($orderId, array $known)
    {
        return $known["endings"][strlen($orderId)][$orderId] ?? [];
    }
}
