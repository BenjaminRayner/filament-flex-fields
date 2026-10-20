<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3).'/scripts/core/export-php-api.php';

final class PublicApiThrowingDefault
{
    public function __construct()
    {
        throw new RuntimeException('Default expressions must never be evaluated.');
    }
}

final class PublicApiDefaultProbe
{
    public function __construct(public object $value = new PublicApiThrowingDefault) {}

    public function named(string $label = 'hello', ?int $amount = null): void {}
}

it('reads new-object default expressions without constructing them', function (): void {
    $method = new ReflectionMethod(PublicApiDefaultProbe::class, '__construct');
    $expression = apiParameterDefault($method, $method->getParameters()[0]);

    expect($expression)->toContain('new \PublicApiThrowingDefault');
});

it('preserves named defaults and explicit nullable parameter values', function (): void {
    $method = new ReflectionMethod(PublicApiDefaultProbe::class, 'named');

    expect(apiParameterDefault($method, $method->getParameters()[0]))->toBe("'hello'")
        ->and(apiParameterDefault($method, $method->getParameters()[1]))->toBe('null');
});
