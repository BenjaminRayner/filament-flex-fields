<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Actions\Action;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\CoverCard;
use Bjanczak\FilamentFlexFields\Support\FlexFieldStylesheetQueue;
use Bjanczak\FilamentFlexFields\Support\Playground\CoverCardPlayground;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableIconPickerForm;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Livewire\Livewire;

it('exposes cover card configuration via fluent api', function () {
    $card = CoverCard::make()
        ->backgroundColor('#f4f4f5')
        ->backgroundGradient('linear-gradient(180deg, #fff, #000)')
        ->backgroundImage('https://example.com/robot.jpg')
        ->backgroundPosition('top center')
        ->ratio('3:4')
        ->topTitle('NEO')
        ->topDescription('Home Robot')
        ->footerTitle('Available soon')
        ->footerDescription('Get notified')
        ->tone('light')
        ->radius('2xl')
        ->contentMaxWidth('18rem')
        ->fullWidth();

    expect($card->getBackgroundColor())->toBe('#f4f4f5')
        ->and($card->getBackgroundGradient())->toBe('linear-gradient(180deg, #fff, #000)')
        ->and($card->getBackgroundImage())->toBe('https://example.com/robot.jpg')
        ->and($card->getBackgroundPosition())->toBe('top center')
        ->and($card->getRatio())->toBe('3:4')
        ->and($card->getAspectRatioStyle())->toBe('3 / 4')
        ->and($card->getTopTitle())->toBe('NEO')
        ->and($card->getTopDescription())->toBe('Home Robot')
        ->and($card->getFooterTitle())->toBe('Available soon')
        ->and($card->getFooterDescription())->toBe('Get notified')
        ->and($card->getTone())->toBe('light')
        ->and($card->getRadius())->toBe('2xl')
        ->and($card->getContentMaxWidth())->toBe('18rem')
        ->and($card->isFullWidth())->toBeTrue()
        ->and($card->hasTopContent())->toBeTrue()
        ->and($card->hasFooterContent())->toBeTrue();
});

it('builds layered background styles with image taking precedence over gradient', function () {
    $card = CoverCard::make()
        ->backgroundColor('#e4e4e7')
        ->backgroundGradient('linear-gradient(180deg, #fff, #000)')
        ->backgroundImage('https://example.com/robot.jpg');

    expect($card->getBackgroundStyles())->toBe([
        "background-image: url('https://example.com/robot.jpg')",
        'background-size: cover',
        'background-position: center',
        'background-repeat: no-repeat',
        'background-color: #e4e4e7',
    ]);
});

it('uses gradient background when no image is configured', function () {
    $card = CoverCard::make()
        ->backgroundColor('#18181b')
        ->backgroundGradient('linear-gradient(135deg, #111, #333)');

    expect($card->getBackgroundStyles())->toBe([
        'background-image: linear-gradient(135deg, #111, #333)',
        'background-color: #18181b',
    ]);
});

it('registers cover card playground with empty default state', function () {
    expect((new CoverCardPlayground)->defaultState())->toBe([]);
});

it('registers a short-ratio cover card playground demo for content-aware height', function () {
    $source = file_get_contents(__DIR__.'/../../src/Support/Playground/CoverCardPlayground.php');

    expect($source)
        ->toContain("->ratio('5:1')")
        ->toContain('Short ratio stress test')
        ->toContain('copy wraps')
        ->toContain('Grid sizer');
});

it('enables content overlays based on present copy blocks', function () {
    $both = CoverCard::make()
        ->contentOverlays()
        ->topTitle('Top')
        ->footerTitle('Footer');

    $topOnly = CoverCard::make()
        ->contentOverlays()
        ->topTitle('Top');

    $footerOnly = CoverCard::make()
        ->contentOverlays()
        ->footerTitle('Footer');

    expect($both->hasContentOverlays())->toBeTrue()
        ->and($both->shouldShowTopOverlay())->toBeTrue()
        ->and($both->shouldShowFooterOverlay())->toBeTrue()
        ->and($topOnly->shouldShowTopOverlay())->toBeTrue()
        ->and($topOnly->shouldShowFooterOverlay())->toBeFalse()
        ->and($footerOnly->shouldShowTopOverlay())->toBeFalse()
        ->and($footerOnly->shouldShowFooterOverlay())->toBeTrue();
});

