<?php

namespace App\Http\Controllers;

use App\Models\BusinessSource;
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

                foreach ($ordersForDate as $order) {
                    foreach ($order->items as $item) {
                        if ($item['item'] === $product->description) {
                            $totalQuantity += $item['quantity'];
                            $totalPrice += $item['total'];
                        } else {
                            info($item['item']);
                        }
                    }
                }

                $data[date("d M", strtotime($date))][$product->item_number ?? "---"] = [
                    'item_code' => $product->item_number ?? "---",
                    'product'   => $product->item_number ?? "---",
                    'price'     => number_format($totalPrice ?? 0, 2),
                    'quantity'  => $totalQuantity ?? 0,
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

        $order_status = request('order_status');

        $orders = Order::orderByDesc('id')
            ->when($order_status, function ($q) use ($order_status) {
                $q->where('order_status', $order_status);
            })
            // ->whereNot("order_status", Order::CANCELLED)
            // ->where("order_id","55524")
            ->whereBetween('order_date', [$from . " 00:00:00", $to . " 23:59:59"])
            ->get()->toArray();


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

    public function render($order, $defaultData)
    {
        $awb = $order->tracking_number;

        $customer =  $order->customer;

        $isCod = $order->payment_method == 'COD' || $order->payment_method == 'cod';

        $data = [
            ...$defaultData,
            // Receiver Info
            'receiver_name' => $customer->full_name,
            'receiver_country' => 'AE',
            'receiver_city' => $customer?->shipping_address?->city ?? 'Dubai',
            'receiver_address' =>  $customer?->shipping_address?->address_1 ?? "No Address given",
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
