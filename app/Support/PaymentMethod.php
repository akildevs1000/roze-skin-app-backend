<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Matching free-text payment methods.
 *
 * orders.payment_method has been written several ways over the years - the
 * website sends the gateway's own slug, staff type their own wording, and the
 * casing varies:
 *
 *     cod (701)   COD (405)   tabby_installments (130)   Tabby (2)
 *     stripe_cc   tamara-gateway-checkout   Bank  Transfer   Complimentary
 *
 * The filter dropdown offers the payment_modes table's names ("Tamara",
 * "Tabby", "COD"), and the query compared those to the stored text exactly.
 * "Tamara" never equalled "tamara-gateway-checkout", so that filter returned
 * nothing at all; "COD" matched 405 of 1,106 orders and silently hid the rest.
 *
 * Rather than rewrite the orders - which would lose what the gateway actually
 * reported - the chosen label is expanded to every stored spelling that means
 * the same thing.
 */
class PaymentMethod
{
    /**
     * Stored spellings folded to one label. Matches the reporting logic in
     * MonthlySalesReportController so a filter and a report agree.
     */
    private const ALIASES = [
        'cod'                     => 'COD',
        'cash on delivery'        => 'COD',
        'stripe_cc'               => 'Stripe',
        'stripe'                  => 'Stripe',
        'tabby_installments'      => 'Tabby',
        'tabby'                   => 'Tabby',
        'customer requested tabby payment' => 'Tabby',
        'tamara-gateway-checkout' => 'Tamara',
        'tamara-gateway'          => 'Tamara',
        'tamara'                  => 'Tamara',
        'bank transfer'           => 'Bank Transfer',
        'bank/courier'            => 'Bank Transfer',
        'complimentary'           => 'Complimentary',
    ];

    /** The label a stored value belongs to. */
    public static function canonical($raw): string
    {
        $key = strtolower(trim(preg_replace('/\s+/', ' ', (string) $raw)));

        return self::ALIASES[$key] ?? ($key === '' ? '' : ucfirst($key));
    }

    /**
     * Every spelling present in the orders table that means the same thing as
     * the chosen one, so the filter can use whereIn.
     *
     * Always includes the selection itself: a value that is in use but not in
     * the alias table above still filters on exactly what the user picked,
     * which is no worse than the old behaviour.
     *
     * @return array<int,string>
     */
    public static function variants($selected): array
    {
        $selected = (string) $selected;

        if ($selected === '') {
            return [];
        }

        $want = self::canonical($selected);

        $stored = DB::table('orders')
            ->select('payment_method')
            ->distinct()
            ->whereNotNull('payment_method')
            ->pluck('payment_method');

        $matches = $stored
            ->filter(fn ($v) => self::canonical($v) === $want)
            ->values()
            ->all();

        if (! in_array($selected, $matches, true)) {
            $matches[] = $selected;
        }

        return $matches;
    }
}
