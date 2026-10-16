<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\ProgressBar;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\ProgressCircle;
use Bjanczak\FilamentFlexFields\Support\Playground\ProgressCirclePlayground;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableProgressCircleTable;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableVerificationCodeForm;
use Livewire\Livewire;

it('renders independent accessible static progress displays without browser factories', function (): void {
    TestableVerificationCodeForm::$formSchema = [
        ProgressCircle::make()->value(40)->max(200)->label('Capacity')->gradientFrom('#6366f1')->gradientTo('#ec4899'),
        ProgressCircle::make()->value(0)->paused()->shell()->heading('Paused upload'),
        ProgressBar::make()->value(60)->max(200)->label('Upload')->variant('pills'),
        ProgressBar::make()->label('Preparing')->indeterminate(),
    ];
    $dom = new DOMDocument;
    @$dom->loadHTML(Livewire::test(TestableVerificationCodeForm::class)->html(false));
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//*[@role="progressbar"]')->length)->toBe(4)
        ->and($xpath->query('//*[@data-slot="progress-circle"]//*[@x-data or @x-fff-load]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-slot="progress-bar"]//*[@x-data or @x-fff-load or @role="progressbar"]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-fff-asset-consumer="progress-circle"]')->length)->toBe(2)
        ->and($xpath->query('//*[@data-fff-asset-consumer="progress-bar"]')->length)->toBe(2)
        ->and($xpath->query('//*[@aria-label="Capacity"]')->item(0)->getAttribute('aria-valuenow'))->toBe('40')
        ->and($xpath->query('//*[@aria-label="Upload"]')->item(0)->getAttribute('aria-valuemax'))->toBe('200')
        ->and($xpath->query('//*[@aria-label="Preparing" and @aria-busy="true"]')->item(0)->hasAttribute('aria-valuenow'))->toBeFalse();
});

it('renders safe real gradient stops and footer copy without false action buttons', function (): void {
    TestableVerificationCodeForm::$formSchema = [
        ProgressCircle::make()->gradientStroke('linear-gradient(90deg, #6366f1, #ec4899)')->label('<b>Label</b>'),
        ProgressBar::make()->shell()->footer('Last updated today')->label('Upload'),
    ];
    $html = Livewire::test(TestableVerificationCodeForm::class)->html(false);
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//*[local-name()="stop"]')->length)->toBe(2)
        ->and($xpath->query('//*[@data-slot="progress-bar"]//button')->length)->toBe(0)
        ->and($html)->toContain('&lt;b&gt;Label&lt;/b&gt;', 'Last updated today');
});

it('renders a static channel gauge and readable legend without interactive controls', function (): void {
    TestableVerificationCodeForm::$formSchema = [ProgressCircle::make()->variant('breakdown')->interactive(false)->shell()->heading('Distribution')->summaryValue('$24,880')->periodLabel('Last 30 days')->segments([
        ['label' => 'Search', 'value' => 60, 'displayValue' => '$60'], ['label' => 'Email', 'value' => 40, 'displayValue' => '$40'],
    ])];
    $html = Livewire::test(TestableVerificationCodeForm::class)->html(false);
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//*[@data-slot="progress-circle"]//button')->length)->toBe(0)
        ->and($xpath->query('//*[@data-slot="progress-circle"]//*[@x-data]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-slot="progress-circle"]//*[@role="img"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-slot="progress-circle"]//li')->length)->toBe(2)
        ->and($html)->toContain('60%', 'Distribution', 'Last 30 days', '$24,880', '$60');
});

it('renders the circle gallery without native Section wrappers or missing child displays', function (): void {
    TestableVerificationCodeForm::$formSchema = (new ProgressCirclePlayground)->components();
    $html = Livewire::test(TestableVerificationCodeForm::class)->html(false);
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//*[@data-slot="progress-circle"]')->length)->toBe(22)
        ->and($xpath->query('//*[@class="fff-progress-demo-preview"]')->length)->toBe(18)
        ->and($html)->not->toContain('fi-section-content', 'fi-section-header', 'Spend by channel', 'Last 30 days</span>', 'fff-progress-circle__breakdown-period');
});

it('renders a tiny ring without text while preserving its accessible numeric state', function (): void {
    TestableVerificationCodeForm::$formSchema = [ProgressCircle::make()->size('xs')->showValue(false)->value(34)->max(50)->label('Completion')];
    $html = Livewire::test(TestableVerificationCodeForm::class)->html(false);
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//*[@data-slot="progress-circle"]')->item(0)->getAttribute('aria-valuenow'))->toBe('34')
        ->and($xpath->query('//*[@data-slot="progress-circle"]')->item(0)->getAttribute('aria-valuemax'))->toBe('50')
        ->and($xpath->query('//*[contains(@class,"fff-progress-circle__display-value")]')->length)->toBe(0)
        ->and($html)->toContain('fff-progress-circle--xs', 'aria-valuetext="68%"', 'Completion');
});

it('shares the circle renderer inside ViewColumn cells with independent asset owners', function (): void {
    view()->addNamespace('progress-circle-test', __DIR__.'/../Support/views');
    $table = Livewire::test(TestableProgressCircleTable::class);
    $table->call('$refresh');
    $dom = new DOMDocument;
    @$dom->loadHTML($table->html(false));
    $xpath = new DOMXPath($dom);
    $owners = $xpath->query('//*[@data-fff-asset-consumer="progress-circle"]');
    $values = $xpath->query('//*[@data-slot="progress-circle"]');
    expect($owners->length)->toBe(2)
        ->and($owners->item(0)->getAttribute('data-fff-asset-consumer-id'))->not->toBe($owners->item(1)->getAttribute('data-fff-asset-consumer-id'))
        ->and($values->item(0)->getAttribute('aria-valuenow'))->toBe('34')
        ->and($values->item(1)->getAttribute('aria-valuenow'))->toBe('100');
});

it('loads breakdown interactions only for enabled chart instances', function (): void {
    TestableVerificationCodeForm::$formSchema = [
        ProgressCircle::make()->value(50),
        ProgressCircle::make()->variant('breakdown')->interactive(false)->segments([['label' => 'Static', 'value' => 1]]),
        ProgressCircle::make()->variant('breakdown')->segments([['label' => 'First', 'value' => 60], ['label' => 'Second', 'value' => 40]]),
    ];
    $html = Livewire::test(TestableVerificationCodeForm::class)->html(false);
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//*[@data-slot="progress-circle" and @x-data]')->length)->toBe(1)
        ->and($xpath->query('//*[contains(@class,"fff-progress-circle__legend-button")]')->length)->toBe(2)
        ->and($html)->toContain('progressCircleBreakdownComponent', '60%', 'First', 'Second');
});
