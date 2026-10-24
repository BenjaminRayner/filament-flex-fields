<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\FragmentPlanProjection;
use Bjanczak\FilamentFlexFields\Support\Rendering\RenderedPortalTemplate;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableTranslatableForm;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;

it('retains the embedded Alpine-owned HTML flag when no server flag is supplied', function (): void {
    $html = view('filament-flex-fields::forms.components.partials.select-field.option-label')->render();
    expect($html)->toContain('x-if="isHtmlAllowed"', 'x-if="! isHtmlAllowed"', 'x-text="optionDropdownLabel(row.option)"', 'x-html="optionDropdownLabel(row.option)"');
});

it('omits dynamic label branches only when the rendered HTML policy is known', function (bool $allowHtml): void {
    $html = view('filament-flex-fields::forms.components.partials.select-field.option-label', ['isHtmlAllowed' => $allowHtml])->render();
    expect($html)->not->toContain('<template')
        ->and($html)->toContain($allowHtml ? 'x-html=' : 'x-text=')
        ->and($html)->not->toContain($allowHtml ? 'x-text=' : 'x-html=');
})->with([false, true]);

it('emits one stock menu definition while keeping two independent field states and boundaries', function (): void {
    TestableTranslatableForm::$formSchema = [
        SelectField::make('first')->label('First private label')->options(['a' => 'Private alpha'])->searchable(),
        SelectField::make('second')->label('Second private label')->options(['b' => 'Private beta'])->searchable(),
    ];
    try {
        $page = Livewire::test(TestableTranslatableForm::class)
            ->call('fillPlaygroundState', ['first' => 'a', 'second' => 'b']);
        $html = $page->html(false);
        expect(substr_count($html, 'data-fff-portal-template-definition='))->toBe(1)
            ->and(substr_count($html, 'data-fff-portal-template-id='))->toBe(2)
            ->and(substr_count($html, 'data-fff-asset-consumer='))->toBe(2)
            ->and(substr_count($html, 'data-fff-select-configuration'))->toBe(2);
        preg_match('~<template data-fff-portal-template-definition="([a-f0-9]{64})" wire:key="fff-portal-[a-f0-9]{64}-[a-f0-9]{64}">([^<]+)</template>~', $html, $match);
        $definition = RenderedPortalTemplate::fromJson(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5));
        expect($definition->id)->toBe($match[1])
            ->and($definition->html)->not->toContain('Private alpha', 'Private beta', 'First private label', 'Second private label');
        $page->assertSet('data.first', 'a')->assertSet('data.second', 'b');
        $page->set('data.first', '');
        $updated = $page->html(false);
        expect(substr_count($updated, 'data-fff-portal-template-definition='))->toBe(1)
            ->and(substr_count($updated, 'data-fff-portal-template-id='))->toBe(2);
        $page->assertSet('data.second', 'b');

        $field = $page->instance()->getSchema('form')->getComponentByStatePath('first');
        view()->share('errors', new ViewErrorBag);
        $standalone = $field->toHtml();
        expect($standalone)->toContain('<template x-teleport="body">')
            ->not->toContain('data-fff-portal-template-id=');
    } finally {
        TestableTranslatableForm::$formSchema = [];
    }
});

it('partitions immutable menu markup by native plain text versus allowed HTML', function (): void {
    TestableTranslatableForm::$formSchema = [
        SelectField::make('plain')->options(['a' => '<b>Literal</b>']),
        SelectField::make('html')->options(['a' => '<b>Allowed</b>'])->allowHtml(),
    ];
    try {
        $page = Livewire::test(TestableTranslatableForm::class);
        $html = $page->html(false);
        preg_match_all('~<template data-fff-portal-template-definition="[a-f0-9]{64}"[^>]*>([^<]+)</template>~', $html, $matches);
        expect($matches[1])->toHaveCount(2);
        $definitions = array_map(fn (string $json): string => RenderedPortalTemplate::fromJson(html_entity_decode($json, ENT_QUOTES | ENT_HTML5))->html, $matches[1]);
        expect(count(array_filter($definitions, fn (string $markup): bool => str_contains($markup, 'x-text="optionDropdownLabel(row.option)"'))))->toBe(1)
            ->and(count(array_filter($definitions, fn (string $markup): bool => str_contains($markup, 'x-html="optionDropdownLabel(row.option)"'))))->toBe(1);
    } finally {
        TestableTranslatableForm::$formSchema = [];
    }
});

