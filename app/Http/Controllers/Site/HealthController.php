<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Readiness probe for load balancers / uptime monitors: verifies that the app can reach its
 * database and cache. Returns no internals in the body; details go to the log.
 * (/up is Laravel's lighter liveness check.)
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check('database', fn () => DB::select('select 1')),
            'cache' => $this->check('cache', function () {
                Cache::put('health-check', 'ok', 10);

                return Cache::get('health-check') === 'ok';
            }),
        ];

        $healthy = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => array_map(fn (bool $ok) => $ok ? 'ok' : 'fail', $checks),
            'time' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    private function check(string $name, callable $probe): bool
    {
        try {
            return $probe() !== false;
        } catch (Throwable $e) {
            Log::error('health.check_failed', ['check' => $name, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
