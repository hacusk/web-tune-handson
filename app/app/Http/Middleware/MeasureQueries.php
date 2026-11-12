<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class MeasureQueries
{
    /**
     * @param  callable(): mixed  $run
     */
    public static function json(Request $request, callable $run, int $status = 200): JsonResponse
    {
        $queries = [];
        $start = hrtime(true);

        DB::listen(function ($query) use (&$queries) {
            $queries[] = [
                'sql' => $query->sql,
                'params' => $query->bindings,
            ];
        });

        $data = $run();
        if ($data === null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $meta = [
            'query_count' => count($queries),
            'elapsed_ms' => round((hrtime(true) - $start) / 1e6, 3),
        ];
        if ($request->query('debug') === '1') {
            $meta['queries'] = $queries;
        }

        return response()->json(['data' => $data, 'meta' => $meta], $status);
    }

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
