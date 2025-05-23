<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="{{ asset('css/invoice.css') }}">
    <title>With Tax</title>
</head>

<body>
    <div class="tm_container" id="download_area">
        {{-- <link rel="stylesheet" href="{{ asset('css/invoice.css') }}"> --}}
        <div class="tm_invoice_wrap print_header_main">
            <div class="tm_invoice tm_style2 print_header" id="tm_download_section" style="padding-top:10px">
                <div class="tm_invoice_in">
                    <div class="tm_invoice_content">
                        <div class="tm_invoice_head tm_mb0">
                            <div class="tm_invoice_left">
                                <div class="tm_logo">

                                    @if (env('APP_ENV') == 'production')
                                    <img src="{{ urldecode($booking->company->logo) }}" height="100px"
                                        width="100" style="margin-left: 50px;margin-top: 0px">
                                    @elseif ($booking->company_id == 1)
                                    <img src="https://backend.ezhms.com/upload/app-logo.jpg" alt="Logo"
                                        style="max-height:70px!important;margin-top:10px">
                                    @elseif ($booking->company_id == 2 || $booking->company_id == 3)
                                    <img src="https://backend.ezhms.com/upload/app-logo.jpeg" alt="Logo"
                                        style="max-height:100px!important">
                                    @endif
                                </div>
                            </div>
                            <div class="tm_invoice_right tm_text_right">
                                <p class="tm_mb17">
                                    <b class="tm_f18 tm_primary_color">
                                        {{ $company->name ?? '' }}
                                    </b>
                                    <br>
                                    <span style="text-transform:capitalize">
                                        {{ strtolower($company->location) ?? '' }}
                                    </span><br>
                                    {{ strtolower($company->user->email) ?? '' }} <br>
                                    {{ strtolower($company->contact->number ?? '') }}<br>
                                    {{ $company->mol_id ?? '' }}
                                </p>
                            </div>
                        </div>
                        <div class="tm_grid_row tm_col_3 tm_col_2_sm tm_invoice_info_in   ">
                            <div>

                            </div>
                            <div style="text-align:center">
                                <spna style="font-size:20px">Tax Invoice</span>
                            </div>
                            <div style="text-align:right">
                                Invoice Number - {{ $invNo }}
                            </div>


                        </div>

                        <div class="tm_invoice_info tm_mb25">

                            <div class="" style="width:100%">
                                <div
                                    class="tm_grid_row tm_col_4 tm_col_2_sm tm_invoice_info_in tm_gray_bg tm_round_border">
                                    <div>
                                        <b class="tm_primary_color">Guest Info</b>
                                        <p class="tm_m0">{{ $booking->customer->full_name ?? '' }}
                                            <br>
                                            {{ $booking->customer->contact_no ?? '' }}
                                            <br>
                                            {{ $booking->customer->gst_number ?? '' }}
                                            <br>
                                            {{ strtolower($booking->customer->address) ?? '' }}
                                        </p>
                                    </div>
                                    <div>
                                        <span>Check In:</span> <br>
                                        <b class="tm_primary_color">
                                            {{ date('d M Y', strtotime($booking->check_in)) }}
                                            <div>{{ $first_check_in_time }}</div>
                                        </b>

                                        <br />
                                        <div>
                                            <span>Nights:</span> <br>
                                            <b
                                                class="tm_primary_color">{{ $booking->total_days == 0 ? 1 : $booking->total_days }}</b>
                                        </div>
                                    </div>
                                    <div>
                                        <span>Check Out:</span> <br>
                                        <b class="tm_primary_color">
                                            {{ date('d M Y', strtotime($booking->check_out)) }}
                                            <div>{{ $first_check_out_time }}</div>
                                        </b>

                                        <br />
                                        <div>
                                            <span>Rooms:</span> <br>
                                            <b class="tm_primary_color">{{ count($booking->bookedRooms) }}</b>
                                        </div>
                                    </div>
                                    <div>
                                        <span>Reservation No:</span> <br>
                                        <b class="tm_primary_color">{{ $booking->reservation_no }}

                                            <div>{{ date('d M Y', strtotime($booking->booking_date)) }}</div>

                                        </b>
                                        <br />
                                        <div>
                                            <span>Room type:</span> <br>
                                            <b class="tm_primary_color">{{ implode(',', $roomTypes) }}</b>
                                        </div>
                                    </div>



                                </div>
                            </div>
                        </div>

                        <div class="tm_table tm_style1">
                            <div class="tm_round_border">
                                <div class="tm_table_responsive">
                                    <table>
                                        <thead>
                                            <tr class="inv-room-th-txt">
                                                <th class="tm_width_2 tm_semi_bold tm_primary_color">Date</th>
                                                <th class="  tm_semi_bold tm_primary_color">Room No</th>
                                                <th class="  tm_semi_bold tm_primary_color">Unit</th>
                                                <th class="  tm_semi_bold tm_primary_color tm_text_right">Price</th>



                                                <th class="  tm_semi_bold tm_primary_color tm_text_right">SGST</th>
                                                <th class=" tm_semi_bold tm_primary_color tm_text_right">CGST</th>
                                                {{-- <th class=" tm_semi_bold tm_primary_color tm_text_right">Extras</th> --}}

                                                <th class="  tm_semi_bold tm_primary_color tm_text_right">Total
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                            @php

                                            $subtotal_price = 0;
                                            $subtotal_cgst = 0;
                                            $subtotal_sgst = 0;
                                            $subtotal_total = 0;

                                            @endphp
                                            @foreach ($orderRooms as $room)
                                            @php
                                            $subtotal_price += $room->price;
                                            $subtotal_sgst += $room->sgst;
                                            $subtotal_cgst += $room->cgst;

                                            $subtotal_total += $room->price + $room->room_tax;

                                            @endphp
                                            <tr class="inv-tr-txt">
                                                <td>
                                                    {{ date('d M Y', strtotime($room->date)) }}


                                                </td>
                                                <td>
                                                    {{ $room->room_no }} ({{ $room->room_type }})
                                                </td>
                                                <td>
                                                    {{ $room->no_of_adult + $room->no_of_child }}
                                                </td>

                                                <td class="  tm_text_right">
                                                    {{ number_format($room->price, 2) }}
                                                </td>


                                                <td class="  tm_text_right">
                                                    {{ number_format($room->sgst, 2) }}
                                                </td>
                                                <td class="  tm_text_right">
                                                    {{ number_format($room->cgst, 2) }}
                                                </td>

                                                {{-- <td class="  tm_text_right">
                                                    {{ number_format(($room->food_plan_price + $room->bed_amount + $room->early_check_in + $room->late_check_out),2) }}
                                                </td> --}}
                                                <td class="  tm_text_right">
                                                    {{ number_format($room->price + $room->room_tax, 2) }}
                                                </td>
                                            </tr>
                                            @endforeach

                                            @php
                                            $postings = App\Models\Posting::with('room')
                                            ->where('booking_id', $booking->id)

                                            ->get();
                                            @endphp
                                            @foreach ($postings as $post)
                                            <tr class=" inv-tr-txt">
                                                <td class=" ">
                                                    {{ date('d M Y', strtotime($post->posting_date)) }}
                                                </td>

                                                <td class="  ">
                                                    {{ $post->item }} ({{ $post->room->room_no }} )
                                                </td>
                                                <td class="  ">
                                                    {{ $post->qty }}
                                                </td>
                                                <td class="  tm_text_right ">
                                                    {{ number_format($post->amount, 2) }}
                                                </td>


                                                <td class="  tm_text_right">
                                                    {{ number_format($post->sgst, 2) }} <br>
                                                    {{-- ({{ (float) $post->tax_type / 2 }}%) --}}
                                                </td>
                                                <td class="  tm_text_right">
                                                    {{ number_format($post->cgst, 2) }} <br>
                                                    {{-- ({{ (float) $post->tax_type / 2 }} %) --}}
                                                </td>
                                                <td class="  tm_text_right">
                                                    {{ number_format((float) $post->amount_with_tax, 2) }}
                                                </td>
                                            </tr>
                                            @php

                                            $subtotal_price += $post->amount;
                                            $subtotal_sgst += $post->cgst;
                                            $subtotal_cgst += $post->sgst;

                                            $subtotal_total += $post->amount_with_tax;
                                            @endphp
                                            @endforeach
                                            <tr class="inv-tr-txt" style="font-weight:bold;border-top:groove;">
                                                <td class=" ">

                                                </td>
                                                <td class="  tm_text_left">

                                                </td>
                                                <td class="  tm_text_left">

                                                </td>
                                                <td class="  tm_text_right">
                                                    {{ number_format($subtotal_price, 2) }}
                                                </td>

                                                <td class="  tm_text_right">
                                                    {{ number_format($subtotal_sgst, 2) }}
                                                </td>
                                                <td class="  tm_text_right">
                                                    {{ number_format($subtotal_cgst, 2) }}
                                                </td>
                                                <td class="  tm_text_right">
                                                    {{ number_format($subtotal_total, 2) }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tm_invoice_footer tm_mb15">
                                <div class="tm_left_footer">
                                    <!-- <p class="tm_mb2"><b class="tm_primary_color">Payment info:</b></p>
                                    <p class="tm_m0">{{ $booking->customer->full_name ?? '' }} <br>
                                        {{ $paymentMode['payment_mode']['name'] ?? '' }}
                                        {{ $paymentMode['payment_method_id'] != 1 ? ' - ' . $paymentMode['reference_number'] : '' }}
                                        <br>Amount: {{ $amtLatter }}
                                    </p> -->



                                    <p style="margin-top:20px"> Tax Collected:
                                        {{ number_format((float) ($subtotal_sgst + $subtotal_cgst), 2) }} <br />
                                        SGST {{ $company->currency ? $company->currency : '' }}
                                        {{ number_format((float) $subtotal_sgst, 2) }}<br />
                                        CGST {{ $company->currency ? $company->currency : '' }}
                                        {{ number_format((float) $subtotal_cgst, 2) }}
                                    </p>


                                </div>
                                <div class="tm_right_footer" style="padding-top:10px">

                                    <table class="tm_mb0">
                                        <tbody>
                                            <tr>


                                            <tr>
                                                <td class="tm_width_3 tm_primary_color tm_bold tm_border_none tm_pt0 ">
                                                    Total
                                                </td>
                                                <td
                                                    class="tm_width_3 tm_primary_color tm_bold tm_text_right tm_border_none tm_pt0">
                                                    {{ $company->currency ? $company->currency : '' }}
                                                    {{ number_format($subtotal_total, 2) }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="tm_width_3 tm_primary_color tm_border_none tm_pt0">
                                                    Paid
                                                </td>
                                                <td
                                                    class="tm_width_3 tm_primary_color tm_text_right tm_border_none tm_pt0">
                                                    {{ $company->currency ? $company->currency : '' }}
                                                    {{ number_format($booking->paid_amounts, 2) ?? 0 }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td
                                                    class="tm_width_3 tm_border_top_0 tm_bold   tm_primary_color tm_gray_bg tm_radius_6_0_0_6">
                                                    Balance
                                                </td>
                                                <td
                                                    class="tm_width_3 tm_border_top_0 tm_bold   tm_primary_color tm_text_right tm_gray_bg tm_radius_0_6_6_0">
                                                    {{ $company->currency ? $company->currency : '' }}{{ number_format($subtotal_total - $booking->paid_amounts, 2) ?? 0 }}
                                                </td>
                                            </tr>
                                            <!-- <tr>
                                                <td class="tm_width_3 tm_primary_color tm_border_none tm_pt0">
                                                    Paid
                                                </td>
                                                <td class="tm_width_3 tm_primary_color tm_text_right tm_border_none tm_pt0">
                                                    {{ $company->currency ? $company->currency : '' }} {{ number_format($transactions->sum('credit'), 2) ?? 0 }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="tm_width_3 tm_border_top_0 tm_bold   tm_primary_color tm_gray_bg tm_radius_6_0_0_6">
                                                    Balance
                                                </td>
                                                <td class="tm_width_3 tm_border_top_0 tm_bold   tm_primary_color tm_text_right tm_gray_bg tm_radius_0_6_6_0">
                                                    {{ $company->currency ? $company->currency : '' }}{{ number_format($transactions->sum('debit') - $transactions->sum('credit'), 2) ?? 0 }}
                                                </td>
                                            </tr> -->


                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tm_text_right">
                                <br>Amount: <?php echo (new App\Http\Controllers\GRCController())->amountToText($subtotal_total); ?> Only
                            </div>
                        </div>
                        <div class="tm_note tm_text_center tm_font_style_normal"><br>
                            <b class="tm_primary_color">
                                Thank you for choosing us. We look forward to welcoming you back soon. 🙂
                            </b>
                            <hr class="tm_mb0">
                            {{-- <p class="tm_mb0"><b class="tm_primary_color">Terms & Conditions:</b></p> --}}
                            <p class="tm_m0">This Is System Generated Invoice And Does Not Require Signature.</p>
                        </div><!-- .tm_note -->
                    </div>
                </div>
            </div>
            <div class="tm_invoice_btns tm_hide_print" id="print_btn">
                <a href="javascript:window.print()" class="tm_invoice_btn tm_color1">
                    <span class="tm_btn_icon">
                        <svg xmlns="http://www.w3.org/2000/svg" class="ionicon" viewBox="0 0 512 512">
                            <path
                                d="M384 368h24a40.12 40.12 0 0040-40V168a40.12 40.12 0 00-40-40H104a40.12 40.12 0 00-40 40v160a40.12 40.12 0 0040 40h24"
                                fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="32" />
                            <rect x="128" y="240" width="256" height="208" rx="24.32" ry="24.32"
                                fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="32" />
                            <path d="M384 128v-24a40.12 40.12 0 00-40-40H168a40.12 40.12 0 00-40 40v24" fill="none"
                                stroke="currentColor" stroke-linejoin="round" stroke-width="32" />
                            <circle cx="392" cy="184" r="24" fill='currentColor' />
                        </svg>
                    </span>
                    <span class="tm_btn_text">Print</span>
                </a>
                <button id="tm_download_btn" class="tm_invoice_btn tm_color2">
                    <span class="tm_btn_icon">
                        <svg xmlns="http://www.w3.org/2000/svg" class="ionicon" viewBox="0 0 512 512">
                            <path
                                d="M320 336h76c55 0 100-21.21 100-75.6s-53-73.47-96-75.6C391.11 99.74 329 48 256 48c-69 0-113.44 45.79-128 91.2-60 5.7-112 35.88-112 98.4S70 336 136 336h56M192 400.1l64 63.9 64-63.9M256 224v224.03"
                                fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                stroke-width="32" />
                        </svg>
                    </span>
                    <span class="tm_btn_text">Download</span>
                </button>
            </div>
        </div>
    </div>


    <script src="https://code.jquery.com/jquery-3.6.4.js" integrity="sha256-a9jBBRygX1Bh5lt8GZjXDzyOB+bWve9EiO7tROUtj/E="
        crossorigin="anonymous"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.3.1/jspdf.umd.min.js"></script>

    <script>
        $(document).ready(function() {
            // alert('gg');
        });


        $('#tm_download_btn').on('click', function() {

            $('#print_btn').hide();

            var doc = document.implementation.createHTMLDocument();
            var $clone = $(".tm_container").clone();
            doc.body.appendChild($clone[0]);

            var href = $(getAllStyles()).attr('href');
            var $link = $("<link>").attr("href", $(getAllStyles()).attr('href')).attr('rel', 'stylesheet')
                .appendTo(doc.head);

            var content = doc.documentElement.outerHTML;

            var blob = new Blob([content], {
                type: "text/html"
            });

            var url = URL.createObjectURL(blob);
            var $a = $("<a>")
                .attr("href", url)
                .attr("download", "myDivContent.html")
                .appendTo("body");


            $a[0].click();
            $a.remove();
            URL.revokeObjectURL(url);
            $('#print_btn').show();

            function getAllStyles() {
                var styles = "";
                $("style, link[rel='stylesheet']").each(function() {
                    styles += $(this)[0].outerHTML;
                });
                return styles;
            }
        });


        // $('#tm_download_btn').on('click', function() {
        //     var doc = document.implementation.createHTMLDocument();
        //     var $style = $("<style>").text(getAllStyles()).appendTo(doc.head);

        //     function getAllStyles() {
        //         var styles = "";
        //         $("style, link[rel='stylesheet']").each(function() {
        //             styles += $(this)[0].outerHTML;
        //         });
        //         return styles;
        //     }
        //     var a = document.body.appendChild(
        //         document.createElement("a")
        //     );

        //     a.download = "newfile.html";
        //     a.href = "data:text/html," + $style[0]['innerText'] + document.getElementById("download_area")
        //         .innerHTML;
        //     console.log(a);

        //     a.click(); //Trigger a click on the element
        //     // when i download i want customize  download_area width
        // });
    </script>

</body>

</html>