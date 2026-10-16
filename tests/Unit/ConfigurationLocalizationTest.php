<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Data\FlexFieldDefinition;
use Bjanczak\FilamentFlexFields\Enums\FieldType;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\BarcodeScannerField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextareaField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FontSelect;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\LinkPreviewField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\VideoField;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields;
use Bjanczak\FilamentFlexFields\Support\Bootstrap\ConfigurationDefaults;
use Bjanczak\FilamentFlexFields\Support\FlexFieldFormBuilder;
use Illuminate\Support\Arr;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

it('inherits new nested defaults without overwriting application choices', function (): void {
    $defaults = ['ui' => ['size' => 'md', 'theme' => ['radius' => '12px']], 'select' => ['limit' => 1000, 'cache' => 64]];
    $merged = ConfigurationDefaults::merge($defaults, ['ui' => ['size' => 'sm'], 'select' => ['cache' => 8]]);
    expect($merged)->toBe(['ui' => ['size' => 'sm', 'theme' => ['radius' => '12px']], 'select' => ['limit' => 1000, 'cache' => 8]])
        ->and(ConfigurationDefaults::merge($defaults, []))->toBe($defaults);
});

it('replaces lists and preserves explicit empty arrays null and false', function (): void {
    $defaults = ['middlewares' => ['web', 'auth'], 'tools' => ['bold', 'italic'], 'ui' => ['theme' => ['radius' => '12px']], 'enabled' => true, 'token' => 'example'];
    expect(ConfigurationDefaults::merge($defaults, ['middlewares' => ['custom'], 'tools' => [], 'ui' => [], 'enabled' => false, 'token' => null]))
        ->toBe(['middlewares' => ['custom'], 'tools' => [], 'ui' => [], 'enabled' => false, 'token' => null]);
});

it('honors the global video default and explicit instance overrides', function (): void {
    config()->set('filament-flex-fields.ui.video_auto_hide_controls', false);
    expect(VideoField::make('video')->autoHidesControls())->toBeFalse()
        ->and(VideoField::make('video')->autoHideControls()->autoHidesControls())->toBeTrue();
    config()->set('filament-flex-fields.ui.video_auto_hide_controls', true);
    expect(VideoField::make('video')->autoHideControls(false)->autoHidesControls())->toBeFalse();
});

it('inherits configured variants and countries through schema defaults and retains field overrides', function (): void {
    config()->set([
        'filament-flex-fields.ui.phone_variant' => 'secondary',
        'filament-flex-fields.ui.phone_default_country' => 'DE',
        'filament-flex-fields.ui.country_default_country' => 'FR',
        'filament-flex-fields.ui.schedule_variant' => 'secondary',
        'filament-flex-fields.ui.video_auto_hide_controls' => false,
    ]);
    $builder = new FlexFieldFormBuilder;
    $make = fn (FieldType $type, array $config = []) => $builder->makeComponent(FlexFieldDefinition::fromArray([
        'slug' => $type->value, 'label' => $type->value, 'type' => $type, 'config' => $config,
    ]));
    expect($make(FieldType::Phone)->getVariant())->toBe('secondary')
        ->and($make(FieldType::Phone)->getDefaultCountryCode())->toBe('DE')
        ->and($make(FieldType::Country)->getDefaultCountryCode())->toBe('FR')
        ->and($make(FieldType::Schedule)->getVariant())->toBe('secondary')
        ->and($make(FieldType::Video)->autoHidesControls())->toBeFalse()
        ->and($make(FieldType::Phone, ['variant' => 'primary', 'default_country' => 'PL'])->getVariant())->toBe('primary')
        ->and($make(FieldType::Phone, ['default_country' => 'PL'])->getDefaultCountryCode())->toBe('PL')
        ->and($make(FieldType::Video, ['auto_hide_controls' => true])->autoHidesControls())->toBeTrue();
});