it('allows custom top and footer overlay gradients', function () {
    $card = CoverCard::make()
        ->contentOverlays()
        ->topOverlayGradient('linear-gradient(180deg, #000, transparent)')
        ->footerOverlayGradient('linear-gradient(0deg, #111, transparent)');

    expect($card->getTopOverlayGradient())->toBe('linear-gradient(180deg, #000, transparent)')
        ->and($card->getFooterOverlayGradient())->toBe('linear-gradient(0deg, #111, transparent)');
});

it('uses glass overlay defaults when no custom gradient is configured', function () {
    $card = CoverCard::make()->contentOverlays();

    expect($card->getTopOverlayGradient())->toBe('linear-gradient(180deg, #00000036 0%, #00000021 42%, #00000000 100%)')
        ->and($card->getFooterOverlayGradient())->toBe('linear-gradient(0deg, #00000036 0%, #00000021 42%, #00000000 100%)')
        ->and($card->hasCustomTopOverlayGradient())->toBeFalse()
        ->and($card->hasCustomFooterOverlayGradient())->toBeFalse();
});

it('renders per-instance overlays and boundary from the resolved presentation', function () {
    TestableIconPickerForm::$formSchema = [
        CoverCard::make()->key('top')->contentOverlays()->topTitle('Top'),
        CoverCard::make()->key('footer')->contentOverlays()->footerTitle('Footer'),
        CoverCard::make()->key('plain')->topTitle('Plain'),
    ];
    $html = Livewire::test(TestableIconPickerForm::class)->html(false);
    expect(substr_count($html, 'fff-cover-card__overlay--top'))->toBe(1)
        ->and(substr_count($html, 'fff-cover-card__overlay--bottom'))->toBe(1)
        ->and(substr_count($html, 'class="fff-cover-card__scrim"'))->toBe(1)
        ->and(substr_count($html, 'data-fff-asset-consumer="cover-card"'))->toBe(3);
});

it('merges extraAttributes style with aspect-ratio into a single style attribute', function () {
    // Regression: a second style="" after extraAttributes was invalid HTML —
    // browsers kept only one, so either min-width or aspect-ratio was dropped.
    TestableIconPickerForm::$formSchema = [
        CoverCard::make()
            ->ratio('3:4')
            ->contentMaxWidth('16rem')
            ->footerTitle('Available soon')
            ->footerAction(Action::make('notify')->label('Notify me'))
            ->extraAttributes([
                'style' => 'min-width: fit-content',
            ]),
    ];

    $html = html_entity_decode(Livewire::test(TestableIconPickerForm::class)->html(false));

    expect($html)
        ->toContain('style="min-width: fit-content; --fff-cover-card-aspect-ratio: 3 / 4; --fff-cover-card-max-width: 16rem;"')
        ->not->toMatch('/style="[^"]*"\s[^>]*style="/');
});

it('keeps cover card height from shrinking below content when aspect-ratio is short', function () {
    // Regression (#42): aspect-ratio + overflow:hidden on the root clipped
    // footer actions. Ratio now lives on a ::before sizer so content can grow
    // the shared grid row taller than the preferred ratio.
    $sourceCss = file_get_contents(__DIR__.'/../../resources/css/components/cover-card.css');
    $render = CoverCard::make()->ratio('5:1')->getCoverCardRenderState();

    expect($sourceCss)
        ->toContain('display: grid')
        ->toContain('.fff-cover-card::before')
        ->toContain('aspect-ratio: var(--fff-cover-card-aspect-ratio, auto)')
        ->toContain('min-width: 0')
        ->toContain('overflow-wrap: break-word')
        ->and($render->styles)->toContain('--fff-cover-card-aspect-ratio: 5 / 1');
});

