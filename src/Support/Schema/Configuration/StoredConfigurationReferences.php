<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema\Configuration;

use Bjanczak\FilamentFlexFields\Enums\FieldType;
use LogicException;

/** Saved data can select approved server references, never authorize a new class/view/method. */
final class StoredConfigurationReferences
{
    /** @param array<string, mixed> $config */
    public static function assert(FieldType $type, array $config): void
    {
        $references = match ($type) {
            FieldType::Select => ['option_view' => 'views', 'option_trigger_view' => 'views'],
            FieldType::UserSelect => ['option_model' => 'models', 'model' => 'models',
                'option_view' => 'views', 'option_trigger_view' => 'views'],
            FieldType::Slug => ['spatie_model' => 'models'],
            FieldType::VerificationCode => ['auto_submit_method' => 'methods'],
            FieldType::TodoList => ['remote_loader' => 'methods'],
            default => [],
        };
        foreach ($references as $key => $category) {
            $value = $config[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            ConfigurationRules::text($config, $key, $category === 'methods' ? 96 : 512);
            $allowed = config('filament-flex-fields.schema.configuration_references.'.$type->value.'.'.$category, []);
            if (! is_array($allowed) || ! array_is_list($allowed) || count($allowed) > 128) {
                throw new LogicException('Schema configuration references require bounded server-owned lists.');
            }
            foreach ($allowed as $reference) {
                if (! is_string($reference) || trim($reference) === '' || strlen($reference) > 512 || str_contains($reference, "\0")) {
                    throw new LogicException('Invalid server-owned schema configuration reference.');
                }
            }
            if (! in_array($value, $allowed, true)) {
                ConfigurationRules::fail($key, 'is not approved by the application configuration');
            }
        }
        if ($type === FieldType::UserSelect && isset($config['option_model'], $config['model'])
            && $config['option_model'] !== '' && $config['model'] !== '' && $config['option_model'] !== $config['model']) {
            ConfigurationRules::fail('option_model', 'contains conflicting model aliases');
        }
    }
}