it('resolves default field labels at use time while retaining custom labels', function (): void {
    app()->setLocale('en');
    $font = FontSelect::make('font');
    $barcode = BarcodeScannerField::make('barcode');
    $link = LinkPreviewField::make('link');
    $textarea = FlexTextareaField::make('body');
    $custom = BarcodeScannerField::make('custom')->scanButtonLabel('Custom scanner');
    expect($font->getPlaceholder())->toBe('Choose a font');
    app()->setLocale('pl');
    expect($font->getPlaceholder())->toBe(__('filament-flex-fields::font-select.placeholder'))
        ->and($barcode->getScanButtonLabel())->toBe(__('filament-flex-fields::default.barcode_scanner.scan'))
        ->and($barcode->getModalHeading())->toBe(__('filament-flex-fields::default.barcode_scanner.modal_heading'))
        ->and($link->getVisitLabel())->toBe(__('filament-flex-fields::default.link_preview.visit'))
        ->and($textarea->getSpeechDictationLabel())->toBe(__('filament-flex-fields::text-input.speak'))
        ->and($textarea->getEmojiPickerLabel())->toBe(__('filament-flex-fields::text-input.emoji'))
        ->and($custom->getScanButtonLabel())->toBe('Custom scanner')
        ->and($textarea->emojiPickerLabel('Custom emoji')->getEmojiPickerLabel())->toBe('Custom emoji');
});

it('localizes built-in NPS labels after construction and preserves custom and suppressed labels', function (): void {
    app()->setLocale('en');
    $builder = new FlexFieldFormBuilder;
    $make = fn (array $config = []) => $builder->makeComponent(FlexFieldDefinition::fromArray([
        'slug' => 'score', 'label' => 'Score', 'type' => FieldType::Nps, 'config' => $config,
    ]));
    $numeric = $make();
    $emoji = $make(['variant' => 'emojis']);
    $custom = $make(['min_label' => 'Custom low', 'max_label' => null]);
    $customEmoji = $make(['variant' => 'emojis', 'options' => [['value' => '0', 'label' => 'Custom mood']]]);
    expect($numeric->getMinLabel())->toBe('Not at all likely')
        ->and($emoji->getOptions())->toBe([0 => 'Awful', 1 => 'Poor', 2 => 'Neutral', 3 => 'Good', 4 => 'Excellent']);
    app()->setLocale('pl');
    expect($numeric->getMinLabel())->toBe('Zupełnie nieprawdopodobne')
        ->and($numeric->getMaxLabel())->toBe('Bardzo prawdopodobne')
        ->and($emoji->getOptions()[0])->toBe('Fatalnie')
        ->and($custom->getMinLabel())->toBe('Custom low')
        ->and($custom->getMaxLabel())->toBeNull()
        ->and($customEmoji->getOptions()[0])->toBe('Custom mood')
        ->and($customEmoji->getOptions()[1])->toBe('Słabo');
});

it('provides complete translation groups and stable replacement tokens in every bundled language', function (string $locale): void {
    foreach (glob(__DIR__.'/../../resources/lang/en/*.php') as $path) {
        $group = basename($path);
        $translatedPath = dirname(dirname($path)).'/'.$locale.'/'.$group;
        expect(is_file($translatedPath))->toBeTrue($locale.'/'.$group);
        $expected = Arr::dot(require $path);
        $actual = Arr::dot(require $translatedPath);
        expect(array_diff_key($expected, $actual))->toBe([], $locale.'/'.$group);
        foreach ($expected as $key => $text) {
            if (! is_string($text)) {
                continue;
            }
            expect($actual[$key])->toBeString()->not->toBe('');
            preg_match_all('/:[a-zA-Z][a-zA-Z0-9_]*/', $text, $sourceTokens);
            preg_match_all('/:[a-zA-Z][a-zA-Z0-9_]*/', $actual[$key], $targetTokens);
            $sourceTokens = array_unique($sourceTokens[0]);
            $targetTokens = array_unique($targetTokens[0]);
            sort($sourceTokens);
            sort($targetTokens);
            expect($targetTokens)->toBe($sourceTokens, $locale.'/'.$group.':'.$key);
            expect($actual[$key])->not->toContain('ZXQ', '⟦000');
        }
    }
})->with(['pl', 'de', 'fr', 'es', 'it', 'nl', 'pt_BR']);

