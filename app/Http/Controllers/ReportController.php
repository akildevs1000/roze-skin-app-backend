<?php

namespace App\Http\Controllers;

use App\Models\BusinessSource;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonPeriod;
use setasign\Fpdi\Fpdi;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\File; // To use File::get/put if needed, though temp file is cleaner

class ReportController extends Controller
{
    public function products()
    {
        $from   = request('from') ? request('from') : date("Y-m-d");
        $to     = request('to') ? request('to') : date("Y-m-d");
        $orders = $this->getOrders($from, $to);
        $period = collect(CarbonPeriod::create($from, $to))->map(fn($d) => $d->format('Y-m-d'));
        return $this->getProducts($orders, $period);
    }

    public function getProducts($orders, $period)
    {
        $data = [];

        $products = Product::when(request()->filled('product_id'), function ($query) {
            $query->where('id', request('product_id'));
        })->get(["item_number", "description"])->keyBy('description');

        foreach ($period as $date) {
            $ordersForDate = $orders->filter(function ($order) use ($date) {
                return date("Y-m-d", strtotime($order->order_date)) === $date;
            });

            foreach ($products as $product) {
                $totalQuantity = 0;
                $totalPrice    = 0;
                $basePrices    = []; // unit rate => quantity sold at that rate

                foreach ($ordersForDate as $order) {
                    foreach ($order->items as $item) {
                        if ($item['item'] === $product->description) {
                            $totalQuantity += $item['quantity'];
                            $totalPrice += $item['total'];

                            $rateKey = number_format((float) $item['rate'], 2, '.', '');
                            $basePrices[$rateKey] = ($basePrices[$rateKey] ?? 0) + $item['quantity'];
                        }
                    }
                }

                ksort($basePrices, SORT_NUMERIC);

                $data[date("d M", strtotime($date))][$product->item_number ?? "---"] = [
                    'item_code'   => $product->item_number ?? "---",
                    'product'     => $product->item_number ?? "---",
                    'price'       => number_format($totalPrice ?? 0, 2),
                    'quantity'    => $totalQuantity ?? 0,
                    'base_prices' => (object) $basePrices,
                ];
            }
        }

        return $data;
    }

    public function payment_modes()
    {
        $from   = request('from') ? request('from') : date("Y-m-d");
        $to     = request('to') ? request('to') : date("Y-m-d");
        $orders = $this->getOrders($from, $to);
        $period = collect(CarbonPeriod::create($from, $to))->map(fn($d) => $d->format('Y-m-d'));
        return $this->getPaymentModes($orders, $period);
    }

    public function sources()
    {
        $from   = request('from') ? request('from') : date("Y-m-d");
        $to     = request('to') ? request('to') : date("Y-m-d");
        $orders = $this->getOrders($from, $to);
        $period = collect(CarbonPeriod::create($from, $to))->map(fn($d) => $d->format('Y-m-d'));
        return $this->getSources($orders, $period);
    }

    public function getSources($orders, $period)
    {
        // All business sources from DB
        $businessSources = BusinessSource::pluck('name');

        $data = [];

        foreach ($period as $date) {
            $data[$date] = [];

            foreach ($businessSources as $sourceName) {
                // Filter orders for this date + business source
                $filtered = $orders->filter(function ($order) use ($date, $sourceName) {
                    $orderSource = $order->business_source ? $order->business_source->name : 'Unknown';
                    return date("Y-m-d", strtotime($order->order_date)) === $date
                        && $orderSource === $sourceName;
                });

                $totalQuantity = 0;
                $totalPrice    = 0;

                foreach ($filtered as $order) {
                    foreach ($order->items as $item) {
                        $totalQuantity += $item['quantity'];
                        $totalPrice += $item['total'];
                    }
                }

                $data[$date][$sourceName] = [
                    'price'    => number_format($totalPrice, 2),
                    'quantity' => $totalQuantity,
                ];
            }
        }

        return $data;
    }

