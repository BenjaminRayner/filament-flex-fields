<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexSlider;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\ProgressCircle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;

class ProgressCirclePlayground
{
    /** @return array<string, mixed> */
    public function defaultState(): array
    {
        return ['progress_circle__animated_value' => 35];
    }

    /** @return list<Component> */
    public function components(): array
    {
        $liveValue = static fn (Get $get): float => (float) ($get('progress_circle__animated_value') ?? 35);

        return [
            $this->heading('Live progress'),
            FlexSlider::make('progress_circle__animated_value')->label('Progress value')->range(0, 100)->step(1)->fillTrack()->showValue()->live(),
            Grid::make(['default' => 1, 'sm' => 3])->extraAttributes(['class' => 'fff-progress-demo-grid'])->schema([
                $this->preview('Circle · 240 ms', ProgressCircle::make()->value($liveValue)->color('#6366f1')->animationDuration(240)),
                $this->preview('Gradient · 520 ms', ProgressCircle::make()->value($liveValue)->gapAngle(36)->gradientFrom('#ec4899')->gradientTo('#f59e0b')->animationDuration(520)),
                $this->preview('Semicircle · 900 ms', ProgressCircle::make()->value($liveValue)->variant('semicircle')->gapAngle(28)->color('success')->animationDuration(900)),
            ]),
            $this->heading('Shapes & content'),
            Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])->extraAttributes(['class' => 'fff-progress-demo-grid'])->schema([
                $this->preview('Full circle', ProgressCircle::make()->value(69)->color('primary')),
                $this->preview('Semicircle', ProgressCircle::make()->value(72)->variant('semicircle')->gapAngle(24)->color('warning')),
                $this->preview('Gradient & gap', ProgressCircle::make()->value(69)->gapAngle(36)->gradientFrom('#6366f1')->gradientTo('#ec4899')),
                $this->preview('Fraction', ProgressCircle::make()->value(124)->max(223)->fraction('124 / 223')->label('Grade rating')->color('success')),
                $this->preview('Text before', ProgressCircle::make()->value(78)->label('Completed')->contentLayout('left')->size('sm')->color('success')),
                $this->preview('Text after', ProgressCircle::make()->value(82)->label('Storage used')->contentLayout('right')->size('sm')->color('warning')),
                $this->preview('Label above', ProgressCircle::make()->value(63)->label('Uploading')->contentLayout('above')->color('primary')),
                $this->preview('Paused', ProgressCircle::make()->value(48)->paused()->color('warning')),
            ]),
            $this->heading('Sizes'),
            Grid::make(['default' => 2, 'sm' => 4])->extraAttributes(['class' => 'fff-progress-demo-grid'])->schema([
                $this->preview('Table size · 40 px', ProgressCircle::make()->value(55)->size('xs')),
                $this->preview('Small', ProgressCircle::make()->value(55)->size('sm')->gapAngle(36)),
                $this->preview('Medium', ProgressCircle::make()->value(55)->size('md')->gapAngle(36)),
                $this->preview('Large', ProgressCircle::make()->value(55)->size('lg')->gapAngle(36)),
            ]),
            $this->heading('Limits & static fill'),
            Grid::make(['default' => 1, 'sm' => 3])->extraAttributes(['class' => 'fff-progress-demo-grid'])->schema([
                $this->preview('Empty · 0%', ProgressCircle::make()->value(0)),
                $this->preview('Complete · 100%', ProgressCircle::make()->value(100)->color('success')),
                $this->preview('Without transition', ProgressCircle::make()->value(55)->animated(false)->gradientFrom('#ec4899')->gradientTo('#f59e0b')),
            ]),
            $this->heading('Distribution gauges'),
            Grid::make(['default' => 1, 'lg' => 2])->extraAttributes(['class' => 'fff-progress-demo-cards'])->schema([
                ProgressCircle::make()->variant('breakdown')->shell()->heading('Workload by service')
                    ->summaryValue('12,500 jobs')->description('Share of completed background jobs.')
                    ->changeLabel('+12%')
                    ->segments([
                        ['label' => 'API', 'value' => 6000, 'displayValue' => '6,000', 'color' => '#3b82f6'],
                        ['label' => 'Uploads', 'value' => 3500, 'displayValue' => '3,500', 'color' => '#8b5cf6'],
                        ['label' => 'Exports', 'value' => 2000, 'displayValue' => '2,000', 'color' => '#f59e0b'],
                        ['label' => 'Webhooks', 'value' => 1000, 'displayValue' => '1,000', 'color' => '#14b8a6'],
                    ]),
                ProgressCircle::make()->variant('breakdown')->shell()->heading('Storage allocation')
                    ->summaryValue('96 GB')->description('Space occupied by each asset group.')->activeSegment(0)
                    ->segments([
                        ['label' => 'Images', 'value' => 44, 'displayValue' => '44 GB', 'color' => '#6366f1'],
                        ['label' => 'Recordings', 'value' => 36, 'displayValue' => '36 GB', 'color' => '#f59e0b'],
                        ['label' => 'Backups', 'value' => 16, 'displayValue' => '16 GB', 'color' => '#2dd4bf'],
                    ]),
            ]),
            $this->heading('Optional cards'),
            Grid::make(['default' => 1, 'sm' => 2])->extraAttributes(['class' => 'fff-progress-demo-cards'])->schema([
                ProgressCircle::make()->value(34)->heading('Bounce rate')->description('Visitors who leave after one page.')->footer('Last 30 days')->shell()->color('danger')->extraAttributes(['class' => 'fff-progress-demo-card']),
                ProgressCircle::make()->value(78)->fraction('78 / 100')->label('Completion rate')->contentLayout('left')->shell()->color('success')->extraAttributes(['class' => 'fff-progress-demo-card']),
            ]),
        ];
    }

    private function heading(string $title): Text
    {
        return Text::make($title)->extraAttributes(['class' => 'fff-progress-demo-heading', 'role' => 'heading', 'aria-level' => 2]);
    }

    private function preview(string $caption, ProgressCircle $circle): View
    {
        return View::make('filament-flex-fields::playground.progress-circle-preview')
            ->viewData(['caption' => $caption])
            ->schema([$circle->extraAttributes(['aria-label' => $caption], merge: true)]);
    }
}
