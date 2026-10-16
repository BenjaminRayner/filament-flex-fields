<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\FilamentFlexFieldsServiceProvider;
use Filament\Support\SupportServiceProvider;
use Illuminate\Support\ViewErrorBag;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Foundation\Application;

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('Compatibility probes are CLI-only.');
}
chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';
// Register the native Filament hook before Livewire freezes its hook registry.
// The older schema-only harness boots Livewire first and never exercises partials.
$app = Application::create(null, null, ['extra' => ['providers' => [
    SupportServiceProvider::class,
    FilamentFlexFieldsServiceProvider::class,
    LivewireServiceProvider::class,
]]]);
$app['view']->share('errors', new ViewErrorBag);
$app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('x', 32)));
$app['config']->set('filament-flex-fields.playground.enabled', false);
$app['config']->set('view.compiled', getcwd().'/build/core-framework-views');
if (! is_dir($app['config']->get('view.compiled'))) {
    mkdir($app['config']->get('view.compiled'), 0755, true);
}
set_exception_handler(static function (Throwable $exception): never {
    fwrite(STDERR, (string) $exception.PHP_EOL);
    exit(1);
});
