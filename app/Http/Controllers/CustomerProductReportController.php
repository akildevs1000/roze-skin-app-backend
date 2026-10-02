<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Who bought a given product.
 *
 * Line items are stored as JSON on the order rather than in a product table,
 * and the same product has been written several ways over the years, so the
 * match is on the item text rather than an id.
 *
 * Several bundles contain a product in their name (Family Pack, Mega Pack and
 * so on), so each customer is marked according to whether they chose the
 * product on its own or only received it inside a bundle. The two are very
 * different audiences and the counts differ by roughly half.
 */
class CustomerProductReportController extends Controller
{
    public function index(Request $request)
    {
        $terms = array_filter(array_map(
            fn ($t) => trim($t),
            explode(",", (string) $request->query("terms", ""))
        ));

        if (! $terms) {
            return response()->json([
                "message" => "Give at least one product keyword, for example: shampoo, hair serum",
            ], 422);
        }

        // Each keyword becomes an ILIKE, and "pack" in the item name is what
        // separates a deliberate purchase from one that arrived in a bundle.
        $matchSql = [];
        $aloneSql = [];
        $bindings = [];

        foreach ($terms as $t) {
            $like = "%" . str_replace(" ", "%", $t) . "%";
            $matchSql[] = "item ILIKE ?";
            $aloneSql[] = "(item ILIKE ? AND item NOT ILIKE '%pack%')";
            $bindings[] = $like;
        }

        $match = implode(" OR ", $matchSql);
        $alone = implode(" OR ", $aloneSql);

        $sql = "
            WITH li AS (
                SELECT o.customer_id, o.id AS order_pk, o.created_at,
                       jsonb_array_elements(o.items::jsonb) ->> 'item' AS item
                FROM orders o
                WHERE o.items IS NOT NULL AND o.order_status <> 'cancelled'
            ),
            flags AS (
                SELECT customer_id,
                       BOOL_OR({$alone}) AS bought_alone,
                       BOOL_OR({$match}) AS bought_any,
                       COUNT(DISTINCT order_pk) AS orders,
                       MAX(created_at) AS last_order
                FROM li
                GROUP BY customer_id
            )
            SELECT TRIM(COALESCE(c.first_name,'') || ' ' || COALESCE(c.last_name,'')) AS name,
                   COALESCE(NULLIF(c.email,''), '') AS email,
                   COALESCE(NULLIF(c.phone,''), NULLIF(c.whatsapp,''), '') AS phone,
                   f.bought_alone,
                   f.orders,
                   TO_CHAR(f.last_order, 'DD.MM.YYYY') AS last_order
            FROM flags f
            JOIN customers c ON c.id = f.customer_id
            WHERE f.bought_any
            ORDER BY f.last_order DESC NULLS LAST
        ";

        // bought_alone bindings come first, then bought_any.
        $rows = DB::select($sql, array_merge($bindings, $bindings));

        $alone = 0;
        foreach ($rows as $r) {
            if ($r->bought_alone) {
                $alone++;
            }
        }

        return response()->json([
            "terms"      => $terms,
            "total"      => count($rows),
            "standalone" => $alone,
            "bundle_only" => count($rows) - $alone,
            "with_email" => count(array_filter($rows, fn ($r) => $r->email !== "")),
            "data"       => $rows,
        ]);
    }
}
