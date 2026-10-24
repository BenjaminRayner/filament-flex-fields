<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Rendering;

use LogicException;

/** Request metadata; only final fragment projection decides which definitions to emit. */
final class PortalTemplateDictionary
{
    /** @var array<string, RenderedPortalTemplate> */
    private array $templates = [];

    private int $bytes = 0;

    public function register(RenderedPortalTemplate $template): string
    {
        if (isset($this->templates[$template->id])) {
            if ($this->templates[$template->id]->json !== $template->json) {
                throw new LogicException('Conflicting immutable portal template.');
            }

            return $template->id;
        }
        if (count($this->templates) >= 128 || $this->bytes + strlen($template->json) > 2 * 1024 * 1024) {
            throw new LogicException('Portal template dictionary exceeds its fragment budget.');
        }
        $this->templates[$template->id] = $template;
        $this->bytes += strlen($template->json);

        return $template->id;
    }

    /** Optimization budget overflow preserves independently rendered valid markup. */
    public function tryRegister(RenderedPortalTemplate $template): ?string
    {
        if (! isset($this->templates[$template->id])
            && (count($this->templates) >= 128 || $this->bytes + strlen($template->json) > 2 * 1024 * 1024)) {
            return null;
        }

        return $this->register($template);
    }

    /** @return array<string, RenderedPortalTemplate> */
    public function all(): array
    {
        return $this->templates;
    }
}
