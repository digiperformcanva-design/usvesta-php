<?php
/**
 * US Vesta – PHP front controller (port of the Django project).
 */
define('BASE_PATH', __DIR__);

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    echo '<h1>PHP 8.1 or newer is required</h1><p>This server runs PHP ' . PHP_VERSION . '. In hPanel open <b>Advanced &rarr; PHP Configuration</b> and select PHP 8.2 (or newer).</p>';
    exit;
}

// php -S (local development): let the built-in server serve real static/media files directly
if (PHP_SAPI === 'cli-server') {
    $p = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
    if (preg_match('#^/(static|media)/#', $p) && is_file(__DIR__ . $p) && !preg_match('/\.php$/i', $p)) return false;
    if (in_array($p, ['/robots.txt', '/favicon.ico'], true) && is_file(__DIR__ . $p)) return false;
}

require __DIR__ . '/app/bootstrap.php';

ob_start();
$req = null;
try {
    session_boot();
    Migrator::ensure();
    $req = new Request();
    $router = require __DIR__ . '/app/routes.php';
    $resp = $router->dispatch($req);
} catch (Http404 $e) {
    $resp = error_response($req ?? new Request(), 404);
} catch (Throwable $e) {
    log_error(get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
    if (config('debug')) {
        $resp = new Response('<pre style="white-space:pre-wrap">' . e(get_class($e) . ': ' . $e->getMessage() . "\n\n" . $e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString()) . '</pre>', 500);
    } else {
        $resp = error_response($req ?? new Request(), 500);
    }
}
$stray = ob_get_clean();
if ($stray !== '' && config('debug')) log_error('stray output: ' . substr($stray, 0, 500));
$resp->send();
run_after_response();

function error_response(Request $req, int $code): Response
{
    try {
        return render($req, $code === 404 ? '404.html' : '500.html', [], $code);
    } catch (Throwable $e) {
        log_error('error page failed: ' . $e->getMessage());
        return new Response($code === 404 ? '<h1>Page not found</h1>' : '<h1>Server error</h1>', $code);
    }
}
