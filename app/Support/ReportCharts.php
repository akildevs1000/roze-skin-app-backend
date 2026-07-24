<?php

namespace App\Support;

/**
 * Small inline-SVG chart builders for dompdf-rendered PDF reports.
 * Kept deliberately simple (no gradients/filters) — dompdf's SVG support
 * is reliable for flat fills, lines, and text but not for CSS-grade effects.
 */
class ReportCharts
{
    /** Daily revenue trend as a filled line chart. $daily = [['date'=>, 'revenue'=>], ...]. */
    public static function trend(array $daily, string $color = '#2a78d6', int $width = 760, int $height = 200): string
    {
        $padL = 46; $padR = 16; $padT = 14; $padB = 26;
        $w = $width - $padL - $padR;
        $h = $height - $padT - $padB;
        $n = count($daily);
        if ($n < 2) return '';

        $vals = array_column($daily, 'revenue');
        $max = max($vals) * 1.08;
        $max = $max > 0 ? $max : 1;

        $x = fn($i) => $padL + ($n === 1 ? 0 : ($i / ($n - 1)) * $w);
        $y = fn($v) => $padT + $h - ($v / $max) * $h;

        $pts = [];
        foreach ($daily as $i => $row) $pts[] = round($x($i), 1) . ',' . round($y($row['revenue']), 1);
        $linePts = implode(' ', $pts);
        $areaPts = round($x(0), 1) . ',' . round($padT + $h, 1) . ' ' . $linePts . ' ' . round($x($n - 1), 1) . ',' . round($padT + $h, 1);

        $gridlines = '';
        for ($i = 0; $i <= 4; $i++) {
            $v = ($max / 4) * $i;
            $yy = round($y($v), 1);
            $gridlines .= "<line x1=\"$padL\" y1=\"$yy\" x2=\"" . ($width - $padR) . "\" y2=\"$yy\" stroke=\"#e1e0d9\" stroke-width=\"1\"/>";
            $label = $v >= 1000 ? round($v / 1000) . 'K' : round($v);
            $gridlines .= "<text x=\"" . ($padL - 6) . "\" y=\"" . ($yy + 3) . "\" text-anchor=\"end\" font-size=\"9\" fill=\"#898781\">$label</text>";
        }

        $labelEvery = max(1, (int) ceil($n / 8));
        $xLabels = '';
        $lastLx = -1000;
        foreach ($daily as $i => $row) {
            if ($i % $labelEvery !== 0 && $i !== $n - 1) continue;
            $lx = round($x($i), 1);
            if ($lx - $lastLx < 40 && $i !== $n - 1) continue;
            if ($lx - $lastLx < 40 && $i === $n - 1) continue; // keep it simple in PDF: skip if would collide
            $lastLx = $lx;
            $lbl = substr($row['date'], 5);
            $xLabels .= "<text x=\"$lx\" y=\"" . ($height - 6) . "\" text-anchor=\"middle\" font-size=\"8.5\" fill=\"#898781\">$lbl</text>";
        }

        $peakIdx = array_keys($vals, max($vals))[0];
        $peak = $daily[$peakIdx];
        $peakX = round($x($peakIdx), 1);
        $peakY = round($y($peak['revenue']), 1);

        return "<svg width=\"$width\" height=\"$height\" viewBox=\"0 0 $width $height\" xmlns=\"http://www.w3.org/2000/svg\">"
            . $gridlines
            . "<polygon points=\"$areaPts\" fill=\"$color\" fill-opacity=\"0.12\"/>"
            . "<polyline points=\"$linePts\" fill=\"none\" stroke=\"$color\" stroke-width=\"2\"/>"
            . "<circle cx=\"$peakX\" cy=\"$peakY\" r=\"3.5\" fill=\"$color\"/>"
            . $xLabels
            . '</svg>';
    }
}
