<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\Components\Routing\ComponentRoute;
use Bjanczak\FilamentFlexFields\Support\Components\Routing\ComponentRouteRegistrar;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;

class FlexRouteTestController
{
    public function __invoke(): string
    {
        return 'ready';
    }

    protected function hidden(): void {}
}

it('defaults to authenticated writes and generates stable component names', function () {
    $route = new ComponentRoute('submit', 'submit/{record}', FlexRouteTestController::class);
    expect($route->methods)->toBe(['POST'])->and($route->middleware)->toBe(['web', 'auth'])
        ->and($route->nameFor('vendor-widget'))->toBe('filament-flex-fields.components.vendor-widget.submit');
    expect((new ComponentRoute('read', 'read', FlexRouteTestController::class, methods: ['GET']))->methods)->toBe(['GET', 'HEAD']);
});

it('requires explicit public access', function () {
    expect(fn () => new ComponentRoute('read', 'read', FlexRouteTestController::class, middleware: ['web']))->toThrow(InvalidArgumentException::class);
    expect((new ComponentRoute('read', 'read', FlexRouteTestController::class, middleware: ['web'], public: true))->public)->toBeTrue();
});

it('rejects malformed routes and constraints', function (array $options) {
    expect(fn () => new ComponentRoute(...['id' => 'read', 'controller' => FlexRouteTestController::class, ...$options]))->toThrow(InvalidArgumentException::class);
})->with([
    [['uri' => '../read']], [['uri' => '/read']], [['uri' => 'read/{id}/{id}']],
    [['uri' => 'read/{id}', 'where' => ['missing' => '[0-9]+']]],
    [['uri' => 'read/{id}', 'where' => ['id' => '[']]],
    [['uri' => 'read', 'middleware' => [['invalid']]]],
    [['uri' => 'read', 'methods' => ['INVALID']]],
]);

it('rejects duplicate names and structural paths without changing the registry', function () {
    $registry = new ComponentRegistry;
    $registry->registerBatch([new ComponentDefinition('existing', ComponentKind::Primitive)]);
    foreach ([
        [new ComponentRoute('a', 'records/{id}', FlexRouteTestController::class), new ComponentRoute('b', 'records/{slug}', FlexRouteTestController::class)],
        [new ComponentRoute('a', 'one', FlexRouteTestController::class, name: 'vendor.shared'), new ComponentRoute('b', 'two', FlexRouteTestController::class, name: 'vendor.shared')],
    ] as $routes) {
        expect(fn () => $registry->registerBatch([
            new ComponentDefinition('first', ComponentKind::Primitive, routes: [$routes[0]]),
            new ComponentDefinition('second', ComponentKind::Primitive, routes: [$routes[1]]),
        ]))->toThrow(LogicException::class);
        expect(array_keys($registry->all()))->toBe(['existing']);
    }
});

it('registers a configured external controller exactly once and preserves mandatory middleware', function () {
    config()->set('vendor.widget.prefix', 'custom/widget');
    config()->set('vendor.widget.middleware', ['web', 'auth:admin']);
    $registry = new ComponentRegistry;
    $registry->registerBatch([new ComponentDefinition('vendor-widget', ComponentKind::Primitive, routes: [
        new ComponentRoute('read', 'read/{record}', FlexRouteTestController::class, methods: ['GET'], prefixConfig: 'vendor.widget.prefix', middlewareConfig: 'vendor.widget.middleware', where: ['record' => '[0-9]+'], requiredMiddleware: ['signed:relative']),
    ])]);
    $registry->freeze();
    $registrar = new ComponentRouteRegistrar;
    $before = count(app(Router::class)->getRoutes());
    $registrar->register(app(), $registry);
    $registrar->register(app(), $registry);
    $route = app(Router::class)->getRoutes()->getByName('filament-flex-fields.components.vendor-widget.read');
    expect(count(app(Router::class)->getRoutes()))->toBe($before + 1)
        ->and($route->uri())->toBe('custom/widget/read/{record}')
        ->and($route->gatherMiddleware())->toBe(['web', 'auth:admin', 'signed:relative'])
        ->and($route->defaults['_fff_component'])->toBe('vendor-widget');
    $collection = new RouteCollection;
    $collection->add($route);
    $compiled = unserialize(serialize($collection->compile()));
    expect($compiled['attributes'])->toHaveKey($route->getName());
});

it('preflights every controller and collision before touching the router', function (string $failure) {
    $router = app(Router::class);
    if ($failure === 'collision') {
        $router->post('flex-fields/taken/{id}', [FlexRouteTestController::class, '__invoke']);
    }
    $registry = new ComponentRegistry;
    $registry->registerBatch([new ComponentDefinition('vendor-widget', ComponentKind::Primitive, routes: [
        new ComponentRoute('good', 'unique-first', FlexRouteTestController::class),
        new ComponentRoute('bad', $failure === 'collision' ? 'taken/{slug}' : 'unique-second', FlexRouteTestController::class, action: $failure === 'collision' ? '__invoke' : 'hidden'),
    ])]);
    $registry->freeze();
    $before = count($router->getRoutes());
    expect(fn () => (new ComponentRouteRegistrar)->register(app(), $registry))->toThrow(LogicException::class);
    expect(count($router->getRoutes()))->toBe($before);
})->with(['collision', 'controller']);

it('refuses configuration that removes authentication', function () {
    config()->set('vendor.widget.middleware', ['web']);
    $registry = new ComponentRegistry;
    $registry->registerBatch([new ComponentDefinition('vendor-widget', ComponentKind::Primitive, routes: [
        new ComponentRoute('read', 'read', FlexRouteTestController::class, middlewareConfig: 'vendor.widget.middleware'),
    ])]);
    $registry->freeze();
    expect(fn () => (new ComponentRouteRegistrar)->register(app(), $registry))->toThrow(InvalidArgumentException::class);
});

it('keeps cached routes untouched and rejects mutable snapshots', function () {
    $registry = new ComponentRegistry;
    expect(fn () => (new ComponentRouteRegistrar)->register(app(), $registry))->toThrow(LogicException::class);
    $registry->freeze();
    app()->instance('routes.cached', true);
    $count = count(app(Router::class)->getRoutes());
    (new ComponentRouteRegistrar)->register(app(), $registry);
    expect(count(app(Router::class)->getRoutes()))->toBe($count);
});

it('rejects equivalent domain templates in an atomic batch', function () {
    $registry = new ComponentRegistry;
    expect(fn () => $registry->registerBatch([
        new ComponentDefinition('first', ComponentKind::Primitive, routes: [new ComponentRoute('read', 'read', FlexRouteTestController::class, domain: '{tenant}.example.com')]),
        new ComponentDefinition('second', ComponentKind::Primitive, routes: [new ComponentRoute('read', 'read', FlexRouteTestController::class, domain: '{account}.example.com')]),
    ]))->toThrow(LogicException::class);
    expect($registry->all())->toBe([]);
});

it('loads a typed route file through the same component declaration checks', function () {
    $routes = require dirname(__DIR__, 2).'/resources/routes/link-preview-field.php';
    expect($routes)->toHaveCount(2)->and($routes[0])->toBeInstanceOf(ComponentRoute::class);
    expect(new ComponentDefinition('route-file-owner', ComponentKind::Primitive, routes: $routes))->routes->toBe($routes);
    expect(fn () => new ComponentDefinition('invalid-file-owner', ComponentKind::Primitive, routes: [static fn () => null]))->toThrow(InvalidArgumentException::class);
});
