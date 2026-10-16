<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Enums\ControlSize;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\ProgressCircle;
use Bjanczak\FilamentFlexFields\Support\GravityIcon;
use Bjanczak\FilamentFlexFields\Support\Playground\ProgressCirclePlayground;

it('exposes progress circle configuration via fluent api', function () {
    $circle = ProgressCircle::make()
        ->value(69)
        ->max(223)
        ->displayValue('69%')
        ->fraction('124 / 223')
        ->label('Grade rating')
        ->variant('circle')
        ->size('md')
        ->color('primary')
        ->gapAngle(40)
        ->gradientFrom('rgb(99 102 241)')
        ->gradientTo('rgb(236 72 153)')
        ->contentLayout('left')
        ->shell()
        ->heading('Bounce rate')
        ->description('Visitors who leave after one page.')
        ->footer('Last 30 days');

    expect($circle->getValue())->toBe(69.0)
        ->and($circle->getMax())->toBe(223.0)
        ->and($circle->getDisplayValue())->toBe('69%')
        ->and($circle->getFormattedValue())->toBe('69%')
        ->and($circle->getFraction())->toBe('124 / 223')
        ->and($circle->getLabel())->toBe('Grade rating')
        ->and($circle->getVariant())->toBe('circle')
        ->and($circle->getSize())->toBe('md')
        ->and($circle->getColor())->toBe('primary')
        ->and($circle->getGapAngle())->toBe(40.0)
        ->and($circle->getPercentage())->toBe(31)
        ->and($circle->hasGradientStroke())->toBeTrue()
        ->and($circle->isPaused())->toBeFalse()
        ->and($circle->getContentLayout())->toBe('left')
        ->and($circle->hasShell())->toBeTrue()
        ->and($circle->getHeading())->toBe('Bounce rate')
        ->and($circle->getDescription())->toBe('Visitors who leave after one page.')
        ->and($circle->getFooter())->toBe('Last 30 days')
        ->and($circle->hasCardChrome())->toBeTrue();
});

it('supports semicircle variant and paused state', function () {
    $circle = ProgressCircle::make()
        ->value(48)
        ->variant('semicircle')
        ->gapAngle(24)
        ->paused()
        ->pausedIcon(GravityIcon::PauseFill);

    expect($circle->getVariant())->toBe('semicircle')
        ->and($circle->isPaused())->toBeTrue()
        ->and($circle->getPausedIcon())->toBe(GravityIcon::PauseFill);
});

it('computes svg metrics for circular and semicircle arcs', function () {
    $circle = ProgressCircle::make()
        ->value(50)
        ->max(100)
        ->gapAngle(40);

    $metrics = $circle->getSvgMetrics();

    expect($metrics)
        ->toHaveKeys(['radius', 'strokeWidth', 'arcLength', 'progressLength', 'rotation', 'gradientId', 'centerX', 'centerY', 'viewBox'])
        ->and($metrics['progressLength'])->toBeGreaterThan(0)
        ->and($metrics['arcLength'])->toBeGreaterThan($metrics['progressLength'])
        ->and($metrics['viewBox'])->toBe('0 0 100 100')
        ->and($metrics['centerY'])->toBe(50.0);

    $semi = ProgressCircle::make()
        ->value(50)
        ->variant('semicircle')
        ->gapAngle(20)
        ->getSvgMetrics();

    expect($semi['arcLength'])->toBeLessThan($metrics['arcLength'])
        ->and($semi['arcLength'])->toBeGreaterThan($circle->getSvgMetrics()['arcLength'] * 0.45)
        ->and($semi['viewBoxHeight'])->toBeGreaterThanOrEqual(56.0)
        ->and($semi['centerY'])->toBe(46.0)
        ->and($semi['rotation'])->toBe(175.0)
        ->and($semi['semicircleFloorInsetPercent'])->toBeGreaterThan(0);
});

it('registers progress circle playground with animated fill default state', function () {
    expect((new ProgressCirclePlayground)->defaultState())->toBe([
        'progress_circle__animated_value' => 35,
    ]);
});

it('renders lazy stylesheet include in progress circle blade', function () {
    $blade = file_get_contents(__DIR__.'/../../resources/views/schemas/components/progress-circle.blade.php');
    $partial = file_get_contents(__DIR__.'/../../resources/views/schemas/components/partials/progress-circle-frame.blade.php');

    expect($blade)
        ->toContain('partials.load-stylesheet')
        ->toContain("'component' => 'progress-circle'")
        ->toContain('fff-progress-circle__card');

    expect($partial)
        ->toContain('fff-progress-circle__svg')
        ->toContain('fff-progress-circle__fill')
        ->toContain('fff-progress-circle__below-label')
        ->toContain('trackGradientId')
        ->toContain('fff-progress-circle__paused-icon')
        ->not->toContain('fff-progress-circle__paused-ghost');
});

