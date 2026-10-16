<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Rendering;

use Livewire\Component;
use WeakMap;

/** Weak request-local phase state; discarded native components cannot leak into retries. */
final class LivewireRenderState
{
    /** @var WeakMap<Component, true> */
    private WeakMap $dehydrating;

    public function __construct()
    {
        $this->dehydrating = new WeakMap;
    }

    public function beginDehydrate(Component $component): void
    {
        $this->dehydrating[$component] = true;
    }

    public function endDehydrate(Component $component): void
    {
        unset($this->dehydrating[$component]);
    }

    public function isDehydrating(Component $component): bool
    {
        return isset($this->dehydrating[$component]);
    }
}