it('declares every concrete config read and known dynamic built-in setting', function (): void {
    $config = require __DIR__.'/../../config/filament-flex-fields.php';
    $finder = new NodeFinder;
    $parser = (new ParserFactory)->createForNewestSupportedVersion();
    $missing = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../src', FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $nodes = $parser->parse(file_get_contents($file->getPathname()));
        foreach ($finder->find($nodes, fn ($n): bool => $n instanceof Node\Expr\FuncCall || $n instanceof Node\Expr\StaticCall || $n instanceof Node\Expr\MethodCall) as $call) {
            $name = $call->name instanceof Node\Name || $call->name instanceof Node\Identifier ? $call->name->toString() : '';
            $class = $call instanceof Node\Expr\StaticCall && $call->class instanceof Node\Name ? $call->class->toString() : '';
            $arg = $call->args[0]->value ?? null;
            $key = null;
            if (($name === 'config' || $name === 'get') && $arg instanceof Node\Scalar\String_ && str_starts_with($arg->value, 'filament-flex-fields.')) {
                $key = substr($arg->value, strlen('filament-flex-fields.'));
            } elseif (in_array($name, ['resolveConfiguredIcon', 'resolveDefaultIcon'], true) || ($class === 'FlexFieldsConfig' && $name === 'getUiDefault') || ($class === 'FlexIcon' && $name === 'configured')) {
                $key = $arg instanceof Node\Scalar\String_ ? 'ui.'.$arg->value : null;
            } elseif ($name === 'resolveFieldIcon') {
                $arg = $call->args[1]->value ?? null;
                $key = $arg instanceof Node\Scalar\String_ ? 'ui.'.$arg->value : null;
            }
            if ($key !== null && ! Arr::has($config, $key)) {
                $missing[$key] = $file->getPathname().':'.$call->getStartLine();
            }
        }
    }
    expect($missing)->toBe([]);
    foreach (['routes.prefix', 'geocoding.mapbox_provider', 'location.mapbox_assets', 'location.maplibre_assets'] as $key) {
        expect(Arr::has($config, $key))->toBeTrue($key);
    }
    foreach (['undo', 'redo', 'underline', 'strike', 'text_color', 'h1', 'h2', 'h3', 'blockquote', 'code', 'code_block', 'bullet_list', 'ordered_list', 'attach_files', 'fullscreen', 'youtube'] as $icon) {
        expect(Arr::has($config, 'ui.flex_rich_editor_'.$icon.'_icon'))->toBeTrue($icon);
    }
    expect($config)->not->toHaveKey('enterprise')
        ->and($config['ui'])->not->toHaveKey('cell_height');
});

it('keeps coupled video icon defaults inherited from explicit primary icons', function (): void {
    $video = VideoField::make('video')->volumeIcon('custom-volume')->castIcon('custom-cast')->airPlayIcon('custom-airplay');
    expect($video->getVolumeLowIcon())->toBe('custom-volume')
        ->and($video->getExitCastIcon())->toBe('custom-cast')
        ->and($video->getExitAirPlayIcon())->toBe('custom-airplay');
});

it('localizes an empty translation badge when it is read and keeps explicit overrides', function (): void {
    $tabs = new class('tabs') extends TranslatableFields
    {
        public function emptyLabel(): string
        {
            return $this->resolveEmptyBadgeLabel();
        }
    };
    app()->setLocale('en');
    expect($tabs->emptyLabel())->toBe('Empty');
    app()->setLocale('pl');
    expect($tabs->emptyLabel())->toBe('Puste');
    $tabs->emptyBadgeWhenAllFieldsAreEmpty('Custom empty');
    expect($tabs->emptyLabel())->toBe('Custom empty');
});

