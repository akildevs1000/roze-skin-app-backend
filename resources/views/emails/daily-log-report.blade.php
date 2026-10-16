<h2>📋 Daily Log Summary</h2>

{{-- The body used to be this heading alone, while every log file was both
     attached and serialised into the queued job. Listing the files here means
     the email still says what is in the logs when a file is too large to
     attach, instead of arriving empty or not at all. --}}

<p style="font:13px/1.5 Arial,sans-serif;color:#555;margin:0 0 12px">
    {{ now()->format('D, d M Y') }} &middot; {{ count($logSummaries) }} log file(s) in storage/logs
</p>

<table cellpadding="7" cellspacing="0"
       style="border-collapse:collapse;font:13px/1.4 Arial,sans-serif">
    <tr style="background:#f4f4f4">
        <th align="left" style="border:1px solid #ddd">File</th>
        <th align="right" style="border:1px solid #ddd">Size</th>
        <th align="left" style="border:1px solid #ddd">Attached</th>
    </tr>
    @foreach ($logSummaries as $log)
        <tr>
            <td style="border:1px solid #ddd">{{ $log['filename'] }}</td>
            <td align="right" style="border:1px solid #ddd">
                {{ number_format(($log['size'] ?? 0) / 1024, 1) }} KB
            </td>
            <td style="border:1px solid #ddd">
                @if (! empty($log['attached']))
                    yes
                @else
                    <span style="color:#b5472f">too large &mdash; on the server</span>
                @endif
            </td>
        </tr>
    @endforeach
</table>
