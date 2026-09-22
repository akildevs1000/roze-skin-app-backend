<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Summarises storage/logs/api_audit-*.log — which API routes are being called
 * without a valid Sanctum token, and by whom.
 *
 * This is the evidence used to decide which routes can safely be put behind
 * auth:sanctum without breaking a real caller.
 */
class ApiAuditReport extends Command
{
    protected $signature = 'api:audit-report
                            {--days=7 : How many days of logs to read}
                            {--path= : Only show entries for paths containing this string}';

    protected $description = 'Summarise anonymous (token-less) API traffic recorded by the audit middleware';

    public function handle(): int
    {
        $days  = max(1, (int) $this->option('days'));
        $files = [];

        for ($i = 0; $i < $days; $i++) {
            $file = storage_path('logs/api_audit-' . now()->subDays($i)->toDateString() . '.log');

            if (is_file($file)) {
                $files[] = $file;
            }
        }

        if (! $files) {
            $this->warn('No audit logs found. Has the middleware been deployed and had traffic yet?');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($files as $file) {
            $handle = fopen($file, 'r');

            while (($line = fgets($handle)) !== false) {
                if (! preg_match('/anonymous (\{.*\})\s*$/', trim($line), $m)) {
                    continue;
                }

                $entry = json_decode($m[1], true);

                if (! $entry) {
                    continue;
                }

                if ($filter = $this->option('path')) {
                    if (! str_contains($entry['path'] ?? '', $filter)) {
                        continue;
                    }
                }

                $key = ($entry['method'] ?? '?') . ' ' . ($entry['route'] ?? $entry['path'] ?? '?');

                $rows[$key] ??= ['count' => 0, 'ips' => [], 'origins' => [], 'agents' => []];
                $rows[$key]['count']++;
                $rows[$key]['ips'][$entry['ip'] ?? '?'] = true;
                $rows[$key]['origins'][$entry['origin'] ?? $entry['referer'] ?? '—'] = true;
                $rows[$key]['agents'][substr((string) ($entry['user_agent'] ?? '—'), 0, 40)] = true;
            }

            fclose($handle);
        }

        if (! $rows) {
            $this->info('No anonymous API calls recorded. Every caller is sending a valid token.');

            return self::SUCCESS;
        }

        uasort($rows, fn ($a, $b) => $b['count'] <=> $a['count']);

        $this->table(
            ['route', 'sightings', 'distinct IPs', 'origins', 'user agents'],
            collect($rows)->map(fn ($r, $route) => [
                $route,
                $r['count'],
                count($r['ips']),
                implode(', ', array_slice(array_keys($r['origins']), 0, 2)),
                implode(', ', array_slice(array_keys($r['agents']), 0, 2)),
            ])->values()->all()
        );

        $this->newLine();
        $this->line('Routes listed above are reached WITHOUT any token. Anything with a real');
        $this->line('origin/user-agent is a live caller — check it before locking that route down.');
        $this->newLine();
        $this->line('"sightings" is not a request count: each route/IP pair is recorded at most');
        $this->line('once an hour to keep the log small. Presence is the signal, not volume.');

        return self::SUCCESS;
    }
}
