<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldFocusOutline;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldRounding;
use Bjanczak\FilamentFlexFields\Concerns\InteractsWithSlugUnique;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\ConfiguresSlugActionButtons;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\ConfiguresSlugBehavior;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\ConfiguresSlugPermalink;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\ConfiguresSlugTitleField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\GeneratesSlugFromSource;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\InteractsWithSlugTranslatableTitle;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\ResolvesSlugStatePaths;
use Bjanczak\FilamentFlexFields\Support\Slug\SlugRenderState;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Closure;
use Filament\Forms\Components\Concerns\CanBeReadOnly;
use Filament\Forms\Components\Concerns\HasPlaceholder;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Livewire\Attributes\Renderless;

/** Slug domain state; the input and command strip use shared FLEX primitives. */
class SlugField extends Field
{
    use CanBeReadOnly;
    use ConfiguresSlugActionButtons;
    use ConfiguresSlugBehavior;
    use ConfiguresSlugPermalink;
    use ConfiguresSlugTitleField;
    use GeneratesSlugFromSource;
    use HasControlSize;
    use HasFieldFocusOutline;
    use HasFieldRounding;
    use HasPlaceholder;
    use InteractsWithSlugTranslatableTitle;
    use InteractsWithSlugUnique;
    use ResolvesSlugStatePaths;

    protected string $view = 'filament-flex-fields::forms.components.slug-field';

    protected function setUp(): void
    {
        parent::setUp();
        $this->default('');
        $this->afterStateHydrated(function (self $component, mixed $state): void {
            $component->state($component->normalizeSlug(is_string($state) ? $state : ''));
        });
        $this->dehydrateStateUsing(function (self $component, mixed $state): ?string {
            $value = $component->normalizeSlug(is_string($state) ? $state : '');

            return $value === '' ? null : $value;
        });
        $this->afterStateUpdated(function (self $component, mixed $state): void {
            if ($component->slugValueUpdated !== null) {
                $component->evaluate($component->slugValueUpdated, ['state' => is_string($state) ? $component->normalizeSlug($state) : $state]);
            }
        });
        $this->rule(function (self $component, Get $get): Closure {
            return function (string $attribute, mixed $value, Closure $fail) use ($component, $get): void {
                $pending = $component->getInlineEditPendingStatePath();
                if ($pending !== null && (bool) $get($pending, isAbsolute: true)) {
                    $fail(Translations::get('filament-flex-fields::default.validation.slug.inline_edit_pending'));

                    return;
                }
                $error = $component->validateSlugValue($value);
                if ($error !== null) {
                    $fail($error);
                }
            };
        });
    }

    /** @param array<int, string|Closure>|Closure $titleRules
     * @param  array<int, string|Closure>|Closure  $slugRules
     */
    public static function withTitle(
        ?string $fieldTitle = null,
        ?string $fieldSlug = null,
        ?Field $titleField = null,
        array|Closure $titleRules = ['required', 'string'],
        array|Closure $slugRules = ['required', 'string'],
        bool $titleAutofocus = false,
        ?Closure $slugConfigurator = null,
    ): FusedGroup {
        return TitleSlugField::make(
            fieldTitle: $fieldTitle, fieldSlug: $fieldSlug, titleField: $titleField,
            titleRules: $titleRules, slugRules: $slugRules, titleAutofocus: $titleAutofocus, slugConfigurator: $slugConfigurator,
        );
    }

    public function getSlugRenderState(): SlugRenderState
    {
        return SlugRenderState::from($this);
    }