it('rejects duplicate literal keys in shipped language arrays', function (): void {
    $parser = (new ParserFactory)->createForNewestSupportedVersion();
    $finder = new NodeFinder;
    $duplicates = [];
    foreach (glob(__DIR__.'/../../resources/lang/*/*.php') as $path) {
        foreach ($finder->findInstanceOf($parser->parse(file_get_contents($path)), Node\Expr\Array_::class) as $array) {
            $seen = [];
            foreach ($array->items as $item) {
                if (! $item?->key instanceof Node\Scalar\String_) {
                    continue;
                }
                $key = $item->key->value;
                if (isset($seen[$key])) {
                    $duplicates[] = $path.':'.$item->getStartLine().':'.$key;
                }
                $seen[$key] = true;
            }
        }
    }
    expect($duplicates)->toBe([]);
});

it('retains platform names and machine readable barcode names in translated interfaces', function (string $locale): void {
    $english = require __DIR__.'/../../resources/lang/en/default.php';
    $translated = require __DIR__.'/../../resources/lang/'.$locale.'/default.php';
    foreach ($english['social_links']['platforms'] as $platform => $name) {
        if ($platform !== 'website') {
            expect($translated['social_links']['platforms'][$platform])->toBe($name);
        }
    }
    expect($translated['barcode_scanner']['formats'])->toBe($english['barcode_scanner']['formats']);
})->with(['de', 'fr', 'es', 'it', 'nl', 'pt_BR']);

it('uses reviewed action terminology rather than unrelated dictionary meanings', function (string $locale, array $labels): void {
    app()->setLocale($locale);
    foreach (array_combine([
        'date-time.apply', 'date-time.clear', 'default.date_time.second',
        'default.file_upload.sources.webcam_controls.manual', 'default.video.unmute',
    ], $labels) as $key => $label) {
        expect(__('filament-flex-fields::'.$key))->toBe($label);
    }
    expect(__('filament-flex-fields::default.video.mute'))->not->toBe(__('filament-flex-fields::default.video.unmute'));
})->with([
    ['de', ['Anwenden', 'Löschen', 'Sekunde', 'Manuell', 'Ton einschalten']],
    ['fr', ['Appliquer', 'Effacer', 'Seconde', 'Manuel', 'Activer le son']],
    ['es', ['Aplicar', 'Borrar', 'Segundo', 'Manual', 'Activar sonido']],
    ['it', ['Applica', 'Cancella', 'Secondo', 'Manuale', 'Riattiva audio']],
    ['nl', ['Toepassen', 'Wissen', 'Seconde', 'Handmatig', 'Dempen opheffen']],
    ['pt_BR', ['Aplicar', 'Limpar', 'Segundo', 'Manual', 'Ativar som']],
]);

it('preserves technical names code references and plural selectors through editorial corrections', function (): void {
    $issues = [];
    foreach (glob(__DIR__.'/../../resources/lang/en/*.php') as $path) {
        $english = Arr::dot(require $path);
        foreach (['de', 'fr', 'es', 'it', 'nl', 'pt_BR'] as $locale) {
            $translated = Arr::dot(require dirname(dirname($path)).'/'.$locale.'/'.basename($path));
            foreach ($english as $key => $text) {
                if (! is_string($text)) {
                    continue;
                }
                preg_match_all('~(?<![A-Za-z0-9_])(?:schema\.[a-z_]+|HasFlexFields|FLEX Fields|Filament|FlexRichEditor|FlexTextarea|TextInput|Whisper|Mapbox|MAPBOX_ACCESS_TOKEN|LICENSE v2\.0|COMMERCIAL\.md)(?![A-Za-z0-9_])|\{[a-z][a-z0-9_]*\}~', $text, $tokens);
                foreach (array_unique($tokens[0]) as $token) {
                    if (! str_contains($translated[$key], $token)) {
                        $issues[] = $locale.'/'.basename($path).':'.$key.' missing '.$token;
                    }
                }
                preg_match_all('/(?:^|\|)\s*(\{\d+\}|\[[^\]]+\])/', $text, $sourceSelectors);
                preg_match_all('/(?:^|\|)\s*(\{\d+\}|\[[^\]]+\])/', $translated[$key], $targetSelectors);
                if ($sourceSelectors[1] !== $targetSelectors[1]) {
                    $issues[] = $locale.'/'.basename($path).':'.$key.' changed plural selectors';
                }
            }
        }
    }
    expect($issues)->toBe([]);
});