    public function getPaymentModes($orders, $period)
    {
        $data = [];

        // Get all payment methods from existing orders
        $paymentMethods = $orders->pluck('payment_method')
            ->map(fn($m) => strtoupper($m))
            ->unique()
            ->values();

        // If no orders, optionally define your known payment methods
        if ($paymentMethods->isEmpty()) {
            $paymentMethods = collect(['CASH', 'CARD', 'COD']); // add more as needed
        }

        foreach ($period as $date) {
            $data[$date] = [];

            foreach ($paymentMethods as $method) {
                // Filter orders for this date + method
                $filtered = $orders->filter(function ($order) use ($date, $method) {
                    return date("Y-m-d", strtotime($order->order_date)) === $date
                        && strtoupper($order->payment_method) === $method;
                });

                $totalQuantity = 0;
                $totalPrice    = 0;

                foreach ($filtered as $order) {
                    foreach ($order->items as $item) {
                        $totalQuantity += $item['quantity'];
                        $totalPrice += $item['total'];
                    }
                }

                $data[$date][$method] = [
                    'payment_method' => $method,
                    'price'          => number_format($totalPrice, 2),
                    'quantity'       => $totalQuantity,
                ];
            }
        }

        return $data;
    }

    public function getOrders($from, $to, $cols = ["id", "order_date", "order_id", "total", "channel", "payment_method", "items", "business_source_id"])
    {
        $order_status = request('order_status');

        return Order::orderByDesc('id')
            ->when($order_status, function ($q) use ($order_status) {
                $q->where('order_status', $order_status);
            })
            // ->whereNot("order_status", Order::CANCELLED)
            // ->where("order_id","55524")
            ->whereBetween('order_date', [$from . " 00:00:00", $to . " 23:59:59"])
            ->withOut("customer", "payments")
            ->with("business_source")
            ->get($cols);
    }

    public function manifestReport()
    {
        $from   = request('from') ? request('from') : date("Y-m-d");
        $to     = request('to') ? request('to') : date("Y-m-d");

        // Same filters the Invoices list uses, so the manifest contains exactly the
        // rows the user has on screen. Each is optional — with none supplied the
        // behaviour is unchanged (today's invoices for the given date window).
        $search              = trim(request('search'));
        $status              = request('status');
        $customer_id         = request('customer_id');
        $delivery_service_id = request('delivery_service_id');
        $payment_method      = request('payment_method');

        // Only invoiced orders, matching the Invoices list (filtered by invoice date).
        // Exclude returned and cancelled invoices — they should not appear on the manifest.
        $orders = Invoice::with('order')
            ->when($search, function ($q) use ($search) {
                $order_id = Order::where("order_id", $search)->value("id");

                if ($order_id) {
                    $q->where('order_id', $order_id);
                } else {
                    // Grouped so the OR cannot escape the other filters below.
                    $q->where(function ($w) use ($search) {
                        $w->where('id', env("WILD_CARD") ?? 'ILIKE', '%' . ltrim($search, '0') . '%')
                            ->orWhereHas('order', function ($q2) use ($search) {
                                $q2->where('tracking_number', $search);
                            });
                    });
                }
            })
            ->when($customer_id, function ($q) use ($customer_id) {
                $q->whereHas('order', function ($o) use ($customer_id) {
                    $o->where('customer_id', $customer_id);
                });
            })
            ->when($delivery_service_id, function ($q) use ($delivery_service_id) {
                $q->whereHas('order', function ($o) use ($delivery_service_id) {
                    $o->where('delivery_service_id', $delivery_service_id);
                });
            })
            ->when($payment_method, function ($q) use ($payment_method) {
                $q->whereHas('order', function ($o) use ($payment_method) {
                    $o->where('payment_method', $payment_method);
                });
            })
            ->when($status, function ($q) use ($status) {
                $q->where('status', $status);
            })
            // Skip the date window when searching, so a lookup by id / order ref /
            // tracking finds the invoice regardless of when it was created.
            ->when(! $search, function ($q) use ($from, $to) {
                $q->whereBetween('created_at', [$from . " 00:00:00", $to . " 23:59:59"]);
            })
            ->whereNotIn('status', ['Returned', 'Cancelled'])
            ->orderByDesc('id')
            ->get()
            ->pluck('order')   // get the Order behind each invoice
            ->filter()         // drop any invoice whose order is missing
            ->values()
            ->toArray();


        // 2. Load the Blade view and pass the data
        $pdf = Pdf::loadView(
            'report.manifest',
            [
                'report_title' => "Shipping Manifest $from - $to",
                'date' => "$from - $to",
                'orders' => $orders
            ]
        );

        // 3. Stream or Download the PDF
        // return $pdf->stream('monthly_report.pdf'); // Streams to browser
        return $pdf->download("Shipping Manifest $from - $to.pdf"); // Downloads the file
    }

