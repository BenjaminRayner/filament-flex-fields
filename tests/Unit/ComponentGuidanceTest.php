<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FontSelect;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SignatureField;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentUsageCatalog;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\Components\Composition\BuiltInChildContracts;
use Bjanczak\FilamentFlexFields\Support\Components\Guidance\ComponentGuidance;

it('supports author guidance without a playground entry or central author map', function (): void {
    $registry = new ComponentRegistry;
    $registry->register(new ComponentDefinition(
        id: 'acme-signature', kind: ComponentKind::Field,
        guidance: new ComponentGuidance(
            summary: 'Capture an approved signature.', renderer: SignatureField::class,
            persistence: 'SVG text on the application model.', documentation: 'https://example.com/signature',
            instructions: ['Use the existing policy.'], verification: ['Save and reopen.'],
            examples: [CodeExample::php('/package/examples/signature.php')],
        ),
    ));
    $report = (new ComponentUsageCatalog)->describe($registry, 'acme-signature');

    expect($report['renderer'])->toBe(SignatureField::class)
        ->and($report['rendererSource'])->toBe('declared-guidance')
        ->and($report['documentation'])->toBe('https://example.com/signature')
        ->and($report['guidance']['format'])->toBe(1)
        ->and($report['guidance']['instructions'])->toBe(['Use the existing policy.'])
        ->and($report['examples'])->toBe([['id' => 1, 'label' => 'signature.php']])
        ->and(json_encode($report))->not->toContain('/package/');
});

it('detaches guidance from mutable caller aliases and exports no executable references', function (): void {
    $instruction = 'Preserve the policy.';
    $guidance = new ComponentGuidance(summary: 'Use a field.', instructions: [&$instruction]);
    $instruction = 'Changed later';

    expect($guidance->instructions)->toBe(['Preserve the policy.'])
        ->and($guidance->export()['instructions'])->toBe(['Preserve the policy.'])
        ->and($guidance->export())->not->toHaveKeys(['examples', 'renderer']);
});

it('does not alter resource demand or configuration contracts', function (): void {
    $plain = new ComponentRegistry;
    $plain->register(new ComponentDefinition(id: 'shared-style', kind: ComponentKind::Primitive, styles: ['shared-style']));
    $plain->register(new ComponentDefinition(id: 'control', kind: ComponentKind::Field, stylesFrom: ['shared-style']));
    $guided = new ComponentRegistry;
    $guided->register(new ComponentDefinition(id: 'shared-style', kind: ComponentKind::Primitive, styles: ['shared-style']));
    $guided->register(new ComponentDefinition(id: 'control', kind: ComponentKind::Field, stylesFrom: ['shared-style'],
        guidance: new ComponentGuidance(summary: 'Use the existing control.')));

    expect($guided->stylesFor('control'))->toBe($plain->stylesFor('control'))
        ->and($guided->runtimeComponents('control'))->toBe($plain->runtimeComponents('control'))
        ->and($guided->rootsFor('control'))->toBe($plain->rootsFor('control'));
});

it('rejects an oversized guidance batch without changing the committed registry', function (): void {
    $registry = new ComponentRegistry;
    $registry->register(new ComponentDefinition(id: 'existing', kind: ComponentKind::Field));
    $registry->validate();
    $revision = $registry->revision();
    $guidance = new ComponentGuidance(summary: 'Bounded author notes.',
        instructions: array_fill(0, 12, str_repeat('a', 900)),
        prerequisites: array_fill(0, 12, str_repeat('b', 900)));
    $definitions = [];
    foreach (range(1, 200) as $number) {
        $definitions[] = new ComponentDefinition(id: 'batch-'.$number, kind: ComponentKind::Field, guidance: $guidance);
    }

    expect(fn () => $registry->registerBatch($definitions))->toThrow(InvalidArgumentException::class, 'metadata budget')
        ->and($registry->revision())->toBe($revision)
        ->and(array_keys($registry->all()))->toBe(['existing']);
});

it('rejects malformed, executable or oversized author metadata', function (Closure $create): void {
    expect($create)->toThrow(InvalidArgumentException::class);
})->with([
    'empty summary' => [fn () => new ComponentGuidance(summary: ' ')],
    'oversized summary' => [fn () => new ComponentGuidance(summary: str_repeat('a', 513))],
    'invalid UTF-8' => [fn () => new ComponentGuidance(summary: "\xff")],
    'terminal control' => [fn () => new ComponentGuidance(summary: "text\x1b[31m")],
    'callback' => [fn () => new ComponentGuidance(summary: 'Text.', instructions: [fn () => 'run'])],
    'non-list' => [fn () => new ComponentGuidance(summary: 'Text.', instructions: ['hidden' => 'data'])],
    'too many steps' => [fn () => new ComponentGuidance(summary: 'Text.', verification: array_fill(0, 13, 'step'))],
    'insecure URL' => [fn () => new ComponentGuidance(summary: 'Text.', documentation: 'http://example.com')],
    'credential URL' => [fn () => new ComponentGuidance(summary: 'Text.', documentation: 'https://token@example.com')],
    'file URL' => [fn () => new ComponentGuidance(summary: 'Text.', documentation: 'file:///tmp/readme')],
    'malformed class' => [fn () => new ComponentGuidance(summary: 'Text.', renderer: 'call()')],
    'global budget' => [fn () => new ComponentGuidance(summary: 'Text.', instructions: array_fill(0, 12, str_repeat('a', 1024)),
        prerequisites: array_fill(0, 12, str_repeat('b', 1024)), verification: array_fill(0, 12, str_repeat('c', 1024)))],
    'unknown renderer' => [fn () => new ComponentDefinition(id: 'test', kind: ComponentKind::Field,
        guidance: new ComponentGuidance(summary: 'Text.', renderer: 'Acme\\Missing'))],
    'wrong renderer kind' => [fn () => new ComponentDefinition(id: 'test', kind: ComponentKind::Column,
        guidance: new ComponentGuidance(summary: 'Text.', renderer: FontSelect::class))],
    'conflicting renderer' => [fn () => new ComponentDefinition(id: 'test', kind: ComponentKind::Field,
        childContract: BuiltInChildContracts::select(), guidance: new ComponentGuidance(summary: 'Text.', renderer: FontSelect::class))],
]);