it('generates unique svg gradient ids for anonymous progress circles', function () {
    $first = ProgressCircle::make()
        ->gradientFrom('#fde047')
        ->gradientTo('#eab308');
    $second = ProgressCircle::make()
        ->gradientFrom('#6366f1')
        ->gradientTo('#ec4899');

    $firstMetrics = $first->getSvgMetrics();
    $secondMetrics = $second->getSvgMetrics();

    expect($firstMetrics['gradientId'])->not->toBe($secondMetrics['gradientId'])
        ->and($firstMetrics['trackGradientId'])->not->toBe($secondMetrics['trackGradientId'])
        ->and($firstMetrics['gradientId'])->not->toEndWith('-')
        ->and($secondMetrics['gradientId'])->not->toEndWith('-');
});

it('supports gradient strokes on fill and track', function () {
    $circle = ProgressCircle::make()
        ->gradientFrom('rgb(99 102 241)')
        ->gradientTo('rgb(236 72 153)')
        ->trackGradientFrom('rgb(228 228 231)')
        ->trackGradientTo('rgb(212 212 216)');

    expect($circle->hasGradientStroke())->toBeTrue()
        ->and($circle->hasTrackGradientStroke())->toBeTrue()
        ->and($circle->usesExplicitTrackGradient())->toBeTrue()
        ->and($circle->getTrackGradientFrom())->toBe('rgb(228 228 231)')
        ->and($circle->getTrackGradientTo())->toBe('rgb(212 212 216)');

    $implicitTrack = ProgressCircle::make()
        ->gradientFrom('rgb(99 102 241)')
        ->gradientTo('rgb(236 72 153)');

    expect($implicitTrack->usesExplicitTrackGradient())->toBeFalse()
        ->and($implicitTrack->hasTrackGradientStroke())->toBeFalse()
        ->and($implicitTrack->getTrackGradientFrom())->toBeNull()
        ->and($implicitTrack->getTrackGradientTo())->toBeNull();
});

it('accepts hex accent and gradient colors on progress circle', function () {
    $circle = ProgressCircle::make()
        ->color('#6366f1')
        ->gradientFrom('#ec4899')
        ->gradientTo('#f59e0b');

    expect($circle->getColor())->toBe('#6366f1')
        ->and($circle->usesCustomAccentColor())->toBeTrue()
        ->and($circle->getGradientFrom())->toBe('#ec4899')
        ->and($circle->getGradientTo())->toBe('#f59e0b');
});

it('supports configurable fill animation on progress circle', function () {
    $circle = ProgressCircle::make()
        ->animated()
        ->animationDuration(600);

    expect($circle->shouldAnimateFill())->toBeTrue()
        ->and($circle->getAnimationDuration())->toBe(600);
});

it('detects semicircle labels that render below the arc', function () {
    $circle = ProgressCircle::make()
        ->variant('semicircle')
        ->label('Uploading file');

    expect($circle->hasBelowLabel())->toBeTrue();

    $circleWithSideLabel = ProgressCircle::make()
        ->variant('semicircle')
        ->label('Uploading file')
        ->contentLayout('left');

    expect($circleWithSideLabel->hasBelowLabel())->toBeFalse();
});

it('rejects unsupported progress circle variants and colors', function () {
    ProgressCircle::make()
        ->variant('donut')
        ->getVariant();
})->throws(InvalidArgumentException::class);

it('rejects invalid progress circle gap angles', function () {
    ProgressCircle::make()
        ->gapAngle(360)
        ->getGapAngle();
})->throws(InvalidArgumentException::class);

it('rejects invalid progress circle max values', function () {
    ProgressCircle::make()
        ->max(-1)
        ->getMax();
})->throws(InvalidArgumentException::class);

it('rejects unsupported content layouts', function () {
    ProgressCircle::make()
        ->contentLayout('below')
        ->getContentLayout();
})->throws(InvalidArgumentException::class);

it('rejects non finite progress values and gap angles', function (float $value): void {
    expect(fn () => ProgressCircle::make()->value($value)->getProgressRatio())->toThrow(InvalidArgumentException::class)
        ->and(fn () => ProgressCircle::make()->max($value)->getMax())->toThrow(InvalidArgumentException::class)
        ->and(fn () => ProgressCircle::make()->gapAngle($value)->getGapAngle())->toThrow(InvalidArgumentException::class);
})->with([INF, -INF, NAN]);