    public function awbPrintReport()
    {
        $from   = request('from') ? request('from') : date("Y-m-d");
        $to     = request('to') ? request('to') : date("Y-m-d");

        $data = $this->getDefaultData();

        $orders = Order::orderByDesc('id')
            ->where('order_status', "completed")
            ->whereBetween('order_date', [$from . " 00:00:00", $to . " 23:59:59"])
            ->get();

        if ($orders->isEmpty()) {
            return Response::make('No orders found to generate report.', 404);
        }

        // 1. Initialize the PDF Merger (FPDI/FPDF)
        // Set page size and orientation (A4, Portrait) for the new document
        $fpdi = new Fpdi('P', 'mm', 'A4');

        // 2. Loop through orders, generate individual PDFs, and merge pages
        foreach ($orders as $order) {
            // Get the binary content of the individual PDF from your render function
            $pdfContent = $this->render($order, $data);

            // Create a unique temporary file path
            $tmpFile = tempnam(sys_get_temp_dir(), 'awb_pdf_');

            // Save the binary content to the temporary file
            file_put_contents($tmpFile, $pdfContent);

            // Set the temporary file as the source for FPDI to read
            $pageCount = $fpdi->setSourceFile($tmpFile);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                // Add a new page to the final document
                $fpdi->AddPage();

                // Import the page from the temporary file
                $templateId = $fpdi->importPage($pageNo);

                // Get the size of the imported page
                $size = $fpdi->getTemplateSize($templateId);

                // Place the imported page onto the new page, scaled to fit
                $fpdi->useTemplate($templateId, 0, 0, $size['width'], $size['height']);
            }

            // CRITICAL: Delete the temporary file immediately after processing
            unlink($tmpFile);
        }

        // 3. Output the merged PDF
        // 'S' means return the document as a string (binary content)
        $mergedPdfContent = $fpdi->Output('S');

        // 4. Return the merged PDF to the browser
        $filename = 'AWB_Report_' . $from . '_to_' . $to . '.pdf';

