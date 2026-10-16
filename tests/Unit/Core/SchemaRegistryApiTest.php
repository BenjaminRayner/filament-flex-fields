<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Data\FlexFieldSchema;
use Bjanczak\FilamentFlexFields\Support\FlexFieldSchemaRegistry;

it('invalidates field memoization when replacing its lazy loader', function (): void {
    $registry = new FlexFieldSchemaRegistry;
    $registry->loadUsing(static function (string $target) use ($registry): void {
        $registry->register(FlexFieldSchema::make('one', $target)->fields([
            ['slug' => 'old', 'label' => 'Old', 'type' => 'single_line_text'],
        ]));
    });
    expect($registry->fieldsForTarget('post')[0]->slug)->toBe('old');
    $calls = 0;
    $registry->loadUsing(static function (string $target) use ($registry, &$calls): void {
        $calls++;
        $registry->register(FlexFieldSchema::make('one', $target)->fields([
            ['slug' => 'new', 'label' => 'New', 'type' => 'single_line_text'],
        ]));
    });
    expect($registry->fieldsForTarget('post')[0]->slug)->toBe('new')
        ->and($registry->fieldsForTarget('post')[0]->slug)->toBe('new')
        ->and($calls)->toBe(1);
});

it('rejects an invalid schema batch without partially replacing the registry', function (): void {
    $registry = new FlexFieldSchemaRegistry;
    $original = FlexFieldSchema::make('existing', 'post');
    $registry->register($original);
    expect(fn () => $registry->registerFromConfig([
        'existing' => ['target' => 'different'],
        'invalid' => ['target' => 'post', 'fields' => [
            ['slug' => 'broken', 'label' => 'Broken', 'type' => 'unknown-field-type'],
        ]],
    ]))->toThrow(InvalidArgumentException::class)
        ->and($registry->find('existing'))->toBe($original)
        ->and($registry->find('invalid'))->toBeNull();
});

it('retries a failed lazy loader instead of caching its failure', function (): void {
    $registry = new FlexFieldSchemaRegistry;
    $attempts = 0;
    $registry->loadUsing(static function (string $target) use ($registry, &$attempts): void {
        if (++$attempts === 1) {
            throw new RuntimeException('temporary database failure');
        }
        $registry->register(FlexFieldSchema::make('recovered', $target));
    });
    expect(fn () => $registry->forTarget('post'))->toThrow(RuntimeException::class)
        ->and($registry->forTarget('post'))->toHaveCount(1)
        ->and($registry->forTarget('post'))->toHaveCount(1)
        ->and($attempts)->toBe(2);
});