it('sanitizes unsafe background image urls', function () {
    $card = CoverCard::make()
        ->backgroundImage('javascript:alert(1)');

    expect($card->getBackgroundImage())->toBeNull()
        ->and($card->getBackgroundStyles())->toBe([]);
});

it('registers footer actions on the cover card', function () {
    $card = CoverCard::make()
        ->footerAction(
            Action::make('notify')
                ->label('Notify me')
                ->action(fn () => Notification::make()->title('Done')->success()->send()),
        );

    expect($card->getFooterAction()?->getName())->toBe('notify')
        ->and($card->hasFooterAction())->toBeTrue();
});

it('supports auto ratio without aspect style', function () {
    $card = CoverCard::make()
        ->ratio('auto');

    expect($card->getRatio())->toBeNull()
        ->and($card->getAspectRatioStyle())->toBeNull();
});

it('rejects invalid aspect ratios', function () {
    CoverCard::make()
        ->ratio('bad:0')
        ->getAspectRatioStyle();
})->throws(InvalidArgumentException::class);

it('rejects unsupported tone and radius values', function () {
    CoverCard::make()
        ->tone('neon')
        ->getTone();
})->throws(InvalidArgumentException::class);

it('rejects unsupported radius values', function () {
    CoverCard::make()
        ->radius('pill')
        ->getRadius();
})->throws(InvalidArgumentException::class);

it('accepts positive ratios and rejects unsafe, zero, negative and non-finite dimensions', function (string $ratio) {
    CoverCard::make()->ratio($ratio)->getAspectRatioStyle();
})->with(['0:4', '-3:4', '3:0', '3/-4', '1/2/3', '1e999/2', '1 / 2; color:red', 'NaN'])
    ->throws(InvalidArgumentException::class);

it('accepts slash and decimal ratios without altering their dimensions', function () {
    expect(CoverCard::make()->ratio(' 16 / 9 ')->getAspectRatioStyle())->toBe('16 / 9')
        ->and(CoverCard::make()->ratio('1.5')->getAspectRatioStyle())->toBe('1.5')
        ->and(CoverCard::make()->ratio(' auto ')->getRatio())->toBeNull();
});

it('rejects injected CSS declarations and resource-loading functions', function (string $method, string $value) {
    $card = CoverCard::make()->backgroundImage('/cover.jpg')->{$method}($value);
    $card->getCoverCardRenderState();
})->with([
    ['backgroundColor', 'red; position:fixed'],
    ['backgroundGradient', 'url(https://example.com/tracker)'],
    ['backgroundPosition', 'center; z-index:999'],
    ['contentMaxWidth', '20rem; display:none'],
    ['topOverlayGradient', 'linear-gradient(red,blue); color:red'],
    ['footerOverlayGradient', 'image-set(url(https://example.com/x) 1x)'],
])->throws(InvalidArgumentException::class);

it('evaluates render properties once per snapshot and never retains stale closure values', function () {
    $calls = 0;
    $title = 'First';
    $gradientCalls = 0;
    $card = CoverCard::make()
        ->topTitle(function () use (&$calls, &$title) {
            $calls++;

            return $title;
        })
        ->topOverlayGradient(function () use (&$gradientCalls) {
            $gradientCalls++;

            return 'linear-gradient(red,blue)';
        });
    $first = $card->getCoverCardRenderState();
    expect($calls)->toBe(1)->and($gradientCalls)->toBe(1)->and($first->topTitle)->toBe('First');
    $title = 'Second';
    expect($card->getCoverCardRenderState()->topTitle)->toBe('Second')->and($first->topTitle)->toBe('First');
});

