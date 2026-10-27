<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Rendering\RenderedPortalTemplate;
use Bjanczak\FilamentFlexFields\Tests\Support\AlpineJsHtml;

it('reads typed owner-local JSON without matching configuration-shaped labels', function (): void {
    $configuration = [
        'statePath' => 'data.author',
        'hasDynamicSearchResults' => true,
        'hasDynamicOptions' => false,
        'options' => [],
        'label' => 'hasDynamicSearchResults: false, options: [], statePath: "data.other"',
        'description' => '<script>not executable</script>',
    ];
    $html = '<template data-fff-select-configuration>'.json_encode($configuration, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR).'</template>';

    expect(AlpineJsHtml::configurationFor($html, 'data.author'))->toBe($configuration)
        ->and(AlpineJsHtml::containsFlag($html, 'hasDynamicSearchResults', true))->toBeTrue()
        ->and(AlpineJsHtml::containsFlag($html, 'hasDynamicSearchResults', false))->toBeFalse()
        ->and(AlpineJsHtml::containsFlag($html, 'hasDynamicOptions', false))->toBeTrue()
        ->and(AlpineJsHtml::containsString($html, 'statePath', 'data.author'))->toBeTrue()
        ->and(AlpineJsHtml::containsString($html, 'statePath', 'data.other'))->toBeFalse()
        ->and(AlpineJsHtml::containsEmptyArray($html, 'options'))->toBeTrue()
        ->and(AlpineJsHtml::containsEmptyArray($html, 'description'))->toBeFalse();
});

it('keeps legacy Alpine object and JSON.parse configurations readable', function (string $expression): void {
    $html = '<div x-data="'.htmlspecialchars($expression, ENT_QUOTES | ENT_HTML5).'"></div>';

    expect(AlpineJsHtml::containsFlag($html, 'enabled', true))->toBeTrue()
        ->and(AlpineJsHtml::containsFlag($html, 'enabled', false))->toBeFalse()
        ->and(AlpineJsHtml::containsString($html, 'statePath', 'data.legacy'))->toBeTrue()
        ->and(AlpineJsHtml::containsEmptyArray($html, 'options'))->toBeTrue();
})->with([
    "field({ enabled: true, statePath: 'data.legacy', options: [] })",
    "field(JSON.parse('{\\u0022enabled\\u0022:true,\\u0022statePath\\u0022:\\u0022data.legacy\\u0022,\\u0022options\\u0022:[]}'))",
]);

it('does not treat unrelated examples or JSON scripts as Alpine configuration', function (): void {
    $html = '<script type="application/json">{"enabled":true}</script><p>enabled: true, statePath: "data.other", options: []</p>';

    expect(AlpineJsHtml::containsFlag($html, 'enabled', true))->toBeFalse()
        ->and(AlpineJsHtml::containsString($html, 'statePath', 'data.other'))->toBeFalse()
        ->and(AlpineJsHtml::containsEmptyArray($html, 'options'))->toBeFalse();
});

it('rejects missing duplicate or malformed owner configurations', function (): void {
    $one = '<template data-fff-select-configuration>{"statePath":"data.author","options":[]}</template>';

    expect(fn () => AlpineJsHtml::configurationFor($one, 'data.missing'))->toThrow(LogicException::class)
        ->and(fn () => AlpineJsHtml::configurationFor($one.$one, 'data.author'))->toThrow(LogicException::class)
        ->and(fn () => AlpineJsHtml::configurationFor('<template data-fff-select-configuration>[]</template>', 'data.author'))->toThrow(LogicException::class)
        ->and(fn () => AlpineJsHtml::configurationFor('<template data-fff-select-configuration>{bad}</template>', 'data.author'))->toThrow(JsonException::class);
});

it('inspects the verified compiled portal instead of requiring eagerly mounted menu markup', function (): void {
    $portal = new RenderedPortalTemplate('<template x-teleport="body"><div x-bind:id="menuDomId"></div></template>');
    $definition = '<template data-fff-portal-template-definition="'.$portal->id.'">'.htmlspecialchars($portal->json, ENT_QUOTES | ENT_HTML5).'</template>';

    expect($definition)->not->toContain('x-teleport="body"')
        ->and(AlpineJsHtml::portalMarkup($definition))->toContain('x-teleport="body"', 'x-bind:id="menuDomId"');

    $forged = str_replace($portal->id, str_repeat('0', 64), $definition);
    expect(fn () => AlpineJsHtml::portalMarkup($forged))->toThrow(LogicException::class);
});
