<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Pages\Concerns;

use Filament\Schemas\Components\View;

trait InteractsWithCodeExamples
{
    public function codeExampleView(): View
    {
        return View::make('filament-flex-fields::catalog.code-example-panel')->key('flex-code-examples')->columnSpanFull()->viewData([
            'componentId' => $this->catalogEntry()['id'] ?? '',
        ]);
    }
}