it('replaces and removes footer actions without retaining the old registered CTA', function () {
    $card = CoverCard::make()->footerAction(Action::make('first'));
    expect($card->getAction('first'))->not->toBeNull();
    $card->footerAction(Action::make('second'));
    expect($card->getFooterAction()?->getName())->toBe('second')->and($card->getAction('first'))->toBeNull();
    $card->footerAction(null);
    expect($card->getFooterAction())->toBeNull()->and($card->getAction('second'))->toBeNull();
});

it('escapes copy and preserves direction and caller attributes in rendered HTML', function () {
    TestableIconPickerForm::$formSchema = [
        CoverCard::make()->key('escaped')->topTitle('<script>alert(1)</script>')->extraAttributes(['dir' => 'rtl', 'data-custom-cover' => 'yes']),
    ];
    $html = Livewire::test(TestableIconPickerForm::class)->html(false);
    expect($html)->toContain('&lt;script&gt;', 'dir="rtl"', 'data-custom-cover="yes"')->not->toContain('<script>alert(1)</script>');
});

it('remains a static display without a duplicated browser controller', function () {
    $definition = require __DIR__.'/../../resources/components/cover-card.php';
    expect($definition->entry)->toBeNull()->and($definition->capabilities)->toBe([]);
});

afterEach(function () {
    TestableIconPickerForm::$formSchema = [];
});

it('prepares visible Cover Card styles before first paint without acquiring hidden tab styles', function () {
    $host = new TestableIconPickerForm;
    $card = CoverCard::make()->key('initial')->topTitle('Initial');
    $schema = Schema::make($host)->key('initial-cover')->components([$card]);
    $schema->getFlatComponents(withHidden: true);
    request()->headers->remove('X-Livewire');
    FlexFieldStylesheetQueue::reset();
    $card->toHtml();
    expect(FlexFieldStylesheetQueue::criticalStylesheets())->toContain('cover-card');

    $hiddenCard = CoverCard::make()->key('inactive')->topTitle('Inactive');
    $tabs = Tabs::make('tabs')->tabs([
        Tab::make('First')->schema([]),
        Tab::make('Second')->schema([$hiddenCard]),
    ]);
    $schema->components([$tabs])->getFlatComponents(withHidden: true);
    FlexFieldStylesheetQueue::reset();
    $hiddenCard->toHtml();
    expect(FlexFieldStylesheetQueue::criticalStylesheets())->not->toContain('cover-card');
});

it('limits native button loading to each complete action call and leaves the registered action unchanged', function () {
    $firstAction = Action::make('sameName')->label('First CTA');
    TestableIconPickerForm::$formSchema = [
        CoverCard::make()->key('first')->footerAction($firstAction),
        CoverCard::make()->key('second')->footerAction(Action::make('sameName')->label('Second CTA')),
    ];
    $html = Livewire::test(TestableIconPickerForm::class)->html(false);
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($dom);
    $buttons = $xpath->query('//div[contains(@class,"fff-cover-card__footer-action")]//button');
    expect($buttons->length)->toBe(2);
    $targets = [];
    foreach ($buttons as $button) {
        $target = $button->getAttribute('wire:target');
        expect($target)->not->toBe('')->and($target)->toBe($button->getAttribute('wire:click'));
        $targets[] = $target;
    }
    expect($targets[0])->not->toBe($targets[1])->and($firstAction->hasLivewireTarget())->toBeFalse();
});

it('supports opt-in mobile width independently from the desktop width and ratio', function () {
    $card = CoverCard::make()->contentMaxWidth('16rem')->ratio('3:4');
    expect($card->isFullWidthOnMobile())->toBeFalse();
    $card->fullWidthOnMobile();
    $state = $card->getCoverCardRenderState();
    expect($state->fullWidthOnMobile)->toBeTrue()
        ->and($state->fullWidth)->toBeFalse()
        ->and($state->styles)->toContain('--fff-cover-card-max-width: 16rem', '--fff-cover-card-aspect-ratio: 3 / 4');
    $card->fullWidthOnMobile(fn () => false);
    expect($card->getCoverCardRenderState()->fullWidthOnMobile)->toBeFalse();
});
