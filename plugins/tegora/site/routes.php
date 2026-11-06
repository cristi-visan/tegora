<?php

/**
 * Post-deploy tasks, called by the GitHub Action after the FTP sync because
 * the hosting has no command line we can rely on.
 *
 * The token travels in a header so it stays out of access logs. Without a
 * DEPLOY_TOKEN in the server's .env the route behaves as if it did not exist.
 */
Route::post('/_tegora/post-deploy', function () {
    $expected = (string) env('DEPLOY_TOKEN', '');
    $given = (string) request()->header('X-Deploy-Token', '');

    if (strlen($expected) < 32 || !hash_equals($expected, $given)) {
        abort(404);
    }

    @set_time_limit(600);

    try {
        Artisan::call('october:migrate', ['--no-interaction' => true]);
        Artisan::call('tegora:seed');
        Artisan::call('package:discover');
        Artisan::call('cache:clear');

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }
    catch (\Throwable $ex) {
        Log::error($ex);
        return response()->json(['status' => 'failed'], 500);
    }

    return response()->json(['status' => 'ok']);
});
