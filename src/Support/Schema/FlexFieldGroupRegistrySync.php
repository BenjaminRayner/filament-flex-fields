<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema;

use Bjanczak\FilamentFlexFields\Data\FlexFieldSchema;
use Bjanczak\FilamentFlexFields\Models\FlexFieldGroup;
use Bjanczak\FilamentFlexFields\Support\FlexFieldSchemaRegistry;
use Bjanczak\FilamentFlexFields\Support\FlexFieldsConfig;
use Illuminate\Database\Eloquent\Builder;

final class FlexFieldGroupRegistrySync
{
    public function __construct(
        protected FlexFieldSchemaRegistry $registry,
    ) {}

    public function syncGroup(FlexFieldGroup $group): void
    {
        if (! FlexFieldsConfig::shouldSyncSchemaOnSave()) {
            return;
        }

        $this->registerGroup($group);
    }

    private function registerGroup(FlexFieldGroup $group): void
    {
        $payload = $group->toRegistrySchema();

        $this->registry->register(
            FlexFieldSchema::make($group->registrySchemaId(), $group->getTargetType())
                ->label($group->name)
                ->sort($group->order)
                ->active((bool) ($payload['active'] ?? true))
                ->sections($payload['sections'] ?? [])
                ->fields($payload['fields'] ?? []),
        );
    }

    public function forgetGroup(FlexFieldGroup $group): void
    {
        if (! FlexFieldsConfig::shouldSyncSchemaOnSave()) {
            return;
        }

        $this->registry->unregister($group->registrySchemaId());
    }

    public function syncAllFromDatabase(): void
    {
        if (! FlexFieldsConfig::shouldSyncSchemaFromDatabase()) {
            return;
        }

        if (! (new FlexFieldGroup)->getConnection()->getSchemaBuilder()->hasTable('flex_field_groups')) {
            return;
        }

        $this->query()
            ->orderBy('order')
            ->each(function ($group): void {
                if (! $group instanceof FlexFieldGroup) {
                    throw new \RuntimeException('Schema group query returned an unexpected record type.');
                }

                $this->registerGroup($group);
            });
    }

    public function syncForTarget(string $target): void
    {
        if (! FlexFieldsConfig::shouldSyncSchemaFromDatabase()) {
            return;
        }

        $query = $this->query()->where(function ($query) use ($target): void {
            $query->where('target_type', $target);

            if ($target === FlexFieldsConfig::getSchemaDefaultTargetType()) {
                $query->orWhereNull('target_type')->orWhere('target_type', '');
            }
        });

        if (! $query->getModel()->getConnection()->getSchemaBuilder()->hasTable('flex_field_groups')) {
            return;
        }

        $query->orderBy('order')->each(function ($group): void {
            if (! $group instanceof FlexFieldGroup) {
                throw new \RuntimeException('Schema group query returned an unexpected record type.');
            }

            $this->registerGroup($group);
        });
    }

    public function syncById(string $id): void
    {
        if (! FlexFieldsConfig::shouldSyncSchemaFromDatabase()) {
            return;
        }

        $parts = explode(':', $id, 2);
        $query = $this->query();

        if (! $query->getModel()->getConnection()->getSchemaBuilder()->hasTable('flex_field_groups')) {
            return;
        }

        if (count($parts) === 2) {
            $query->where('tenant_id', $parts[0])->where('slug', $parts[1]);
        } else {
            $query->where('slug', $id)->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', ''));
        }

        $group = $query->first();

        if ($group !== null) {
            $this->registerGroup($group);
        }
    }

    /** @return Builder<FlexFieldGroup> */
    private function query(): Builder
    {
        return (new FlexFieldGroup)->newQuery();
    }
}
