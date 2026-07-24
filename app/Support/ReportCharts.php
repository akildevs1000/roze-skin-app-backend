<?php

namespace App\Support;

/**
 * Small HTML/CSS chart builder for dompdf-rendered PDF reports.
 * Deliberately table/div-based, not SVG — dompdf renders inline <svg> as
 * flattened text (confirmed by manual render check), but its div/table box
 * model (used everywhere else in this report) is solid.
 */
class ReportCharts
{
    /**
     * Daily revenue bucketed into weekly horizontal bars — trades daily
     * granularity for a chart form dompdf actually renders correctly.
     * $daily = [['date'=>'Y-m-d', 'revenue'=>float, 'orders'=>int], ...].
     */
    public static function weeklyRevenueBars(array $daily, string $color = '#2a78d6'): string
    {
        if (empty($daily)) return '';

        $weeks = [];
        foreach ($daily as $row) {
            $weekStart = \Carbon\Carbon::parse($row['date'])->startOfWeek(\Carbon\Carbon::SUNDAY);
            $key = $weekStart->toDateString();
            if (! isset($weeks[$key])) {
                $weeks[$key] = ['label' => $weekStart->format('d M'), 'revenue' => 0.0, 'orders' => 0];
            }
            $weeks[$key]['revenue'] += $row['revenue'];
            $weeks[$key]['orders'] += $row['orders'];
        }
        ksort($weeks);

        $max = max(array_column($weeks, 'revenue')) ?: 1;
        $money = fn($n) => 'AED ' . number_format($n, 0);

        $rows = '';
        foreach ($weeks as $w) {
            $pctW = max(2, round(($w['revenue'] / $max) * 100));
            $rows .= '<table style="width:100%; margin-bottom:4px;"><tr>'
                . '<td width="18%" style="font-size:8pt;">' . e($w['label']) . '</td>'
                . '<td width="62%"><div style="background:#eef1f5; border-radius:3px; height:12px;">'
                . '<div style="width:' . $pctW . '%; background:' . $color . '; height:12px; border-radius:3px;"></div>'
                . '</div></td>'
                . '<td width="20%" style="text-align:right; font-size:8pt; font-weight:bold; color:#52514e;">' . $money($w['revenue']) . '</td>'
                . '</tr></table>';
        }

        return $rows;
    }
}
