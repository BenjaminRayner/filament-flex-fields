<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Tables\Columns;

use Bjanczak\FilamentFlexFields\Concerns\CalculatesRatingFill;
use Bjanczak\FilamentFlexFields\Filament\Concerns\EmitsFlexDisplayAssets;
use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\Concerns\RatingColumn\ConfiguresRatingColumn;
use Bjanczak\FilamentFlexFields\Support\FlexFieldStylesheetQueue;
use Bjanczak\FilamentFlexFields\Support\Rating\Presentation;
use Bjanczak\FilamentFlexFields\Support\Rating\RatingValue;
use Bjanczak\FilamentFlexFields\Support\RatingColumnRenderCache;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class RatingColumn extends TextColumn
{
    use CalculatesRatingFill;
    use ConfiguresRatingColumn;
    use EmitsFlexDisplayAssets;

    protected int $standaloneRenderSequence = 0;

    protected function flexDisplayComponentId(): string
    {
        return 'rating-column';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatStateUsing(fn (mixed $state): Htmlable => new HtmlString($this->renderRatingVisual($state)));
    }

    public function normalizeRatingFromState(mixed $state): ?float
    {
        return RatingValue::display($state, $this->getMax());
    }

    public function formatRatingDisplay(mixed $state): string
    {
        $html = $this->renderRatingVisual($state);
        if ($html === '') {
            return '';
        }
        if ($this->hasCriticalFlexDisplayStyles) {
            FlexFieldStylesheetQueue::requireCritical('rating-column');
        }
        $identity = 'rating-column.'.$this->getName().'.'.spl_object_id($this).'.'.(++$this->standaloneRenderSequence);

        return FlexFieldStylesheetQueue::emitDomRootConsumerMarkup('rating-column', $identity, $this->hasCriticalFlexDisplayStyles).$html;
    }

    protected function renderRatingVisual(mixed $state): string
    {
        $value = $this->normalizeRatingFromState($state);
        if ($value === null) {
            return '';
        }
        $presentation = Presentation::make($value, $this->getMax(), $this->getRatingDisplaySize(),
            $this->getRatingColor(), $this->getRatingIcon(), $this->shouldShowValue(), table: true);
        $key = hash('xxh128', serialize($presentation));
        $html = RatingColumnRenderCache::remember($key, static fn (): string => view(
            'filament-flex-fields::tables.columns.rating-column', ['presentation' => $presentation],
        )->render());

        return $html;
    }
}
