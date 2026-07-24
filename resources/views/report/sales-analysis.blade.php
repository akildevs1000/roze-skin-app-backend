<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Roze Skincare — Sales Performance Report</title>
<style>
  @php
    $brand = '#e0672a';
    $ink = '#0b0b0b'; $ink2 = '#52514e'; $muted = '#898781';
    $grid = '#e1e0d9'; $good = '#0ca30c'; $warn = '#fab219'; $crit = '#d03b3b';
    $cat = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948'];
    $money = fn($n) => 'AED ' . number_format((float) $n, 0);
    $num = fn($n) => number_format((float) $n);
    $pct = fn($n, $t) => $t ? number_format(($n / $t) * 100, 1) . '%' : '0%';
    $short = fn($s, $max = 60) => strlen($s) > $max ? rtrim(substr($s, 0, $max - 1)) . '…' : $s;
    $bottomRevSum = array_sum(array_column($d['bottom_by_qty'], 'revenue'));
  @endphp

  body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: {{ $ink }}; margin: 0; }
  .section { margin: 0 24px 18px 24px; }
  .section-title { font-size: 13pt; font-weight: bold; border-bottom: 2px solid {{ $brand }}; padding-bottom: 5px; margin-bottom: 10px; }
  .section-title .tag { float: right; font-size: 8pt; color: {{ $muted }}; font-weight: normal; }
  .card-box { border: 1px solid {{ $grid }}; border-radius: 6px; padding: 10px 12px; background: #fcfcfb; }
  .callout { background: #fff7ef; border: 1px solid #f3d9c2; border-left: 3px solid {{ $brand }}; border-radius: 4px; padding: 8px 10px; font-size: 9pt; }
  table.dtable { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
  table.dtable th { text-align: left; font-size: 7.5pt; text-transform: uppercase; color: {{ $muted }}; border-bottom: 1px solid #c3c2b7; padding: 4px 6px; }
  table.dtable td { padding: 4px 6px; border-bottom: 1px solid {{ $grid }}; }
  table.dtable .num { text-align: right; }
  .hbar-label { font-size: 8pt; }
  .hbar-track { background: #eef1f5; border-radius: 3px; height: 11px; }
  .hbar-fill { height: 11px; border-radius: 3px; }
  .hbar-value { font-size: 8pt; font-weight: bold; color: {{ $ink2 }}; }
</style>
</head>
<body>

  <!-- COVER -->
  <div style="text-align:center; padding-top: 90px;">
    <div style="font-size: 30px; font-weight: bold; color: {{ $brand }};">ROZE</div>
    <div style="font-size: 13px; letter-spacing: 4px; color: {{ $ink }};">SKINCARE</div>
    <div style="width: 50px; height: 3px; background: {{ $brand }}; margin: 24px auto;"></div>
    <div style="font-size: 22px; font-weight: bold; margin-top: 40px;">Sales Performance Report</div>
    <div style="font-size: 11px; color: {{ $ink2 }}; margin-top: 8px;">Product-level sales analysis — best &amp; worst performers, revenue trend, and channel mix</div>
    <div style="display:inline-block; margin-top: 20px; background: {{ $brand }}; color: #fff; font-weight: bold; font-size: 10px; padding: 6px 18px; border-radius: 999px;">
      {{ $d['range']['from'] }} &nbsp;to&nbsp; {{ $d['range']['to'] }}
    </div>
    <div style="margin-top: 70px; font-size: 8.5px; color: {{ $muted }}; line-height: 1.8;">
      Prepared for Roze Skincare Dubai &middot; Invoice App Analytics<br>
      Generated {{ $generated_at }}<br>
      Source: {{ $num($d['summary']['order_count']) }} completed invoices (Cancelled &amp; Returned excluded)
    </div>
  </div>
  <div style="page-break-before: always;"></div>

  <!-- EXECUTIVE SUMMARY -->
  <div class="section">
    <div class="section-title">Executive Summary <span class="tag">{{ $d['range']['from'] }} – {{ $d['range']['to'] }}</span></div>
    <table style="width:100%; border-collapse:separate; border-spacing: 6px 0;">
      <tr>
        <td width="33%" class="card-box">
          <div style="font-size:7.5pt; color:{{ $muted }}; text-transform:uppercase;">Total Revenue</div>
          <div style="font-size:16pt; font-weight:bold;">{{ $money($d['summary']['total_revenue']) }}</div>
          <div style="font-size:8pt; color: {{ $revDelta >= 0 ? $good : $crit }};">{{ $revDelta >= 0 ? '▲' : '▼' }} {{ number_format(abs($revDelta), 1) }}% vs prior period</div>
        </td>
        <td width="33%" class="card-box">
          <div style="font-size:7.5pt; color:{{ $muted }}; text-transform:uppercase;">Total Orders</div>
          <div style="font-size:16pt; font-weight:bold;">{{ $num($d['summary']['order_count']) }}</div>
          <div style="font-size:8pt; color:{{ $muted }};">{{ $num($d['summary']['unique_customers']) }} unique customers</div>
        </td>
        <td width="33%" class="card-box">
          <div style="font-size:7.5pt; color:{{ $muted }}; text-transform:uppercase;">Items Sold</div>
          <div style="font-size:16pt; font-weight:bold;">{{ $num($d['summary']['total_items_sold']) }}</div>
          <div style="font-size:8pt; color:{{ $muted }};">across {{ $d['summary']['unique_products'] }} products</div>
        </td>
      </tr>
    </table>
    <table style="width:100%; border-collapse:separate; border-spacing: 6px 0; margin-top:6px;">
      <tr>
        <td width="33%" class="card-box">
          <div style="font-size:7.5pt; color:{{ $muted }}; text-transform:uppercase;">Avg Order Value</div>
          <div style="font-size:14pt; font-weight:bold;">{{ $money($d['summary']['avg_order_value']) }}</div>
        </td>
        <td width="33%" class="card-box">
          <div style="font-size:7.5pt; color:{{ $muted }}; text-transform:uppercase;">Best Seller (units)</div>
          <div style="font-size:10pt; font-weight:bold;">{{ $short($d['top_by_qty'][0]['name'] ?? '—', 32) }}</div>
          <div style="font-size:8pt; color:{{ $muted }};">{{ $d['top_by_qty'][0]['qty'] ?? 0 }} units sold</div>
        </td>
        <td width="33%" class="card-box">
          <div style="font-size:7.5pt; color:{{ $muted }}; text-transform:uppercase;">Top Revenue Product</div>
          <div style="font-size:10pt; font-weight:bold;">{{ $short($d['top_by_revenue'][0]['name'] ?? '—', 32) }}</div>
          <div style="font-size:8pt; color:{{ $muted }};">{{ $money($d['top_by_revenue'][0]['revenue'] ?? 0) }}</div>
        </td>
      </tr>
    </table>
  </div>

  <!-- TREND -->
  <div class="section">
    <div class="section-title">Weekly Revenue Trend</div>
    <div class="card-box">
      {!! $trendHtml !!}
    </div>
    @if($peak)
    <div class="callout" style="margin-top:8px;">
      <strong>{{ $peak['date'] }}</strong> shows the largest single-day revenue ({{ $num($peak['orders']) }} orders / {{ $money($peak['revenue']) }}) — roughly {{ $peakMultiple }}&times; the daily average.
      Worth reviewing what drove it so it can be reproduced deliberately.
    </div>
    @endif
  </div>

  <!-- MONTH COMPARISON -->
  <div class="section">
    <div class="section-title">Period Comparison <span class="tag">First half vs second half</span></div>
    <table style="width:100%; border-collapse:separate; border-spacing: 6px 0;">
      <tr>
        <td width="50%" class="card-box">
          <div style="font-size:7.5pt; color:{{ $muted }}; text-transform:uppercase; font-weight:bold;">First Half</div>
          <div style="font-size:15pt; font-weight:bold; margin-top:4px;">{{ $money($m1['revenue']) }}</div>
          <div style="font-size:8pt; color:{{ $ink2 }};">{{ $num($m1['orders']) }} orders</div>
        </td>
        <td width="50%" class="card-box">
          <div style="font-size:7.5pt; color:{{ $muted }}; text-transform:uppercase; font-weight:bold;">Second Half</div>
          <div style="font-size:15pt; font-weight:bold; margin-top:4px; color: {{ $revDelta >= 0 ? $good : $crit }};">{{ $money($m2['revenue']) }}</div>
          <div style="font-size:8pt; color:{{ $ink2 }};">{{ $num($m2['orders']) }} orders &middot; {{ $revDelta >= 0 ? '▲' : '▼' }} {{ number_format(abs($revDelta), 1) }}%</div>
        </td>
      </tr>
    </table>
  </div>

  <div style="page-break-before: always;"></div>

  <!-- TOP BY QTY -->
  <div class="section">
    <div class="section-title">Top 10 Best-Selling Products — by Units Sold <span class="tag">Volume leaders</span></div>
    <div class="card-box">
      @php $maxQ = max(array_column($d['top_by_qty'], 'qty') ?: [1]); @endphp
      @foreach($d['top_by_qty'] as $p)
        <table style="width:100%; margin-bottom:4px;"><tr>
          <td width="38%" class="hbar-label">{{ $short($p['name'], 34) }}</td>
          <td width="45%"><div class="hbar-track"><div class="hbar-fill" style="width:{{ max(2, ($p['qty']/$maxQ)*100) }}%; background:#2a78d6;"></div></div></td>
          <td width="17%" class="hbar-value" style="text-align:right;">{{ $num($p['qty']) }} units</td>
        </tr></table>
      @endforeach
    </div>
  </div>
  <div class="section">
    <table class="dtable">
      <thead><tr><th>#</th><th>Product</th><th class="num">Qty</th><th class="num">Revenue</th><th class="num">Orders</th></tr></thead>
      <tbody>
      @foreach($d['top_by_qty'] as $i => $p)
        <tr><td>{{ $i+1 }}</td><td>{{ $short($p['name'], 62) }}</td><td class="num">{{ $num($p['qty']) }}</td><td class="num">{{ $money($p['revenue']) }}</td><td class="num">{{ $num($p['orders']) }}</td></tr>
      @endforeach
      </tbody>
    </table>
  </div>

  <div style="page-break-before: always;"></div>

  <!-- TOP BY REVENUE -->
  <div class="section">
    <div class="section-title">Top 10 Products — by Revenue <span class="tag">Value leaders</span></div>
    <div class="card-box">
      @php $maxR = max(array_column($d['top_by_revenue'], 'revenue') ?: [1]); @endphp
      @foreach($d['top_by_revenue'] as $p)
        <table style="width:100%; margin-bottom:4px;"><tr>
          <td width="38%" class="hbar-label">{{ $short($p['name'], 34) }}</td>
          <td width="45%"><div class="hbar-track"><div class="hbar-fill" style="width:{{ max(2, ($p['revenue']/$maxR)*100) }}%; background:#eb6834;"></div></div></td>
          <td width="17%" class="hbar-value" style="text-align:right;">{{ $money($p['revenue']) }}</td>
        </tr></table>
      @endforeach
    </div>
  </div>
  <div class="section">
    <table class="dtable">
      <thead><tr><th>#</th><th>Product</th><th class="num">Qty</th><th class="num">Revenue</th><th class="num">Orders</th></tr></thead>
      <tbody>
      @foreach($d['top_by_revenue'] as $i => $p)
        <tr><td>{{ $i+1 }}</td><td>{{ $short($p['name'], 62) }}</td><td class="num">{{ $num($p['qty']) }}</td><td class="num">{{ $money($p['revenue']) }}</td><td class="num">{{ $num($p['orders']) }}</td></tr>
      @endforeach
      </tbody>
    </table>
  </div>

  <div style="page-break-before: always;"></div>

  <!-- LEAST SELLING -->
  <div class="section">
    <div class="section-title">Least-Selling Products <span class="tag">Review for bundling / discount / discontinuation</span></div>
    <table class="dtable">
      <thead><tr><th>#</th><th>Product</th><th class="num">Qty</th><th class="num">Revenue</th><th class="num">Orders</th></tr></thead>
      <tbody>
      @foreach($d['bottom_by_qty'] as $i => $p)
        <tr><td>{{ $i+1 }}</td><td>{{ $short($p['name'], 62) }}</td><td class="num">{{ $num($p['qty']) }}</td><td class="num">{{ $money($p['revenue']) }}</td><td class="num">{{ $num($p['orders']) }}</td></tr>
      @endforeach
      </tbody>
    </table>
    @if(count($d['bottom_by_qty']))
    <div class="callout" style="margin-top:8px;">
      <strong>{{ count($d['bottom_by_qty']) }} of {{ $d['summary']['unique_products'] }} products</strong> sold in the single digits over this period, contributing only {{ $money($bottomRevSum) }} — under {{ $pct($bottomRevSum, $d['summary']['total_revenue']) }} of total revenue.
      Consider bundling, discounting, or trimming these from the catalog.
    </div>
    @endif
  </div>

  <div style="page-break-before: always;"></div>

  <!-- CHANNEL MIX -->
  <div class="section">
    <div class="section-title">Sales Channel Mix <span class="tag">{{ $num($d['summary']['order_count']) }} orders</span></div>
    <table style="width:100%;"><tr>
      <td width="50%" style="vertical-align:top; padding-right:6px;">
        <div class="card-box">
          <div style="font-size:9pt; font-weight:bold; margin-bottom:6px;">Delivery Service</div>
          @php $maxD = max(array_column($d['channels']['delivery_service'], 'count') ?: [1]); @endphp
          @foreach($d['channels']['delivery_service'] as $i => $c)
            <table style="width:100%; margin-bottom:3px;"><tr>
              <td width="35%" class="hbar-label">{{ $short($c['label'], 20) }}</td>
              <td width="40%"><div class="hbar-track"><div class="hbar-fill" style="width:{{ max(2, ($c['count']/$maxD)*100) }}%; background:{{ $cat[$i % count($cat)] }};"></div></div></td>
              <td width="25%" class="hbar-value" style="text-align:right; font-size:7pt;">{{ $c['count'] }} &middot; {{ $pct($c['count'], $d['summary']['order_count']) }}</td>
            </tr></table>
          @endforeach
        </div>
      </td>
      <td width="50%" style="vertical-align:top; padding-left:6px;">
        <div class="card-box">
          <div style="font-size:9pt; font-weight:bold; margin-bottom:6px;">Order Source</div>
          @php $maxS = max(array_column($d['channels']['business_source'], 'count') ?: [1]); @endphp
          @foreach($d['channels']['business_source'] as $i => $c)
            <table style="width:100%; margin-bottom:3px;"><tr>
              <td width="35%" class="hbar-label">{{ $short($c['label'], 20) }}</td>
              <td width="40%"><div class="hbar-track"><div class="hbar-fill" style="width:{{ max(2, ($c['count']/$maxS)*100) }}%; background:{{ $cat[$i % count($cat)] }};"></div></div></td>
              <td width="25%" class="hbar-value" style="text-align:right; font-size:7pt;">{{ $c['count'] }} &middot; {{ $pct($c['count'], $d['summary']['order_count']) }}</td>
            </tr></table>
          @endforeach
        </div>
      </td>
    </tr></table>

    <div class="card-box" style="margin-top:8px;">
      <div style="font-size:9pt; font-weight:bold; margin-bottom:6px;">Payment Method</div>
      @php $maxP = max(array_column($d['channels']['payment_mode'], 'count') ?: [1]); @endphp
      @foreach($d['channels']['payment_mode'] as $i => $c)
        <table style="width:100%; margin-bottom:3px;"><tr>
          <td width="25%" class="hbar-label">{{ $short($c['label'], 22) }}</td>
          <td width="55%"><div class="hbar-track"><div class="hbar-fill" style="width:{{ max(2, ($c['count']/$maxP)*100) }}%; background:{{ $cat[$i % count($cat)] }};"></div></div></td>
          <td width="20%" class="hbar-value" style="text-align:right; font-size:7pt;">{{ $c['count'] }} &middot; {{ $pct($c['count'], $d['summary']['order_count']) }}</td>
        </tr></table>
      @endforeach
    </div>
  </div>

  <!-- PAYMENT STATUS -->
  <div class="section">
    <div class="section-title">Payment Status</div>
    <table style="width:100%; border-collapse:separate; border-spacing: 6px 0;"><tr>
      @foreach($d['status_count'] as $status => $count)
        @php $sc = ['Paid' => $good, 'Unpaid' => $warn, 'Cancelled' => $crit, 'Returned' => $muted][$status] ?? '#2a78d6'; @endphp
        <td width="25%" class="card-box" style="border-left: 3px solid {{ $sc }};">
          <div style="font-size:7.5pt; color:{{ $muted }}; text-transform:uppercase;">{{ $status }}</div>
          <div style="font-size:13pt; font-weight:bold;">{{ $num($count) }}</div>
          <div style="font-size:7.5pt; color:{{ $muted }};">{{ $pct($count, $d['summary']['order_count']) }}</div>
        </td>
      @endforeach
    </tr></table>
  </div>

  <div style="text-align:center; font-size:7.5pt; color:{{ $muted }}; margin-top: 24px; border-top: 1px solid {{ $grid }}; padding-top: 8px;">
    Roze Skincare — Sales Performance Report &middot; {{ $d['range']['from'] }} to {{ $d['range']['to'] }} &middot; Generated {{ $generated_at }} &middot; Internal use only
  </div>

</body>
</html>
