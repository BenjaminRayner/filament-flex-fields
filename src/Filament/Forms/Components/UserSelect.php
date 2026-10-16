<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\ConfiguresUserSelectSearch;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\UserSelect\ConfiguresUserSelectColumns;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\UserSelect\ConfiguresUserSelectModel;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\UserSelect\HasUserSelectCollaborators;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\UserSelect\InteractsWithUserSelectPresentation;

/** People are a typed data/presentation adapter. Select owns the field and overlay lifecycle. */
class UserSelect extends SelectField
{
    use ConfiguresUserSelectColumns;
    use ConfiguresUserSelectModel;
    use ConfiguresUserSelectSearch;
    use HasUserSelectCollaborators;
    use InteractsWithUserSelectPresentation;

    public function getAssetComponent(): string
    {
        return 'user-select';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->richOptions()->allowHtml()->searchable()->chevronIcon('hugeicons-unfold-more');
    }

    public function __clone(): void
    {
        parent::__clone();
        // Repositories are owned by this field, never its prototype or another tenant's field.
        $this->runtimeState = null;
        $this->recordMapper = null;
        $this->queryEngine = null;
        $this->shapeResolver = null;
        $this->optionPresenter = null;
    }
}
