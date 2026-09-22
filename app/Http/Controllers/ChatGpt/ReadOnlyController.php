<?php

namespace App\Http\Controllers\ChatGpt;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChatGpt\CustomerResource;
use App\Http\Resources\ChatGpt\InvoiceResource;
use App\Http\Resources\ChatGpt\OrderResource;
use App\Http\Resources\ChatGpt\OrderSummaryResource;
use App\Models\BusinessSource;
use App\Models\Customer;
use App\Models\DeliveryService;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Read-only surface for the ChatGPT integration.
 *
 * Every route reaching this controller has already passed through
 * auth:sanctum -> abilities:read -> readonly -> db.readonly, so there is no
 * write path from here even by accident. Responses go through the
 * App\Http\Resources\ChatGpt\* resources, which whitelist fields and mask
 * customer contact details unless the token also carries `read-pii`.
 */
class ReadOnlyController extends Controller
{
    private const MAX_PER_PAGE = 100;

    /* ------------------------------------------------------------------ */
    /* Orders                                                              */
    /* ------------------------------------------------------------------ */

    public function orders(Request $request)
    {
        $request->validate([
            'from'             => 'nullable|date',
            'to'               => 'nullable|date',
            'status'           => 'nullable|string|max:50',
            'delivery_status'  => 'nullable|string|max:50',
            'channel'          => 'nullable|string|max:50',
            'customer_id'      => 'nullable|integer',
            'has_tracking'     => 'nullable|boolean',
            'per_page'         => 'nullable|integer|min:1|max:' . self::MAX_PER_PAGE,
        ]);

        $query = $this->orderListQuery();

        if ($request->filled('from')) {
            $query->where('order_date', '>=', $request->date('from')->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('order_date', '<=', $request->date('to')->endOfDay());
        }

        if ($request->filled('status')) {
            $query->where('order_status', $request->status);
        }

        if ($request->filled('delivery_status')) {
            $query->where('delivery_status', $request->delivery_status);
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', (int) $request->input('customer_id'));
        }

        // tracking_number is a bigint, and 0 is what the app stores for
        // "not dispatched yet" — not null.
        if ($request->filled('has_tracking')) {
            $request->boolean('has_tracking')
                ? $query->where('tracking_number', '>', 0)
                : $query->where(fn ($q) => $q->whereNull('tracking_number')->orWhere('tracking_number', 0));
        }

        return OrderSummaryResource::collection(
            $query->orderByDesc('order_date')
                ->paginate($this->perPage($request))
                ->withQueryString()
        );
    }

    public function order(string $reference)
    {
        $order = $this->resolveOrder($reference);

        if (! $order) {
            return $this->notFound("No order matches '{$reference}'.");
        }

        $order->load(['customer', 'delivery_service', 'business_source', 'invoice', 'payments', 'legacyItems']);

        return new OrderResource($order);
    }

    /* ------------------------------------------------------------------ */
    /* Customers                                                           */
    /* ------------------------------------------------------------------ */

    public function customers(Request $request)
    {
        $request->validate([
            'query'    => 'nullable|string|max:120',
            'per_page' => 'nullable|integer|min:1|max:' . self::MAX_PER_PAGE,
        ]);

        $query = Customer::query();

        if ($request->filled('query')) {
            $this->applyCustomerSearch($query, $request->input('query'));
        }

        return CustomerResource::collection(
            $query->orderByDesc('id')
                ->paginate($this->perPage($request))
                ->withQueryString()
        );
    }

    public function customer(int $id)
    {
        $customer = Customer::find($id);

        if (! $customer) {
            return $this->notFound("No customer with id {$id}.");
        }

        return new CustomerResource($customer);
    }

    public function customerOrders(Request $request, int $id)
    {
        if (! Customer::whereKey($id)->exists()) {
            return $this->notFound("No customer with id {$id}.");
        }

        return OrderSummaryResource::collection(
            $this->orderListQuery()
                ->where('customer_id', $id)
                ->orderByDesc('order_date')
                ->paginate($this->perPage($request))
                ->withQueryString()
        );
    }

    /* ------------------------------------------------------------------ */
    /* Invoices                                                            */
    /* ------------------------------------------------------------------ */

    public function invoices(Request $request)
    {
        $request->validate([
            'status'   => 'nullable|string|max:30',
            'from'     => 'nullable|date',
            'to'       => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:' . self::MAX_PER_PAGE,
        ]);

        $query = Invoice::query()->with('customer');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from')->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to')->endOfDay());
        }

        return InvoiceResource::collection(
            $query->orderByDesc('id')
                ->paginate($this->perPage($request))
                ->withQueryString()
        );
    }

    public function invoice(string $reference)
    {
        $id = $this->referenceToId($reference, 'INV');

        $invoice = $id ? Invoice::with(['customer', 'order'])->find($id) : null;

        if (! $invoice) {
            return $this->notFound("No invoice matches '{$reference}'.");
        }

        return new InvoiceResource($invoice);
    }

    /* ------------------------------------------------------------------ */
    /* Search + reports                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * One box that takes a name, phone, email, order number, tracking number
     * or INV-/ORD- reference and returns whatever it matches.
     */
    public function search(Request $request)
    {
        $request->validate(['query' => 'required|string|min:2|max:120']);

        $term = trim($request->input('query'));

        $customers = Customer::query()
            ->tap(fn ($q) => $this->applyCustomerSearch($q, $term))
            ->limit(10)
            ->get();

        $orders = $this->orderListQuery()
            ->where(function ($q) use ($term) {
                // tracking_number is a bigint — cast before a text match.
                $q->whereRaw('tracking_number::text ilike ?', ["%{$term}%"])
                    ->orWhere('username', 'ilike', "%{$term}%")
                    ->orWhere('email', 'ilike', "%{$term}%");

                if (ctype_digit($term)) {
                    $q->orWhere('order_id', $term);
                }

                if ($id = $this->referenceToId($term, 'ORD')) {
                    $q->orWhere('id', $id);
                }
            })
            ->orderByDesc('order_date')
            ->limit(10)
            ->get();

        // A customer match is usually what was meant — surface their orders too.
        if ($customers->isNotEmpty() && $orders->isEmpty()) {
            $orders = $this->orderListQuery()
                ->whereIn('customer_id', $customers->pluck('id'))
                ->orderByDesc('order_date')
                ->limit(10)
                ->get();
        }

        $invoiceId = $this->referenceToId($term, 'INV');

        return response()->json([
            'query'     => $term,
            'customers' => CustomerResource::collection($customers),
            'orders'    => OrderSummaryResource::collection($orders),
            'invoices'  => InvoiceResource::collection(
                $invoiceId ? Invoice::with('customer')->where('id', $invoiceId)->get() : collect()
            ),
        ]);
    }

    /**
     * "How many orders did this customer make this year and how much did
     * they spend?" — answered server-side so the numbers are right.
     */
    public function customerSpend(Request $request, int $id)
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date',
        ]);

        $customer = Customer::find($id);

        if (! $customer) {
            return $this->notFound("No customer with id {$id}.");
        }

        $query = Order::query()->setEagerLoads([])->where('customer_id', $id);

        if ($request->filled('from')) {
            $query->where('order_date', '>=', $request->date('from')->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('order_date', '<=', $request->date('to')->endOfDay());
        }

        $totals = (clone $query)
            ->selectRaw('count(*) as orders_count, coalesce(sum(total), 0) as total_value, coalesce(sum(paid_amount), 0) as total_paid')
            ->first();

        return response()->json([
            'customer'     => new CustomerResource($customer),
            'period'       => ['from' => $request->input('from'), 'to' => $request->input('to')],
            'currency'     => 'AED',
            'orders_count' => (int) $totals->orders_count,
            'total_value'  => round((float) $totals->total_value, 2),
            'total_paid'   => round((float) $totals->total_paid, 2),
            'by_status'    => (clone $query)
                ->select('order_status', DB::raw('count(*) as count'), DB::raw('coalesce(sum(total), 0) as value'))
                ->groupBy('order_status')
                ->get()
                ->map(fn ($r) => [
                    'status' => $r->order_status,
                    'count'  => (int) $r->count,
                    'value'  => round((float) $r->value, 2),
                ]),
            'first_order'  => (clone $query)->min('order_date'),
            'last_order'   => (clone $query)->max('order_date'),
        ]);
    }

    /**
     * Aggregate order counts/values over a period, optionally grouped.
     */
    public function ordersSummary(Request $request)
    {
        $request->validate([
            'from'     => 'nullable|date',
            'to'       => 'nullable|date',
            'group_by' => 'nullable|in:status,channel,delivery_service,business_source,day,month',
        ]);

        $query = Order::query()->setEagerLoads([]);

        if ($request->filled('from')) {
            $query->where('order_date', '>=', $request->date('from')->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('order_date', '<=', $request->date('to')->endOfDay());
        }

        $group = $request->input('group_by', 'status');

        // Whitelisted above by the `in:` rule, so this is never user SQL.
        $expression = [
            'status'           => 'order_status',
            'channel'          => 'channel',
            'delivery_service' => 'delivery_service_id',
            'business_source'  => 'business_source_id',
            'day'              => "to_char(order_date, 'YYYY-MM-DD')",
            'month'            => "to_char(order_date, 'YYYY-MM')",
        ][$group];

        $rows = (clone $query)
            ->select([
                DB::raw($expression . ' as bucket'),
                DB::raw('count(*) as count'),
                DB::raw('coalesce(sum(total), 0) as value'),
                DB::raw('coalesce(sum(paid_amount), 0) as paid'),
            ])
            ->groupBy(DB::raw($expression))
            ->orderByDesc(DB::raw('count(*)'))
            ->get();

        // Foreign keys are meaningless in an answer — swap them for names.
        $labels = match ($group) {
            'delivery_service' => DeliveryService::pluck('name', 'id'),
            'business_source'  => BusinessSource::pluck('name', 'id'),
            default            => collect(),
        };

        return response()->json([
            'period'   => ['from' => $request->input('from'), 'to' => $request->input('to')],
            'group_by' => $group,
            'currency' => 'AED',
            'totals'   => [
                'orders_count' => (clone $query)->count(),
                'total_value'  => round((float) (clone $query)->sum('total'), 2),
                'total_paid'   => round((float) (clone $query)->sum('paid_amount'), 2),
            ],
            'buckets'  => $rows->map(fn ($r) => [
                'bucket' => $labels->get($r->bucket, $r->bucket),
                'count'  => (int) $r->count,
                'value'  => round((float) $r->value, 2),
                'paid'   => round((float) $r->paid, 2),
            ]),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Accepts an ORD-000123 reference, a marketplace order number (61310) or
     * an internal row id — in that order of preference.
     */
    private function resolveOrder(string $reference): ?Order
    {
        if ($id = $this->referenceToId($reference, 'ORD')) {
            return Order::find($id);
        }

        if (! ctype_digit($reference)) {
            return null;
        }

        return Order::where('order_id', $reference)->orderByDesc('id')->first()
            ?: Order::find((int) $reference);
    }

    /**
     * "INV-006014" -> 6014. Returns null when the string is not a reference
     * with that prefix.
     */
    private function referenceToId(string $reference, string $prefix): ?int
    {
        if (! preg_match('/^' . $prefix . '-?(\d+)$/i', trim($reference), $m)) {
            return null;
        }

        return (int) ltrim($m[1], '0') ?: null;
    }

    private function applyCustomerSearch($query, string $term): void
    {
        $query->where(function ($q) use ($term) {
            $q->where('first_name', 'ilike', "%{$term}%")
                ->orWhere('last_name', 'ilike', "%{$term}%")
                ->orWhere('email', 'ilike', "%{$term}%")
                ->orWhere('phone', 'ilike', "%{$term}%")
                ->orWhere('whatsapp', 'ilike', "%{$term}%")
                ->orWhereRaw("(first_name || ' ' || last_name) ilike ?", ["%{$term}%"]);
        });
    }

    /**
     * Order lists never need the address book. The Order model eager-loads
     * addresses globally via $with, so drop them here — otherwise every list
     * of 50 orders drags four extra address queries and a pile of PII along
     * with it.
     */
    private function orderListQuery()
    {
        return Order::query()
            ->setEagerLoads([])
            ->with(['customer', 'delivery_service', 'business_source']);
    }

    private function perPage(Request $request): int
    {
        $requested = (int) $request->input('per_page', 50);

        return max(1, min($requested ?: 50, self::MAX_PER_PAGE));
    }

    private function notFound(string $message)
    {
        return response()->json(['error' => $message], 404);
    }
}
