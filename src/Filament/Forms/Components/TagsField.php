<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldFocusOutline;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldRounding;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\InteractsWithTagSearch;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField\ConfiguresSelectSurface;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Tags\ConfiguresTags;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Tags\NormalizesTagsState;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Tags\PresentsTags;
use Bjanczak\FilamentFlexFields\Support\Tags\TagValidator;
use Closure;
use Filament\Forms\Components\TagsInput;

/** Native storage/affixes/validation API with the shared FLEX Select runtime. */
class TagsField extends TagsInput
{
    use ConfiguresSelectSurface;
    use ConfiguresTags;
    use HasControlSize;
    use HasFieldFocusOutline;
    use HasFieldRounding;
    use InteractsWithTagSearch;
    use NormalizesTagsState;
    use PresentsTags;

    protected string $view = 'filament-flex-fields::forms.components.tags-field';

    protected function setUp(): void
    {
        parent::setUp();
        $this->size('md')->splitKeys(['Tab']);
        $this->rule(static fn (TagsField $component): Closure => TagValidator::rule($component));
    }
}