    /** @return array<string, mixed> */
    public function getAlpineConfiguration(): array
    {
        $current = $this->isRootMounted() ? $this->getState() : $this->getRecordSlug();
        $slug = $this->normalizeSlug(is_string($current) ? $current : '');
        $autoPath = $this->getAutoUpdateDisabledStatePath();
        $disabledAuto = $autoPath !== null && $this->isRootMounted()
            ? (bool) data_get($this->getLivewire(), $autoPath, $this->getInitialAutoSyncDisabled()) : $this->getInitialAutoSyncDisabled();

        return [
            'statePath' => $this->resolveOwnStatePath(), 'initialSlug' => $slug,
            'sourcePath' => $this->getSourceStatePath(), 'sourceLive' => $this->isSourceLive(),
            'separator' => $this->getSeparator(), 'maxLength' => $this->getMaxSlugLength(),
            'urlHost' => $this->getUrlHost(), 'urlPath' => $this->getUrlPath(), 'urlPostfix' => $this->getSlugLabelPostfix(),
            'urlHostVisible' => $this->isUrlHostVisible(), 'urlPathVisible' => $this->isUrlPathVisible(),
            'permalinkPreview' => $this->hasPermalinkPreview() && $this->hasUrlContext(),
            'visitUrl' => $this->canVisitLink() ? $this->getFullPermalinkUrl($slug) : null,
            'customVisitUrl' => $this->hasCustomVisitUrl() || $this->usesSelfHealingPermalink(), 'showVisitLink' => $this->shouldShowVisitLink(),
            'canVisitLink' => $this->canVisitLink(), 'showCopyButton' => $this->shouldShowCopyButton(),
            'showRegenerateButton' => $this->shouldShowRegenerateButton(), 'showActionButtonLabels' => $this->shouldShowActionButtonLabels(),
            'autoUpdateDisabledPath' => $autoPath, 'inlineEditPendingPath' => $this->getInlineEditPendingStatePath(),
            'inlineEditing' => $this->shouldUseInlineEditing(), 'autoGenerate' => $this->shouldAutoGenerate(),
            'preserveOnEdit' => $this->shouldPreserveSlugOnEdit(), 'initialAutoSyncDisabled' => $disabledAuto,
            'allowHomepage' => $this->allowsHomepageSlug(), 'slugReadOnly' => $this->isSlugReadOnly(),
            'disabled' => $this->isRootMounted() ? $this->isDisabled() : (bool) $this->evaluate($this->isDisabled), 'debounceMs' => $this->getDebounceMilliseconds(), 'recordSlug' => $this->getRecordSlug(),
            'selfHealingPermalink' => $this->usesSelfHealingPermalink(), 'permalinkRecordKey' => $this->getPermalinkRecordKey(),
            'selfHealingSeparator' => $this->getSelfHealingSeparator(),
            'placeholder' => $this->getPlaceholder() ?? Translations::get('filament-flex-fields::default.slug.placeholder'),
            'labels' => $this->getUiLabels(), 'serverGenerate' => $this->shouldUseServerSideGeneration(),
            'slugSourceLocale' => $this->usesTranslatableTitle() ? $this->getSlugSourceLocale() : null,
            'componentKey' => $this->isRootMounted() ? $this->getKey() : null,
            'liveUniqueValidation' => $this->shouldValidateSlugUniquenessLive(), 'validateOnConfirm' => $this->requiresServerDraftValidation(),
            'uniqueTakenMessage' => $this->getSlugUniqueValidationMessage(),
        ];
    }

    #[ExposedLivewireMethod]
    #[Renderless]
    public function generateSlugPreview(string $source, bool $force = false): string
    {
        abort_if($this->isDisabled() || $this->isSlugReadOnly(), 403);
        abort_if(strlen($source) > 65536, 422);

        return $this->generateSlugFromSource($source, force: $force);
    }

    /** @return array{available: bool, message: string|null} */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function checkSlugAvailability(string $slug): array
    {
        abort_if(strlen($slug) > 65536, 422);

        return $this->checkSlugUniqueness($slug);
    }

    /** @return array{valid: bool, slug: string, message: string|null, url: string|null} */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function validateSlugDraft(string $value): array
    {
        abort_if($this->isDisabled() || $this->isSlugReadOnly(), 403);
        abort_if(strlen($value) > 65536, 422);
        $slug = $this->normalizeSlug($value);
        $message = $this->validateSlugValue($slug, $this->shouldValidateSlugUniquenessLive());

        return ['valid' => $message === null, 'slug' => $slug, 'message' => $message, 'url' => $message === null ? $this->getFullPermalinkUrl($slug) : null];
    }

    #[ExposedLivewireMethod]
    #[Renderless]
    public function resolveSlugPermalink(string $slug): ?string
    {
        abort_if(strlen($slug) > 65536, 422);

        return $this->getFullPermalinkUrl($slug);
    }

    /** @return array<string, string> */
    public function getUiLabels(): array
    {
        $labels = [];
        foreach (['auto' => 'badge_auto', 'custom' => 'badge_custom', 'edit' => 'edit', 'confirm' => 'confirm', 'cancel' => 'cancel', 'reset' => 'reset', 'regenerate' => 'regenerate', 'copy' => 'copy', 'copied' => 'copied', 'changed' => 'changed', 'error' => 'operation_error', 'copyError' => 'copy_error'] as $key => $translation) {
            $labels[$key] = Translations::get('filament-flex-fields::default.slug.'.$translation);
        }

        return [...$labels, 'permalink' => $this->getPermalinkLabel(), 'visit' => $this->getVisitLinkLabel()];
    }

    /** @return list<string> */
    public function getWrapperClasses(): array
    {
        return ['fff-slug-field-field', 'fff-flex-text-input-field', 'fff-slug-field-field--'.$this->getSize(),
            'fff-flex-text-input-field--'.$this->getSize(), 'fff-slug-field-field--'.$this->getVariant(), 'fff-rounding-'.$this->getRounding()];
    }
}
