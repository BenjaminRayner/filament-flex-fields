<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Capture;

use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureState;
use InvalidArgumentException;

/** @phpstan-type Policies array{
 * uploads: array{enabled: bool, days: int|null}, voice_notes: array{enabled: bool, days: int|null},
 * signatures: array{enabled: bool, days: int|null}, temp_captures: array{enabled: bool, days: int|null}
 * } */
final class RetentionSettings
{
    public function __construct(private readonly MediaCaptureState $state) {}

    /** @return Policies */
    public static function defaults(): array
    {
        return ['uploads' => ['enabled' => false, 'days' => null],
            'voice_notes' => ['enabled' => false, 'days' => 365],
            'signatures' => ['enabled' => false, 'days' => null],
            'temp_captures' => ['enabled' => true, 'days' => 7]];
    }

    /** @param array<string, mixed>|null $configuration
     * @return Policies
     */
    public function resolve(?array $configuration = null): array
    {
        if ($configuration === null && $this->state->retentionPolicies !== null) {
            return $this->state->retentionPolicies;
        }
        $configuration ??= config('filament-flex-fields.media_capture', []);
        if (! is_array($configuration)) {
            throw new InvalidArgumentException('Media capture configuration must be an array.');
        }
        $configured = $configuration['retention'] ?? [];
        if (! is_array($configured)) {
            throw new InvalidArgumentException('Media retention configuration must be an array.');
        }
        $policies = self::defaults();
        foreach ($policies as $kind => $defaults) {
            $override = $configured[$kind] ?? [];
            if (! is_array($override)) {
                throw new InvalidArgumentException('Media retention policy must be an array.');
            }
            $days = array_key_exists('days', $override) ? $override['days'] : $defaults['days'];
            if (is_string($days)) {
                $days = filter_var($days, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 36500]]);
            }
            if ($days !== null && (! is_int($days) || $days < 1 || $days > 36500)) {
                throw new InvalidArgumentException('Media retention days must be null or an integer between 1 and 36500.');
            }
            $enabled = $override['enabled'] ?? $defaults['enabled'];
            if (! is_bool($enabled)) {
                throw new InvalidArgumentException('Media retention enabled must be boolean.');
            }
            $policies[$kind] = ['enabled' => $enabled, 'days' => $days];
        }

        return $this->state->retentionPolicies = $policies;
    }
}
