<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields\Concerns;

use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields\TranslatableTab;
use Bjanczak\FilamentFlexFields\Support\Translatable\TranslatableAttributePath;
use Bjanczak\FilamentFlexFields\Support\Translatable\TranslatableTabFactory;
use Filament\Forms\Components\Field;
use Filament\Schemas\Schema;
use InvalidArgumentException;
use Throwable;

trait BuildsTranslatableTabs
{
    protected ?Schema $preparedLocaleSchema = null;

    /** @return list<TranslatableTab> */
    public function buildTranslatableTabs(): array
    {
        $templates = $this->evaluate($this->translatableFieldTemplates);
        $templates = $templates instanceof Schema ? $templates->getComponents() : $templates;
        if (! is_array($templates)) {
            throw new InvalidArgumentException('TranslatableFields schema callbacks must return an array of fields or a Schema.');
        }
        $locales = $this->getLocales();
        $paths = [];
        if ($locales === []) {
            throw new InvalidArgumentException('TranslatableFields requires at least one locale. Use ->locales() or the translatable configuration.');
        }
        foreach ($templates as $index => $template) {
            if (! $template instanceof Field) {
                throw new InvalidArgumentException('TranslatableFields schema accepts Filament field templates only.');
            }
            $path = TranslatableAttributePath::relativeBasePath($template);
            foreach ($paths as $existing) {
                if ($path === $existing || str_starts_with($path, $existing.'.') || str_starts_with($existing, $path.'.')) {
                    throw new InvalidArgumentException("Overlapping translation template paths [{$existing}] and [{$path}].");
                }
            }
            $paths[] = $path;
            if (isset($this->container)) {
                $templates[$index] = $template->getClone()->container($this->getContainer());
            }
        }

        return TranslatableTabFactory::make(
            locales: $locales, templates: array_values($templates),
            spatieTranslatable: $this->shouldUseSpatieTranslatable(),
            localeFieldUsing: $this->translatableLocaleFieldUsing,
            storageAttributeUsing: $this->translatableStorageAttributeUsing,
            useTemplateStatePath: $this->translatableUseTemplateStatePath,
        );
    }

    public function getChildSchema($key = null): ?Schema
    {
        if ($key !== null && $key !== 'default') {
            return parent::getChildSchema($key);
        }
        if ($this->preparedLocaleSchema !== null) {
            return $this->preparedLocaleSchema;
        }
        // Publish the instance cache before callbacks can reenter this layout.
        $schema = $this->preparedLocaleSchema = parent::getChildSchema($key);
        if ($schema === null) {
            return null;
        }
        try {
            foreach ($schema->getComponents() as $tab) {
                if (! $tab instanceof TranslatableTab) {
                    continue;
                }
                $this->configureLocaleTab($tab);
                $injections = ['locale' => $tab->getLocale(), 'tab' => $tab];
                foreach ($this->getTranslatableTabModifiers() as $modifier) {
                    $tab->evaluate($modifier, $injections);
                }
                foreach ($tab->getChildSchema()?->getComponents() ?? [] as $field) {
                    if (! $field instanceof Field) {
                        continue;
                    }
                    $this->configureLocaleField($field, $tab->getLocale());
                    foreach ($this->getTranslatableFieldModifiers() as $modifier) {
                        $field->evaluate($modifier, $injections);
                    }
                }
            }
            $this->syncLocaleFieldStates($schema);

        } catch (Throwable $error) {
            // A failed modifier must not leave a partly configured instance cached.
            $this->clearCachedChildSchemas();
            throw $error;
        }

        return $schema;
    }

    public function clearCachedChildSchemas(): void
    {
        $this->preparedLocaleSchema = null;
        parent::clearCachedChildSchemas();
    }
}
