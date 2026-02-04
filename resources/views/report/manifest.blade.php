<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{{ $report_title }}</title>
    <style>
        /* --- Compact Styling Adjustments --- */
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            /* Reduced overall size and margins for less white space */
            margin: 10px;
            padding: 10px;
        }

        /* --- HEADER STYLES REMOVED --- */

        /* Table Styling for Compact Output */
        .report-table {
            width: 100%;
            border-collapse: collapse;
            /* Reduced top margin since header is removed */
            margin-top: 5px;
            border: none;
        }

        /* This targets all cells (th and td) */
        .report-table th,
        .report-table td {
            /* Only top/bottom borders, no side borders */
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            border-left: none;
            border-right: none;
            /* Reduced padding */
            padding: 4px 6px;
            text-align: left;
        }

        /* This specifically styles the table header cells (th) */
        .report-table th {
            background-color: transparent;
            font-size: 9pt;
            font-weight: bold;
        }

        .report-table td {
            font-size: 10pt;
        }

        .text-center {
            text-align: center;
        }

        /* Summary Box Adjustment */
        .summary-box {
            width: 300px;
            margin-left: auto;
            margin-top: 10px;
            font-size: 10pt;
        }

        .summary-box td {
            border: none !important;
            padding: 4px 6px !important;
        }
    </style>
</head>

<body>

    <table width="100%" cellspacing="0" cellpadding="2">
        <tr>
            <td style="width: 50%;">
                <img alt="EMX Logo" width="75" height="75" src="data:image/png;base64,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">
            </td>

            <td style="width: 50%; text-align: right;">
                <div style="font-size: 8pt; margin-top: 10px;">
                    {{ $date }}
                </div>
            </td>
        </tr>
    </table>


    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 5%;">Sl No</th>

                <th style="width: 12%;" class="text-center">AWBNo</th>
                <th style="width: 12%;" class="text-center">ShipperRef</th>
                <th style="width: 18%;" class="text-center">Destination</th>
                <th style="width: 18%;" class="text-center">Consignee</th>
                <th style="width: 10%;" class="text-right">Payment Method</th>

                <th style="width: 10%; text-align: right !important;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($orders as $index => $item)
            <tr>
                <td>{{ $index + 1  }}</td>
                <td class="text-center">{{ $item['tracking_number'] }}</td>
                <td class="text-center">{{ $item['order_id'] }}</td>
                <td>{{ $item['customer']['shipping_address']['city'] }}</td>
                <td>{{ $item['customer']['full_name'] }}</td>
                <td class="text-right">{{ $item['payment_method'] }}</td>
                <td style="text-align: right !important;"><img src="{{ public_path('AED.svg') }}" style="width: 10px;"> {{ number_format($item['total'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>