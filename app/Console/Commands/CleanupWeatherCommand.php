<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Weather\Services\DailySummaryService;
use App\Weather\Services\RetentionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Daily maintenance: rebuild recent daily summaries, then prune detailed
 * observations older than the retention window. Forecast and summary data are
 * never pruned here.
 */
class CleanupWeatherCommand extends Command
{
    protected $signature = 'weather:cleanup';

    protected $description = 'Aggregate recent days and prune observations beyond the retention window';

    public function handle(RetentionService $retention, DailySummaryService $summaries): int
    {
        // Ensure summaries exist for everything we are about to (potentially)
        // prune, so daily history is preserved past retention.
        $retentionDays = max(1, (int) config('weather.retention_days', 10));
        $rebuilt = $summaries->rebuildRecent($retentionDays + 1);

        $result = $retention->prune();

        $message = sprintf(
            'Cleanup complete: pruned %d observation(s) older than %d day(s) (cutoff %s); rebuilt %d daily summary/summaries.',
            $result['deleted'],
            $result['retention_days'],
            $result['cutoff'],
            $rebuilt->count(),
        );

        $this->info($message);
        Log::info('Weather cleanup ran.', $result + ['summaries_rebuilt' => $rebuilt->count()]);

        return self::SUCCESS;
    }
}
