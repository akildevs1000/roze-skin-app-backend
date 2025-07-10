<h2>📋 Laravel Daily Log Summary</h2>

@foreach($logSummaries as $log)
    <h4>{{ $log['filename'] }}</h4>
    <pre style="background-color:#f5f5f5;padding:10px;border-radius:5px;max-height:400px;overflow:auto;">
        {{ \Illuminate\Support\Str::limit($log['content'], 5000) }}
    </pre>
    <hr>
@endforeach

<p>📎 Log files are also attached (if not too large).</p>