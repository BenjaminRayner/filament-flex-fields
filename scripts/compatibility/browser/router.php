<?php

// Only fixed fixture files and published assets are served on the loopback host.
$fixture = getenv('FFF_ASSET_FIXTURE');
$package = dirname(__DIR__, 3);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = null;
if ($path === '/') {
    $file = $fixture.'/index.html';
} elseif (preg_match('~^/[a-z0-9-]+\.js$~D', $path)) {
    $file = $fixture.$path;
} elseif (str_starts_with($path, '/dist/')) {
    $file = $package.'/resources'.$path;
} elseif (preg_match('~^/css/janczakb/filament-flex-fields/flex-fields-([a-z0-9-]+)\.css$~D', $path, $matches)) {
    $file = $package.'/resources/dist/css/'.$matches[1].'.css';
} elseif (preg_match('~^/js/janczakb/filament-flex-fields/components/([a-zA-Z0-9_.-]+)\.js$~D', $path, $matches)) {
    $file = $package.'/resources/dist/components/'.$matches[1].'.js';
}
$actual = $file ? realpath($file) : false;
if (! $actual || (! str_starts_with($actual, realpath($fixture).'/') && ! str_starts_with($actual, $package.'/resources/dist/'))) {
    http_response_code(404);
    exit;
}
header('Content-Type: '.match (pathinfo($actual, PATHINFO_EXTENSION)) {
    'css' => 'text/css', 'js' => 'text/javascript', default => 'text/html'
});
readfile($actual);
