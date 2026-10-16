<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\LinkPreview;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\LinkPreviewField;

final readonly class LinkPreviewRenderState
{
    /** @param array<string, mixed> $configuration */
    public function __construct(public array $configuration, public string $value, public string $displayValue, public string $size, public string $variant, public string $rounding) {}

    public static function from(LinkPreviewField $field, mixed $state): self
    {
        $value = is_string($state) ? trim($state) : '';
        $config = $field->getAlpineConfiguration();
        $config['disabled'] = $field->isDisabled();
        $config['readOnly'] = $field->isReadOnly();
        $config['initialUrl'] = $value;
        $config['initialPreview'] = $field->shouldResolveInitialPreviewOnServer() ? $field->resolveInitialPreview($value) : null;
        $prefix = $field->getPrefix();
        $display = $prefix !== null && str_starts_with($value, $prefix) ? substr($value, strlen($prefix)) : $value;

        return new self($config, $value, $display, $field->getSize(), $field->getVariant(), $field->getRounding());
    }

    public function fingerprint(): string
    {
        $structure = $this->configuration;
        unset($structure['initialUrl'], $structure['initialPreview']);

        return hash('sha256', serialize([$structure, $this->size, $this->variant, $this->rounding]));
    }
}