it('keeps repeated clear playback and automatic mode actions consistent', function (string $locale): void {
    app()->setLocale($locale);
    foreach (['default.dual_listbox.clear', 'location.clear', 'text-input.clear'] as $key) {
        expect(__('filament-flex-fields::'.$key))->toBe(__('filament-flex-fields::date-time.clear'));
    }
    expect(__('filament-flex-fields::default.audio.play'))->toBe(__('filament-flex-fields::default.video.play'))
        ->and(__('filament-flex-fields::default.file_upload.sources.webcam_controls.auto'))->toBe(__('filament-flex-fields::default.slug.badge_auto'));
})->with(['de', 'fr', 'es', 'it', 'nl', 'pt_BR']);

it('resolves every literal package translation used by production PHP and Blade', function (): void {
    $catalog = [];
    foreach (glob(__DIR__.'/../../resources/lang/en/*.php') as $path) {
        $catalog[basename($path, '.php')] = require $path;
    }
    $parser = (new ParserFactory)->createForNewestSupportedVersion();
    $finder = new NodeFinder;
    $missing = [];
    foreach (['src', 'resources/components', 'resources/views'] as $directory) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../'.$directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            $keys = [];
            if (str_ends_with($file->getPathname(), '.blade.php')) {
                preg_match_all('/(?:__|trans|trans_choice|Translations::get|Translations::forContext|@lang)\s*\(\s*([\x27\x22])(filament-flex-fields::[^\x27\x22]+)\1/', $source, $matches, PREG_OFFSET_CAPTURE);
                foreach ($matches[2] as [$key, $offset]) {
                    if (! preg_match('/^\s*\./', substr($source, $offset + strlen($key) + 1))) {
                        $keys[] = $key;
                    }
                }
            } else {
                $nodes = $parser->parse($source);
                foreach ($finder->find($nodes, fn ($n): bool => $n instanceof Node\Expr\FuncCall || $n instanceof Node\Expr\StaticCall) as $call) {
                    $name = $call->name instanceof Node\Name || $call->name instanceof Node\Identifier ? $call->name->toString() : '';
                    $class = $call instanceof Node\Expr\StaticCall && $call->class instanceof Node\Name ? $call->class->toString() : '';
                    if (! in_array($name, ['__', 'trans', 'trans_choice'], true) && ! ($class === 'Translations' && in_array($name, ['get', 'forContext'], true))) {
                        continue;
                    }
                    $arg = $call->args[0]->value ?? null;
                    if ($arg instanceof Node\Scalar\String_) {
                        $keys[] = $arg->value;
                    }
                }
                if ($directory === 'resources/components') {
                    foreach ($finder->findInstanceOf($nodes, Node\Scalar\String_::class) as $string) {
                        if (str_starts_with($string->value, 'filament-flex-fields::catalog.')) {
                            $keys[] = $string->value;
                        }
                    }
                }
            }
            foreach ($keys as $key) {
                if (str_starts_with($key, 'filament-flex-fields::') && ! Arr::has($catalog, substr($key, strlen('filament-flex-fields::')))) {
                    $missing[$key] = $file->getPathname();
                }
            }
        }
    }
    expect($missing)->toBe([]);
});
