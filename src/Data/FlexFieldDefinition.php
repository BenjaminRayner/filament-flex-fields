<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Data;

use Bjanczak\FilamentFlexFields\Enums\FieldType;
use Bjanczak\FilamentFlexFields\Enums\FlexFieldWidth;
use Bjanczak\FilamentFlexFields\Support\Schema\Conditions\ConditionRuleParser;
use Bjanczak\FilamentFlexFields\Support\Schema\Configuration\BuiltinConfigurationContract;
use Bjanczak\FilamentFlexFields\Support\Schema\FieldFormula;
use Bjanczak\FilamentFlexFields\Support\Schema\SchemaPayloadContract;
use Bjanczak\FilamentFlexFields\Support\Values\Submission\ValidationRuleList;
use InvalidArgumentException;

readonly class FlexFieldDefinition
{
    /** @var array<string, mixed> */
    public array $config;

    /** @var list<string|object> */
    public array $validation;

    public mixed $defaultValue;

    /** @var array<string, mixed>|list<array<string, mixed>>|null */
    public ?array $visibleWhen;

    /** @var array<string, mixed>|list<array<string, mixed>>|null */
    public ?array $requiredWhen;

    /** @var array<string, mixed>|list<array<string, mixed>>|null */
    public ?array $disabledWhen;

    /**
     * @param  array<string, mixed>  $config
     * @param  list<string|object>  $validation  Trusted PHP may supply Laravel rules; array exchange remains literal.
     * @param  array<string, mixed>|list<array<string, mixed>>|null  $visibleWhen
     * @param  array<string, mixed>|list<array<string, mixed>>|null  $requiredWhen
     * @param  array<string, mixed>|list<array<string, mixed>>|null  $disabledWhen
     */
    public function __construct(
        public string $slug,
        public string $label,
        public FieldType $type,
        array $config = [],
        array $validation = [],
        mixed $defaultValue = null,
        public ?string $helpText = null,
        public ?string $placeholder = null,
        public bool $isRequired = false,
        public bool $isActive = true,
        public bool $isVisible = true,
        public bool $hiddenLabel = false,
        public int $sort = 0,
        ?array $visibleWhen = null,
        ?array $requiredWhen = null,
        ?array $disabledWhen = null,
        public ?string $formula = null,
        public ?string $sectionId = null,
        public FlexFieldWidth $width = FlexFieldWidth::Full,
        public bool $isEncrypted = false,
    ) {
        // readonly alone does not break references nested in caller-owned PHP arrays.
        $budget = SchemaPayloadContract::budget();
        $this->config = $budget->copyArray($config);
        if ($validation !== []) {
            ValidationRuleList::assert($validation);
        }
        $rules = [];
        foreach ($validation as $rule) {
            if (is_string($rule) && str_contains($rule, "\0")) {
                throw new InvalidArgumentException('Field validation cannot contain null bytes.');
            }
            $rules[] = $rule;
        }
        $this->validation = $rules;
        $this->defaultValue = is_array($defaultValue) ? $budget->copyArray($defaultValue) : $defaultValue;
        $this->visibleWhen = $visibleWhen === null ? null : $budget->copyArray($visibleWhen);
        $this->requiredWhen = $requiredWhen === null ? null : $budget->copyArray($requiredWhen);
        $this->disabledWhen = $disabledWhen === null ? null : $budget->copyArray($disabledWhen);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        // Preserve the typed PHP convenience, then apply the same literal-data contract
        // as exchange/model validation before any bool/string/int conversion takes place.
        if (($attributes['type'] ?? null) instanceof FieldType) {
            $attributes['type'] = $attributes['type']->value;
        }
        if (($attributes['width'] ?? null) instanceof FlexFieldWidth) {
            $attributes['width'] = $attributes['width']->value;
        }
        SchemaPayloadContract::assert(['fields' => [$attributes]]);
        foreach (['slug', 'label', 'type'] as $key) {
            if (! is_string($attributes[$key] ?? null) || trim($attributes[$key]) === '') {
                throw new InvalidArgumentException('Field '.$key.' requires a nonempty literal string.');
            }
        }
        $type = FieldType::tryFrom($attributes['type'])
            ?? throw new InvalidArgumentException('Field type is not registered.');

        $helpText = $attributes['help_text'] ?? $attributes['helpText'] ?? null;
        $userConfig = $attributes['config'] ?? [];
        BuiltinConfigurationContract::assert($type, $userConfig);
        $config = array_merge($type->defaultConfig(), $userConfig);

        // Keep `size` out of merged defaults when the definition did not set it, so
        // field configurators can resolve dedicated `filament-flex-fields.ui.*_size` keys.
        if (! array_key_exists('size', $userConfig)) {
            unset($config['size']);
        }

        // Built-in NPS labels resolve in the component's current locale. Explicit
        // text (including an empty/null label) remains application-owned data.
        if ($type === FieldType::Nps) {
            foreach (['min_label', 'max_label'] as $key) {
                if (! array_key_exists($key, $userConfig)) {
                    unset($config[$key]);
                }
            }
            if (($config['variant'] ?? null) === 'emojis' && ! array_key_exists('options', $userConfig)) {
                unset($config['options']);
            }
        }

        $formula = FieldFormula::resolve($attributes, $config);
        $width = self::resolveWidth($attributes);

        return new self(
            slug: (string) $attributes['slug'],
            label: (string) $attributes['label'],
            type: $type,
            config: $config,
            validation: $attributes['validation'] ?? [],
            defaultValue: $attributes['default_value'] ?? $attributes['defaultValue'] ?? null,
            helpText: is_string($helpText) && trim($helpText) !== '' ? trim($helpText) : null,
            placeholder: $attributes['placeholder'] ?? null,
            isRequired: (bool) ($attributes['is_required'] ?? $attributes['isRequired'] ?? $attributes['required'] ?? false),
            isActive: (bool) ($attributes['is_active'] ?? $attributes['isActive'] ?? true),
            isVisible: (bool) ($attributes['is_visible'] ?? $attributes['isVisible'] ?? true),
            hiddenLabel: (bool) ($attributes['hidden_label'] ?? $attributes['hiddenLabel'] ?? false),
            sort: (int) ($attributes['sort'] ?? 0),
            visibleWhen: self::parseConditionRules($attributes['visible_when'] ?? $attributes['visibleWhen'] ?? null),
            requiredWhen: self::parseConditionRules($attributes['required_when'] ?? $attributes['requiredWhen'] ?? null),
            disabledWhen: self::parseConditionRules($attributes['disabled_when'] ?? $attributes['disabledWhen'] ?? null),
            formula: $formula,
            sectionId: isset($attributes['section_id']) && filled($attributes['section_id']) ? (string) $attributes['section_id'] : null,
            width: $width,
            isEncrypted: (bool) ($attributes['is_encrypted'] ?? $attributes['isEncrypted'] ?? $config['encrypted'] ?? false),
        );
    }

    public function hasDynamicVisibility(): bool
    {
        return $this->visibleWhen !== null;
    }

    public function hasFormula(): bool
    {
        return filled($this->formula);
    }

    /**
     * @return array<string, mixed>|list<array<string, mixed>>|null
     */
    private static function parseConditionRules(mixed $value): ?array
    {
        return ConditionRuleParser::parse($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function resolveWidth(array $attributes): FlexFieldWidth
    {
        $width = $attributes['width'] ?? $attributes['config']['width'] ?? FlexFieldWidth::Full->value;

        if ($width instanceof FlexFieldWidth) {
            return $width;
        }

        return FlexFieldWidth::tryFrom((string) $width) ?? FlexFieldWidth::Full;
    }

    public function stateKey(): string
    {
        return $this->slug;
    }
}