it('clamps endpoints and keeps long centered descriptions outside the ring', function (): void {
    expect(ProgressCircle::make()->value(-50)->getProgressRatio())->toBe(0.0)
        ->and(ProgressCircle::make()->value(150)->getProgressRatio())->toBe(1.0)
        ->and(ProgressCircle::make()->label('A longer description that needs a readable line')->hasBelowLabel())->toBeTrue();
});

it('converts the legacy gradient API into actual SVG stops', function (): void {
    expect(ProgressCircle::make()->gradientStroke('linear-gradient(90deg, #6366f1, #ec4899)')->getGradientStops())->toBe(['#6366f1', '#ec4899'])
        ->and(fn () => ProgressCircle::make()->gradientStroke('url(https://invalid.test)')->getGradientStops())->toThrow(InvalidArgumentException::class);
});

it('rejects CSS declaration injection in configured progress paint', function (): void {
    expect(fn () => ProgressCircle::make()->color('rgb(1 2 3);position:fixed')->getColor())->toThrow(InvalidArgumentException::class)
        ->and(fn () => ProgressCircle::make()->gradientFrom('rgb(1 2 3);display:none')->getGradientFrom())->toThrow(InvalidArgumentException::class);
});

it('renders channel shares with deterministic rounded sector geometry', function (): void {
    $circle = ProgressCircle::make()->variant('breakdown')->segments([
        ['label' => 'Search', 'value' => 11400, 'color' => '#a3e635'],
        ['label' => 'Social', 'value' => 7620],
        ['label' => 'Email', 'value' => 3180],
        ['label' => 'Affiliates', 'value' => 2680],
    ]);
    $data = $circle->getPresentation();
    expect($data['breakdown']->total)->toBe(24880.0)
        ->and($data['breakdown']->activeIndex)->toBe(0)
        ->and($data['value'])->toBe('46%')
        ->and($data['label'])->toBe('Search')
        ->and($data['breakdown']->segments)->toHaveCount(4);
    foreach ($data['breakdown']->segments as $segment) {
        expect($segment['path'])->toStartWith('M ')->toEndWith(' Z')->not->toContain('NAN', 'INF');
    }
    expect($circle->activeSegment(1)->getPresentation()['label'])->toBe('Social');
});

it('handles an empty channel set and zero or tiny channel values without invalid paths', function (): void {
    $empty = ProgressCircle::make()->variant('breakdown')->getPresentation();
    expect($empty['value'])->toBe('0%')->and($empty['breakdown']->activeIndex)->toBeNull();
    $data = ProgressCircle::make()->variant('breakdown')->segments([
        ['label' => 'Zero', 'value' => 0], ['label' => 'Tiny', 'value' => 0.00001], ['label' => 'Main', 'value' => 1000],
    ])->getPresentation();
    expect($data['breakdown']->segments[0]['path'])->toBe('')
        ->and($data['breakdown']->segments[1]['path'])->not->toContain('NAN', 'INF');
});

it('rejects malformed channel data, invalid index and unsafe colors', function (): void {
    expect(fn () => ProgressCircle::make()->variant('breakdown')->segments([['label' => 'Bad', 'value' => -1]])->getPresentation())->toThrow(InvalidArgumentException::class)
        ->and(fn () => ProgressCircle::make()->variant('breakdown')->segments([['label' => 'Bad', 'value' => INF]])->getPresentation())->toThrow(InvalidArgumentException::class)
        ->and(fn () => ProgressCircle::make()->variant('breakdown')->segments([['label' => 'Bad', 'value' => 1, 'color' => 'rgb(1 2 3);display:none']])->getPresentation())->toThrow(InvalidArgumentException::class)
        ->and(fn () => ProgressCircle::make()->variant('breakdown')->activeSegment(1)->getPresentation())->toThrow(InvalidArgumentException::class);
});

it('supports validated tiny sizes and keeps their labels outside the ring', function (): void {
    $circle = ProgressCircle::make()->size(fn () => 'xs')->label('Done')->showValue(fn () => false);
    expect($circle->getSize())->toBe('xs')
        ->and($circle->hasBelowLabel())->toBeTrue()
        ->and($circle->getPresentation()['showValue'])->toBeFalse()
        ->and(ProgressCircle::make()->size(ControlSize::Sm)->getSize())->toBe('sm')
        ->and(fn () => ProgressCircle::make()->size('invalid')->getSize())->toThrow(InvalidArgumentException::class);
});