it('gives every partial a complete portal definition and adopts nested definitions without an emitted flag', function (): void {
    $portal = new RenderedPortalTemplate('<template x-teleport="body"><div x-bind:id="menuDomId">Menu</div></template>');
    $reference = '<div data-fff-portal-template-id="'.$portal->id.'"></div>';
    $definitions = [$portal->id => $portal];
    $first = FragmentPlanProjection::project($reference, [], portals: $definitions);
    $second = FragmentPlanProjection::project($reference, [], portals: $definitions);
    expect($first)->toBe($second)
        ->and(substr_count($first, 'data-fff-portal-template-definition='))->toBe(1);
    $nested = FragmentPlanProjection::project('<section>'.$first.$second.'</section>', []);
    expect(substr_count($nested, 'data-fff-portal-template-definition='))->toBe(1)
        ->and(substr_count($nested, 'data-fff-portal-template-id='))->toBe(2);
    $island = FragmentPlanProjection::project('<!--[if BLOCK]><![endif]-->'.$reference.'<!--end-->', [], false, $definitions);
    expect($island)->toStartWith('<!--[if BLOCK]><![endif]--><span hidden data-fff-fragment-dictionary');
    $opaque = '<script>const example = '.json_encode($reference).';</script><div>plain</div>';
    expect(FragmentPlanProjection::project($opaque, [], portals: $definitions))->toBe($opaque);
});

it('rejects unknown conflicting and malformed portal definitions before projecting a fragment', function (): void {
    $one = new RenderedPortalTemplate('<template x-teleport="body"><div>One</div></template>');
    $two = new RenderedPortalTemplate('<template x-teleport="body"><div>Two</div></template>');
    $reference = '<div data-fff-portal-template-id="'.$one->id.'"></div>';
    expect(fn () => FragmentPlanProjection::project($reference, []))->toThrow(LogicException::class);
    $conflict = '<section><template data-fff-portal-template-definition="'.$one->id.'">'
        .htmlspecialchars($two->json, ENT_QUOTES).'</template>'.$reference.'</section>';
    expect(fn () => FragmentPlanProjection::project($conflict, []))->toThrow(LogicException::class)
        ->and(fn () => RenderedPortalTemplate::fromJson('{"format":1,"kind":"alpine-portal","html":"broken"}'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new RenderedPortalTemplate(str_repeat('a', 131073)))->toThrow(InvalidArgumentException::class);
});

it('renders chip removal controls only for a multiple owner and retains independent values', function (): void {
    TestableTranslatableForm::$formSchema = [
        SelectField::make('single')->options(['a' => 'Alpha', 'b' => 'Beta'])->default('a'),
        SelectField::make('many')->options(['a' => 'Alpha', 'b' => 'Beta'])->multiple()->default(['a', 'b']),
    ];
    try {
        $form = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['single' => 'a', 'many' => ['a', 'b']]);
        $html = $form->html(false);
        expect(substr_count($html, 'x-for="(chip, chipIndex) in selectedChips()"'))->toBe(1);
        $form->assertSet('data.single', 'a')->assertSet('data.many', ['a', 'b']);
        $form->set('data.many', ['b']);
        $form->assertSet('data.single', 'a')->assertSet('data.many', ['b']);
        expect(substr_count($form->html(false), 'x-for="(chip, chipIndex) in selectedChips()"'))->toBe(1);
    } finally {
        TestableTranslatableForm::$formSchema = [];
    }
});

it('keys inert definitions by fragment so new metadata cannot displace native application siblings', function (): void {
    $portal = new RenderedPortalTemplate('<template x-teleport="body"><div>Menu</div></template>');
    $reference = '<div data-fff-portal-template-id="'.$portal->id.'"></div>';
    $project = fn (string $root): string => FragmentPlanProjection::project($root.$reference.'</section>', [], portals: [$portal->id => $portal]);
    $one = $project('<section wire:partial="schema.first">');
    $again = FragmentPlanProjection::project($one, []);
    $two = $project('<section wire:partial="schema.second">');
    preg_match('/wire:key="(fff-portal-[^"]+)"/', $one, $first);
    preg_match('/wire:key="(fff-portal-[^"]+)"/', $two, $second);
    expect($again)->toBe($one)
        ->and($first[1])->not->toBe($second[1])
        ->and(substr_count($again, 'data-fff-portal-template-definition='))->toBe(1);
});

it('leaves dictionary-shaped examples opaque inside comments, raw text and quoted attributes', function (): void {
    $portal = new RenderedPortalTemplate('<template x-teleport="body"><div>Menu</div></template>');
    $reference = '<div data-fff-portal-template-id="'.$portal->id.'"></div>';
    $projected = FragmentPlanProjection::project($reference, [], portals: [$portal->id => $portal]);
    preg_match('~(<span hidden data-fff-fragment-dictionary.*?</span>)~', $projected, $match);
    foreach (['<!--'.$match[1].'-->', '<script>const value = `'.$match[1].'`;</script>',
        '<textarea>'.$match[1].'</textarea>', "<div data-example='".$match[1]."'></div>"] as $opaque) {
        expect(FragmentPlanProjection::project($opaque, []))->toBe($opaque);
    }
});