        return Response::make($mergedPdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-cache, must-revalidate',
            'Pragma'              => 'no-cache',
            'Access-Control-Expose-Headers' => 'Content-Disposition',
        ]);


        return response($content)
            ->header('Access-Control-Expose-Headers', 'Content-Disposition');
    }

    /**
     * Sales analysis for the "Analyse" page: revenue trend, best/worst-selling
     * products, and channel mix over a date range. Read-only, JSON — mirrors
     * the from/to convention of manifestReport()/awbPrintReport() above.
     */
    public function salesAnalysis()
    {
        return $this->computeSalesAnalysis(request('from'), request('to'));
    }

    /** Same data as salesAnalysis(), rendered as a PDF (dompdf) instead of JSON. */
    public function salesAnalysisPdf()
    {
        $result = $this->computeSalesAnalysis(request('from'), request('to'));
        $daily = $result['daily'];

        // Month-over-month split at the midpoint of the daily series (same
        // point used for each product's qty_m1/qty_m2 in computeSalesAnalysis).
        $half = (int) ceil(count($daily) / 2);
        $firstHalf = array_slice($daily, 0, $half);
        $secondHalf = array_slice($daily, $half);
        $sum = fn($rows, $key) => array_sum(array_column($rows, $key));
        $m1 = ['revenue' => $sum($firstHalf, 'revenue'), 'orders' => $sum($firstHalf, 'orders')];
        $m2 = ['revenue' => $sum($secondHalf, 'revenue'), 'orders' => $sum($secondHalf, 'orders')];
        $revDelta = $m1['revenue'] > 0 ? (($m2['revenue'] - $m1['revenue']) / $m1['revenue']) * 100 : 0;

        $avgDaily = count($daily) ? array_sum(array_column($daily, 'revenue')) / count($daily) : 0;
        $peak = collect($daily)->sortByDesc('revenue')->first();
        $peakMultiple = $avgDaily > 0 && $peak ? round($peak['revenue'] / $avgDaily) : 0;

        $pdf = Pdf::loadView('report.sales-analysis', [
            'd'            => $result,
            'trendHtml'    => \App\Support\ReportCharts::weeklyRevenueBars($daily),
            'm1'           => $m1,
            'm2'           => $m2,
            'revDelta'     => $revDelta,
            'peak'         => $peak,
            'peakMultiple' => $peakMultiple,
            'generated_at' => now()->format('Y-m-d H:i') . ' ' . config('app.timezone'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Roze-Skincare-Sales-Analysis-' . $result['range']['from'] . '_to_' . $result['range']['to'] . '.pdf');
    }

    /** Shared aggregation behind salesAnalysis() (JSON) and salesAnalysisPdf(). */
    private function computeSalesAnalysis($fromParam, $toParam): array
    {
        $from = $fromParam ? $fromParam : now()->subMonths(2)->toDateString();
        $to   = $toParam ? $toParam : now()->toDateString();

        $invoices = Invoice::with('order.delivery_service', 'order.business_source')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->whereNotIn('status', ['Cancelled', 'Returned'])
            ->get();

        // Split point for the month-over-month trend column: the midpoint of
        // whatever range was requested (not hardcoded to "2 months").
        $fromDate = \Carbon\Carbon::parse($from)->startOfDay();
        $toDate   = \Carbon\Carbon::parse($to)->endOfDay();
        $mid      = $fromDate->copy()->addSeconds($fromDate->diffInSeconds($toDate) / 2);

        $products = [];
        $dailyRevenue = [];
        $dailyOrders = [];
        $deliveryService = [];
        $paymentMode = [];
        $businessSource = [];
        $customerSet = [];
        $statusCount = [];
        $totalRevenue = 0.0;
        $totalItemsSold = 0;

        foreach ($invoices as $inv) {
            $order = $inv->order;
            $items = $order ? (array) $order->items : [];
            $day = $inv->created_at->toDateString();
            $isFirstHalf = $inv->created_at->lt($mid);

            $custKey = $order->customer_id ?? ('inv-' . $inv->id);
            $customerSet[$custKey] = ($customerSet[$custKey] ?? 0) + 1;
            $statusCount[$inv->status] = ($statusCount[$inv->status] ?? 0) + 1;

            $ds = optional(optional($order)->delivery_service)->name ?? 'Unknown';
            $deliveryService[$ds] = ($deliveryService[$ds] ?? 0) + 1;

            // Normalise case-duplicate payment values (e.g. "COD" / "cod") and
            // blank markers so the frontend doesn't have to.
            $pmRaw = trim(optional($order)->payment_method ?? '');
            $pm = match (true) {
                $pmRaw === '' || $pmRaw === '---' => 'Unspecified',
                strtolower($pmRaw) === 'cod'      => 'Cash on Delivery (COD)',
                default                            => $pmRaw,
            };
            $paymentMode[$pm] = ($paymentMode[$pm] ?? 0) + 1;

            $bsRaw = trim(optional(optional($order)->business_source)->name ?? '');
            $bs = $bsRaw === '' || $bsRaw === '---' ? 'Unspecified' : $bsRaw;
            $businessSource[$bs] = ($businessSource[$bs] ?? 0) + 1;

            $lineTotal = 0.0;
            foreach ($items as $line) {
                $name = trim($line['item'] ?? 'Unknown item');
                $qty  = (int) ($line['quantity'] ?? 0);
                // Use the line's own "total", not qty*rate: bundle child lines carry
                // their component's normal "rate" for display but total=0 (the
                // customer paid once via the bundle's own line) — qty*rate double-
                // charges those. Fall back to qty*rate only when total is absent.
                $rev = isset($line['total']) ? (float) $line['total'] : $qty * (float) ($line['rate'] ?? 0);
                $lineTotal += $rev;

                if (! isset($products[$name])) {
                    $products[$name] = ['name' => $name, 'qty' => 0, 'revenue' => 0.0, 'orders' => 0, 'qty_m1' => 0, 'qty_m2' => 0];
                }
                $products[$name]['qty'] += $qty;
                $products[$name]['revenue'] += $rev;
                $products[$name]['orders']++;
                if ($isFirstHalf) $products[$name]['qty_m1'] += $qty;
                else $products[$name]['qty_m2'] += $qty;

                $totalItemsSold += $qty;
            }

            $invTotal = (float) ($inv->total ?? $lineTotal);
            $totalRevenue += $invTotal;
            $dailyRevenue[$day] = ($dailyRevenue[$day] ?? 0) + $invTotal;
            $dailyOrders[$day] = ($dailyOrders[$day] ?? 0) + 1;
        }

        // Fill every calendar day in range so zero-sales days show as real
        // dips on the trend chart rather than being silently skipped.
        $daily = [];
        foreach (\Carbon\CarbonPeriod::create($fromDate, $toDate) as $d) {
            $key = $d->toDateString();
            $daily[] = ['date' => $key, 'revenue' => round($dailyRevenue[$key] ?? 0, 2), 'orders' => $dailyOrders[$key] ?? 0];
        }

        $byQty = collect($products)->sortByDesc('qty')->values();
        $byRevenue = collect($products)->sortByDesc('revenue')->values();
        $bottomByQty = collect($products)->sortBy('qty')->values();

        $toChannel = fn($arr) => collect($arr)->map(fn($count, $label) => ['label' => $label, 'count' => $count])->sortByDesc('count')->values();

        return [
            'range' => ['from' => $from, 'to' => $to],
            'summary' => [
                'total_revenue'     => round($totalRevenue, 2),
                'order_count'       => $invoices->count(),
                'unique_customers'  => count($customerSet),
                // "Regular"/repeat customers: placed more than one order in
                // this period — same definition as CustomerController::repeatedCustomerReport().
                'repeat_customers'  => count(array_filter($customerSet, fn ($c) => $c > 1)),
                'total_items_sold'  => $totalItemsSold,
                'unique_products'   => count($products),
                'avg_order_value'   => $invoices->count() ? round($totalRevenue / $invoices->count(), 2) : 0,
            ],
            'daily'          => $daily,
            // ->all() everywhere below: plain PHP arrays, not Collections — the
            // PDF Blade view calls array_column()/max() on these directly.
            'top_by_qty'     => $byQty->take(10)->values()->all(),
            'top_by_revenue' => $byRevenue->take(10)->values()->all(),
            'bottom_by_qty'  => $bottomByQty->take(12)->values()->all(),
            'channels' => [
                'delivery_service' => $toChannel($deliveryService)->all(),
                'payment_mode'     => $toChannel($paymentMode)->all(),
                'business_source'  => $toChannel($businessSource)->all(),
            ],
            'status_count' => $statusCount,
        ];
    }

    public function render($order, $defaultData)
    {
        $awb = $order->tracking_number;

        $customer =  $order->customer;
        // Address frozen onto this order; fall back to the customer's current one.
        $shipTo = $order->shippingAddress ?? optional($customer)->shipping_address;

        $isCod = $order->payment_method == 'COD' || $order->payment_method == 'cod';

        $data = [
            ...$defaultData,
            // Receiver Info
            'receiver_name' => $customer->full_name,
            'receiver_country' => 'AE',
            'receiver_city' => $shipTo?->city ?? 'Dubai',
            'receiver_address' =>  $shipTo?->address_1 ?? "No Address given",
            'receiver_phone' => $customer->whatsapp . " " . $customer->phone,
            'tracking_number' => $awb,

            // Shipment & Other Info
            'reference' => (string) ($order->order_id ?? ""),
            // 'payment_type' => 'COD',
            'declared_value' => $order->total,
            'cod_value' => $isCod ? $order->total : "00.00",
            'special_notes' => $order->special_instructions ?? "Fragile handle with care",
            'service_type' => 'None',
            'items' => $order->items,
        ];

        $pdf = Pdf::loadView('pdf.awb', compact('data'))->setPaper('A4', 'portrait');

        $content = $pdf->output(); // raw PDF binary

        $fileName = $awb ? $awb . '.pdf' : 'invoice.pdf';

        return response($content)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"')
            ->header('Access-Control-Expose-Headers', 'Content-Disposition');
    }

    function getDefaultData()
    {
        return [
            // Top Info
            'printed_on' => now()->format('d-M-Y H:i:s') . ' (UTC+04:00) Gulf Standard Time (Dubai)',
            'account_number' => env("EMX_ACCOUNT_NO"),

            // Shipper Info
            'shipper_name' => 'Roze Skincare',
            'shipper_country' => 'AE',
            'shipper_city' => 'Dubai',
            'shipper_address' => "DRS JAFFER'S BLDG - SHOP NO 4 - Al Nahdha street - 83481 - Al Souq Al Kabeer",
            'shipper_phone' => '0529048025 0529048025',

            // Content Details
            'content_type' => 'NonDocument',
            'weight' => '250.0',
            'length' => '23.0',
            'width' => '14.0',
            'height' => '4.0',
        ];
    }
}
