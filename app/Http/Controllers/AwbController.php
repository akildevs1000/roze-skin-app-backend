<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class AwbController extends Controller
{
    public function download($awb_no)
    {
        $data = [
            // Top Info
            'printed_on' => '20-Nov-2025 12:49:29 (UTC+04:00) Gulf Standard Time (Dubai)',
            'account_number' => '1067170',
            'shipment_number' => '1000037991324',

            // Shipper Info
            'shipper_name' => 'Roze Skincare',
            'shipper_country' => 'AE',
            'shipper_city' => 'Dubai',
            'shipper_address' => "DRS JAFFER'S BLDG - SHOP NO 4 - Al Nahdha street - 83481 - Al Souq Al Kabeer",
            'shipper_phone' => '0529048025 0529048025',

            // Receiver Info
            'receiver_name' => 'Salwa Machhiwala',
            'receiver_country' => 'AE',
            'receiver_city' => 'Dubai',
            'receiver_address' => 'Eleganza Apartments, Flat 703',
            'receiver_phone' => '0508901064 +971508901064',

            // Content Details
            'content_type' => 'NonDocument',
            'pcs' => '$1/1$',
            'weight' => '250.0',
            'l' => '23.0',
            'w' => '14.0',
            'h' => '4.0',

            // Shipment & Other Info
            'reference' => 'any referece number',
            'payment_type' => 'COD',
            'cod_value' => '48.00',
            'incoterms' => 'DDU',
            'special_notes' => 'Fragile handle with care',
            'service_type' => 'None',
            'other_info' => 'Door To Door',
        ];

        // 2. Load the view with the data
        $pdf = Pdf::loadView('pdf.awb', $data)->setPaper('A4', 'portrait');

        return $pdf->stream('awb-' . $awb_no . '.pdf');

        // or force download:
        // return $pdf->download('awb-'.$awb_no.'.pdf');
    }
}
