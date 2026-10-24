<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Concerns;

use Bjanczak\FilamentFlexFields\Support\FilamentApi;
use Bjanczak\FilamentFlexFields\Support\Rendering\StockFieldRenderer;
use Filament\Forms\Components\Field;

/** @mixin Field */
trait RendersStockFieldView
{
    public function toHtml(): string
    {
        // render() keeps Filament's complete public-method view data. Only the
        // uncustomized toHtml path may use the stock Blade context projection.
        if (FilamentApi::hasProperty(Field::class, 'viewInstances') && $this->viewData === [] && $this->viewInstances === []) {
            $html = app(StockFieldRenderer::class)->render($this);
            if ($html !== null) {
                return $html;
            }
        }

        return parent::toHtml();
    }
}
