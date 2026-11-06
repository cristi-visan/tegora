<?php
/**
 * Unpacks a release uploaded by the GitHub Action (_deploy/release.zip) into the
 * site root. Standalone on purpose: it must work before the app itself exists
 * on the server, and while the app's files are being replaced.
 *
 * POST with header X-Deploy-Token (must equal DEPLOY_TOKEN in the site's .env).
 *   ?step=extract&from=N  extracts entries from index N for up to ~15 seconds,
 *                         returns {"next": M} until {"done": true}
 *   ?step=finish          deletes files dropped since the previous release,
 *                         clears framework metadata caches, removes the zip
 *
 * Only files listed in a previous release manifest are ever deleted, so .env,
 * uploads, logs and anything else the server created are never touched.
 */

header('Content-Type: application/json');

$root = dirname(__DIR__);
$dir = __DIR__;
$zipPath = $dir . '/release.zip';
$manifestName = '.release-manifest';
$previousManifest = $dir . '/manifest.txt';

function respond(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body);
    exit;
}

function deployToken(string $envFile): string
{
    if (!is_file($envFile)) {
        return '';
    }
    foreach (file($envFile, FILE_IGNORE_NEW_LINES) as $line) {
        if (preg_match('/^\s*DEPLOY_TOKEN\s*=\s*"?([^"\s#]*)"?/', $line, $m)) {
            return $m[1];
        }
    }
    return '';
}

$expected = deployToken($root . '/.env');
$given = (string) ($_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || strlen($expected) < 32 || !hash_equals($expected, $given)) {
    respond(404, ['status' => 'not found']);
}

if (!class_exists('ZipArchive')) {
    respond(500, ['status' => 'failed', 'error' => 'PHP zip extension is not enabled']);
}

@set_time_limit(60);
$step = $_GET['step'] ?? 'extract';

if ($step === 'extract') {
    $zip = new ZipArchive;
    if ($zip->open($zipPath) !== true) {
        respond(500, ['status' => 'failed', 'error' => 'release.zip missing or unreadable']);
    }

    $from = max(0, (int) ($_GET['from'] ?? 0));
    $total = $zip->numFiles;
    // Time budget per call, kept well under common 30-second PHP limits
    $budget = min(15, max(1, (int) ($_GET['budget'] ?? 15)));
    $deadline = microtime(true) + $budget;
    $i = $from;

    for (; $i < $total; $i++) {
        $name = $zip->getNameIndex($i);

        // Refuse anything that could escape the site root
        if ($name === false || strpos($name, '..') !== false || $name[0] === '/' || strpos($name, ':') !== false) {
            respond(500, ['status' => 'failed', 'error' => "unsafe entry at $i"]);
        }

        if (!$zip->extractTo($root, $name)) {
            respond(500, ['status' => 'failed', 'error' => "could not write $name"]);
        }

        if (microtime(true) > $deadline) {
            $i++;
            break;
        }
    }
    $zip->close();

    respond(200, $i >= $total
        ? ['status' => 'ok', 'done' => true, 'files' => $total]
        : ['status' => 'ok', 'done' => false, 'next' => $i, 'files' => $total]);
}

if ($step === 'finish') {
    $newList = is_file($root . '/' . $manifestName)
        ? file($root . '/' . $manifestName, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
        : [];
    if (!$newList) {
        respond(500, ['status' => 'failed', 'error' => 'release manifest missing']);
    }

    $deleted = 0;
    if (is_file($previousManifest)) {
        $keep = array_flip($newList);
        foreach (file($previousManifest, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $old) {
            if (isset($keep[$old]) || strpos($old, '..') !== false || $old[0] === '/') {
                continue;
            }
            if (is_file($root . '/' . $old) && @unlink($root . '/' . $old)) {
                $deleted++;
            }
        }
    }

    copy($root . '/' . $manifestName, $previousManifest);
    @unlink($root . '/' . $manifestName);
    @unlink($zipPath);

    // Package and class metadata are rebuilt on the next request
    foreach (['storage/framework/packages.php', 'storage/framework/services.php', 'storage/framework/classes.php', 'storage/cms/manifest.php', '.ftp-deploy-sync-state.json'] as $meta) {
        @unlink($root . '/' . $meta);
    }

    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }

    respond(200, ['status' => 'ok', 'deleted' => $deleted]);
}

respond(400, ['status' => 'failed', 'error' => 'unknown step']);
