<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Document</title>
    {{-- <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@400;600;700&display=swap"> --}}

    <style>
        * {
            font-family: "Source Sans Pro", sans-serif !important;
            margin: 0;
            padding: 0;
            font-size: 11px;
        }

        body {
            margin: 25px 25px 25px 25px;
        }

        .border {
            padding: 5px;
            border: 1px solid #dddddd;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #dddddd;
            padding: 4px;
            text-align: left;
        }

        .text-left {
            text-align: left;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .logo {
            max-width: 150px;
            height: auto;
        }

        .my-blue {
            background-color: #408dfb !important;
        }

        .my-green {
            background-color: #a2b63b !important;
        }

        .my-grey {
            background-color: rgb(182, 182, 182) !important;
        }

        .my-red {
            background-color: #e04e4f !important;
        }

        .col-1 {
            width: 8.3%;
        }

        .col-2 {
            width: 16.6%;
        }

        .col-3 {
            width: 24.9%;
        }

        .col-4 {
            width: 33.2%;
        }

        .col-6 {
            width: 50%;
        }

        .uppercase {
            text-transform: uppercase;
        }

        .border-top {
            border-top: 1px solid #dddddd;
            /* Add top border */
        }

        .border-bottom {
            border-bottom: 1px solid #dddddd;
            /* Add top border */
        }

        .border-none {
            border: none;
            /* Add top border */
        }

        .mt-1 {
            margin-top: 5px;
            /* Add top border */
        }

        .mt-2 {
            margin-top: 10px;
            /* Add top border */
        }

        .mt-3 {
            margin-top: 15px;
            /* Add top border */
        }

        .mt-4 {
            margin-top: 20px;
            /* Add top border */
        }

        .mt-5 {
            margin-top: 25px;
            /* Add top border */
        }

        .page-break {
            /* page-break-after: always; */
            page-break-before: always !important;
        }

        .circle-container {
            text-align: left;
        }

        .circle-container img {
            border-radius: 50%;
            max-width: 100%;
            vertical-align: middle;
            /* Adjust as needed for spacing */
        }

        .width-100 {
            width: 100%;
        }

        .width-50 {
            width: 50%;
        }

        .text-white {
            color: #fff;
        }

        .footer {
            position: fixed;
            bottom: 2mm;
            left: 10mm;
            right: 10mm;
            text-align: center;
            font-size: 12px;
        }

        .page-number:after {
            content: counter(page);
        }

        body {
            font-family: Arial, sans-serif;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
        }

        .header-left {
            display: flex;
            align-items: center;
        }

        .logo {
            width: 100px;
            margin-right: 15px;
        }

        .hotel-info {
            text-align: right;
            /* Align the hotel name and tagline to the right */
        }

        .guest-info {
            margin: 20px 0;
            background-color: #f7f7f7;
            padding: 15px;
            border-radius: 8px;
            width: 100%;
        }

        .guest-info p {
            margin: 0;
            padding: 2px 0;
        }

        .quotation-header {
            text-align: center;
            margin: 20px 0;
            font-size: 18px;
        }

        .info-container {
            display: flex;
            justify-content: space-between;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 15px 0 15px 0;
            border-radius: 8px;
            background-color: #f9f9f9;
        }

        .info-container div {
            margin: 0 10px;
        }

        .info-container .label {
            font-weight: bold;
        }

        .footer {
            text-align: right;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="footer">

        <hr class="mt-1" style="color: #dddddd" />
        <table>
            <tr>
                <td class="text-left border-none col-4">
                    <div>Generated On: {{ date('d-M-y') }}</div>
                </td>
                <td class="text-center border-none col-4">
                    <div>This is a system-generated report</div>
                </td>
                <td class="text-right border-none col-4">
                    Page <span class="page-number"></span>
                </td>
            </tr>
        </table>
    </div>

    @include('common.company-info', $quotation->company)

    <table>
        <tr>
            <td class="text-left border-none col-4">

            </td>

            <td class="text-center border-none col-4">
                <div class="quotation-header">Quotation</div>
            </td>

            <td class="text-right border-none col-4">
                <p class="text-right">Ref: {{ $quotation->ref_no ?? '' }}</p>
            </td>
        </tr>
    </table>



    <div class="info-container">
        <table>
            <tr>
                <td class="text-left border-none col-3">
                    <h4>Guest Info</h4>
                    <p>{{ $quotation->customer->full_name ?? '' }}</p>
                    <p>{{ $quotation->customer->contact_no ?? '' }}</p>
                </td>
                <td class="text-left border-none col-3">
                    <h4>Check In</h4>
                    <p>{{ $quotation->arrival_date }} 12:00 AM</p>
                    <p></p>
                </td>
                <td class="text-left border-none col-3">
                    <h4>Check Out</h4>
                    <p>{{ $quotation->departure_date }} 11:00 AM</p>
                    <p></p>
                </td>
                <td class="text-left border-none col-3">
                    <h4>Booking Date</h4>
                    <p>{{ $quotation->book_date }}</p>
                    <p></p>
                </td>
            </tr>

            <tr>
                <td class="text-left border-none col-3">
                </td>
                <td class="text-left border-none col-3">
                    <h4>Hours</h4>
                    <p>{{ $quotation->total_booking_hours }}</p>
                </td>
                <td class="text-left border-none col-3">
                    <h4>Hall</h4>
                    <p>{{ $quotation->room_types }}</p>
                </td>
                <td class="text-left border-none col-3">
                    <h4>Function</h4>
                    <p>{{ $quotation->function_names }}</p>
                </td>
            </tr>
        </table>
    </div>
    <table>
        <thead>
            <tr style="background:#408dfb; color:white;">
                <th class="text-center">#</th>
                <th>Description</th>
                <th class="text-center">Qty</th>
                <th class="text-center">Unit Price</th>
                <th class="text-center">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quotation->items as $key => $item)
                <tr>
                    <td style="width:50px;" class="text-center">{{ $key + 1 }}</td>
                    <td style="width:350px;">{{ $item['description'] }}</td>
                    <td style="width:100px;" class="text-center">{{ $item['qty'] }}</td>
                    <td class="text-right">{{ number_format($item['unit_price'], 2) }}</td>
                    <td class="text-right">{{ number_format($item['total_price'], 2) }}</td>
                </tr>
            @endforeach

            <tr>
                <td colspan="3" class="border-none"></td>
                <td class=" border-none" style="border-bottom: 1px solid #dddddd">Sub Total</td>
                <td class="text-right border-none" style="border-bottom: 1px solid #dddddd">
                    {{ number_format($quotation->sub_total, 2) }}
                </td>
            </tr>
            <tr>
                <td colspan="3" class="border-none"></td>
                <td class=" border-none border-bottom" style="border-bottom: 1px solid #dddddd">Discount</td>
                <td class="text-right border-none" style="border-bottom: 1px solid #dddddd">
                    {{ number_format($quotation->discount, 2) }}
                </td>
            </tr>
            <tr class="total-row">
                <td colspan="3" class="border-none"></td>

                <td class=" border-none" style="border-bottom: 1px solid #dddddd">Total Rs.</td>
                <td class="text-right border-none" style="border-bottom: 1px solid #dddddd">
                    {{ number_format($quotation->total, 2) }}
                </td>
            </tr>
        </tbody>
    </table>
    <br>
    <br>
    <div>
        <p style="color:#0762e2;">Additional Charges:</p>
        <p>Extra Hall Usage Hours : RS 4000/- per Hrs</p>
        <p>Generator : Rs 1500/- Per Hrs</p>
        <p>Projector : 500 /-</p>
        <p>Audio system : 1500/-</p>
        <p>Cleaning Charges : 15000/-</p>
    </div>
    <br>
    <br>
    <div>
        <p style="color:#0762e2;">Terms and Condition:</p>
        <p>Booking Confirmation: 25% advance payment is required to confirm the hall booking.</p>

        <p>Cancellation Policy: In case of cancellation, the advance payment is non</p>
        <p>Payment Methods: Payments can be made via cash, UPI, or bank transfer. Please note that cheques are not
            accepted.</p>
        <p>Full Payment Requirement: The full payment must be completed at least one day before the event date.</p>
        <p>Additional Charges: Any additional charges will be applied as per the notes provided above</p>
        <p>GST : The above price are included GST .</p>
    </div>
    <br>
    <br>
    <div>
        <p style="color:#0762e2;">Acknowledgment of Terms:</p>
        <p>I hereby acknowledge and agree to all the terms and conditions outlined above</p>
    </div>
    <br>
    <br>
    <br>
    <br>
    <br>
    <br>
    <table class="">
        <tr>
            <td style="border-top: 1px solid #dddddd" class="border-none col-4">
                <p class="reds">Guest Signature</p>
            </td>
            <td class="text-center border-none col-4 uppercase"></td>
            <td style="border-top: 1px solid #dddddd" class="text-right border-none col-4">
                <p class="reds">Manager</p>
            </td>
        </tr>
    </table>
</body>

</html>
