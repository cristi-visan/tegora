<?php

/**
 * Post-deploy tasks, called by the GitHub Action after the release is
 * unpacked, because the hosting has no command line we can rely on.
 *
 * The token travels in a header so it stays out of access logs. Without a
 * DEPLOY_TOKEN in the server's .env the route behaves as if it did not exist.
 *
 * Failures return only the stage and a short code (e.g. MySQL error 1045),
 * never the message: Actions logs on a public repo are public. The full
 * error is in storage/logs/system.log on the server.
 */
Route::post('/_tegora/post-deploy', function () {
    $expected = (string) env('DEPLOY_TOKEN', '');
    $given = (string) request()->header('X-Deploy-Token', '');

    if (strlen($expected) < 32 || !hash_equals($expected, $given)) {
        abort(404);
    }

    @set_time_limit(600);

    $stage = 'database';
    try {
        DB::connection()->getPdo();

        $stage = 'migrate';
        Artisan::call('october:migrate', ['--no-interaction' => true]);
        $stage = 'seed';
        Artisan::call('tegora:seed');
        $stage = 'discover';
        Artisan::call('package:discover');
        $stage = 'cache';
        Artisan::call('cache:clear');

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }
    catch (\Throwable $ex) {
        Log::error($ex);
        return response()->json(['status' => 'failed', 'stage' => $stage, 'code' => tegoraSafeErrorCode($ex)], 500);
    }

    return response()->json(['status' => 'ok']);
});

/**
 * A code that says what went wrong without revealing anything: a numeric
 * driver error (1045 access denied, 1049 unknown database, 2002 cannot reach
 * host), "driver_missing", or the exception's short class name.
 */
if (!function_exists("tegoraSafeErrorCode")) {
function tegoraSafeErrorCode(\Throwable $ex): string
{
    for ($e = $ex; $e; $e = $e->getPrevious()) {
        if (!$e instanceof \PDOException) {
            continue;
        }
        if (stripos($e->getMessage(), 'could not find driver') !== false) {
            return 'driver_missing';
        }
        if (is_int($e->getCode()) && $e->getCode() > 0) {
            return 'db_' . $e->getCode();
        }
        if (isset($e->errorInfo[1]) && is_numeric($e->errorInfo[1])) {
            return 'db_' . (int) $e->errorInfo[1];
        }
        if (preg_match('/SQLSTATE\[\w+\] \[(\d+)\]/', $e->getMessage(), $m)) {
            return 'db_' . $m[1];
        }
        if (preg_match('/^[0-9A-Z]{5}$/', (string) $e->getCode())) {
            return 'sqlstate_' . $e->getCode();
        }
    }
    return (new \ReflectionClass($ex))->getShortName();
}
}
