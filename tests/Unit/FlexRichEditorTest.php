<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexRichEditor;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\RichEditor\FlexRichEditorTool;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\RichEditor\FlexToolbarButtonGroup;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\RichEditor\Plugins\FlexRichEditorYoutubePlugin;
use Bjanczak\FilamentFlexFields\Support\FileUpload\FileUploadImageProcessor;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
use Bjanczak\FilamentFlexFields\Support\Playground\FlexRichEditorPlayground;
use Bjanczak\FilamentFlexFields\Support\RichEditor\Concerns\RegistersFlexRichEditorMediaConversions;
use Bjanczak\FilamentFlexFields\Support\RichEditor\RichEditorAttachmentIdResolver;
use Bjanczak\FilamentFlexFields\Support\RichEditor\RichEditorAttachmentPruner;
use Bjanczak\FilamentFlexFields\Support\RichEditor\RichEditorAttachmentVariantGenerator;
use Bjanczak\FilamentFlexFields\Support\RichEditor\RichEditorImageVariant;
use Bjanczak\FilamentFlexFields\Support\RichEditorGravityIcons;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\RichEditor\ToolbarButtonGroup;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

function createFlexRichEditorTestJpeg(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);

    ob_start();
    imagejpeg($image);
    $contents = ob_get_clean();
    imagedestroy($image);

    return $contents ?: '';
}

it('extends filament rich editor with flex defaults', function () {
    $field = FlexRichEditor::make('body');

    expect($field)
        ->toBeInstanceOf(FlexRichEditor::class)
        ->and($field->isJson())->toBeTrue()
        ->and($field->getFlexDefaultFloatingToolbars())->toHaveKey('paragraph')
        ->and($field->getFlexDefaultFloatingToolbars())->not->toHaveKey('image')
        ->and($field->getWrapperClasses())->toContain('fff-rich-editor-field--secondary')
        ->and($field->shouldShowJsonBadge())->toBeFalse();
});

it('enables chrome sync only when footer metrics or autosave are configured', function () {
    expect(FlexRichEditor::make('body')->shouldEnableRichEditorChromeSync())->toBeFalse()
        ->and(FlexRichEditor::make('body')->wordCount()->shouldEnableRichEditorChromeSync())->toBeTrue()
        ->and(FlexRichEditor::make('body')->autosave(30)->shouldEnableRichEditorChromeSync())->toBeTrue();
});

it('registers youtube toolbar tool without evaluating editor before initialization', function () {
    $field = FlexRichEditor::make('body')->youtube();

    $pluginTools = FlexRichEditorYoutubePlugin::make($field)->getEditorTools();

    expect($pluginTools)->toHaveCount(1)
        ->and($pluginTools[0]->getName())->toBe('youtube');

    $parentTool = RichEditorTool::make('youtube')->action(arguments: '{}');
    $flexTool = FlexRichEditorTool::fromParentTool($parentTool);

    $jsHandler = (new ReflectionProperty(RichEditorTool::class, 'jsHandler'))->getValue($flexTool);

    expect($jsHandler)->toBeInstanceOf(Closure::class);
});

it('exposes word count and json badge toggles', function () {
    $field = FlexRichEditor::make('body')
        ->wordCount()
        ->jsonBadge(false);

    expect($field->shouldShowWordCount())->toBeTrue()
        ->and($field->shouldShowJsonBadge())->toBeFalse()
        ->and($field->getJsonBadgeLabel())->toBe(__('filament-flex-fields::default.field_types.json'));
});

it('registers grouped toolbar buttons in default layout', function () {
    $groups = FlexRichEditor::make('body')->getFlexDefaultToolbarButtons();

    expect(collect($groups)->flatten()->all())->toContain('h1', 'h2', 'h3', 'attachFiles', 'clearFormatting', 'clearContent')
        ->not->toContain('textColor', 'customBlocks', 'mergeTags')
        ->and($groups)->toHaveCount(5);
});

it('exposes a full toolbar preset with attachments', function () {
    $groups = FlexRichEditor::make('body')->getFlexFullToolbarButtons();

    expect(collect($groups)->flatten()->all())->toContain('attachFiles')
        ->not->toContain('textColor', 'customBlocks', 'mergeTags');
});

it('registers Hugeicons only for flex rich editor aliases', function () {
    RichEditorGravityIcons::register();

    $nativeBold = FilamentIcon::resolve('forms:components.rich-editor.toolbar.bold');
    $nativeBoldName = is_string($nativeBold) ? $nativeBold : (string) $nativeBold;

    expect(FilamentIcon::resolve('filament-flex-fields::flex-rich-editor.toolbar.clear-formatting'))->toBe('hugeicons-eraser')
        ->and(FilamentIcon::resolve('filament-flex-fields::flex-rich-editor.toolbar.clear-content'))->toBe('hugeicons-delete-02')
        ->and($nativeBoldName)->not->toStartWith('gravityui-');
});

it('applies Hugeicons to flex extra tools and maps flex toolbar icons', function () {
    $extraTools = FlexRichEditor::getFlexExtraTools();

    expect($extraTools[0]->getIcon())->toBe('hugeicons-eraser')
        ->and($extraTools[1]->getIcon())->toBe('hugeicons-delete-02')
        ->and(RichEditorGravityIcons::iconForToolName('bold'))->toBe('hugeicons-text-bold');
});

it('keeps native comparison extra tools without gravity icons', function () {
    $tools = FlexRichEditor::getNativeExtraTools();

    expect($tools)->toHaveCount(1)
        ->and($tools[0]->getName())->toBe('clearContent')
        ->and($tools[0]->getIcon())->toBeInstanceOf(Heroicon::class);
});

it('applies Hugeicons to toolbar tools', function () {
    expect(RichEditorGravityIcons::iconForToolName('undo'))->toBe('hugeicons-undo-02')
        ->and(RichEditorGravityIcons::iconForToolName('redo'))->toBe('hugeicons-redo-02')
        ->and(RichEditorGravityIcons::iconForToolName('bold'))->toBe('hugeicons-text-bold')
        ->and(RichEditorGravityIcons::iconForToolName('h1'))->toBe('hugeicons-heading-01')
        ->and(RichEditorGravityIcons::iconForToolName('blockquote'))->toBe('hugeicons-quote-down')
        ->and(RichEditorGravityIcons::iconForToolName('textColor'))->toBe('hugeicons-paint-board')
        ->and(RichEditorGravityIcons::iconForToolName('attachFiles'))->toBe('hugeicons-image-01')
        ->and(RichEditorGravityIcons::iconForToolName('alignCenter'))->toBe('hugeicons-text-align-center');
});

it('uses the flex rich editor tooltip theme without filament arrow', function () {
    $toolSource = file_get_contents(__DIR__.'/../../src/Filament/Forms/Components/RichEditor/FlexRichEditorTool.php');
    $groupSource = file_get_contents(__DIR__.'/../../src/Filament/Forms/Components/RichEditor/FlexToolbarButtonGroup.php');

    expect($toolSource)
        ->toContain("theme: \\'fff-rich-editor\\'")
        ->toContain('arrow: false')
        ->not->toContain('$store.theme');

    expect($groupSource)
        ->toContain("theme: \\'fff-rich-editor\\'")
        ->toContain('arrow: false');
});

it('positions flex toolbar dropdown menus with fixed coordinates', function () {
    $source = file_get_contents(__DIR__.'/../../src/Filament/Forms/Components/RichEditor/FlexToolbarButtonGroup.php');

    expect($source)
        ->toContain('menuStyle')
        ->toContain(':style="menuStyle"')
        ->toContain('getBoundingClientRect()')
        ->toContain('position: fixed')
        ->toContain('requestAnimationFrame(reposition)')
        ->toContain('toolbar.addEventListener(\'scroll\'')
        ->toContain('fff-rich-editor__toolbar-dropdown-menu');
});

it('renders flex rich editor blade with filament alpine runtime', function () {
    $blade = file_get_contents(__DIR__.'/../../resources/views/forms/components/flex-rich-editor-field.blade.php');
    foreach (glob(__DIR__.'/../../resources/views/forms/components/partials/rich-editor/*.blade.php') as $partial) {
        $blade .= file_get_contents($partial);
    }

    expect($blade)
        ->toContain('fff-rich-editor')
        ->toContain('fi-fo-rich-editor')
        ->toContain('fi-fo-rich-editor-main')
        ->toContain('fi-fo-rich-editor-content')
        ->toContain('flexRichEditorFormComponent')
        ->toContain("getAlpineComponentSrc('flex-rich-editor'")
        ->toContain("getAlpineComponentSrc('rich-editor', 'filament/forms')")
        ->toContain('richEditorSrc:')
        ->not->toContain('flex-rich-editor-runtime-preload')
        ->not->toContain('x-intersect:enter.once.margin.300px')
        ->toContain('floatingToolbar::{{ $nodeName }}')
        ->toContain('fff-rich-editor__footer-stats')
        ->toContain('fff-rich-editor__bubble-menu')
        ->toContain('fff-rich-editor__image-overlay')
        ->toContain('fi-fo-rich-editor-dropdown-tool-trigger')
        ->toContain('fff-rich-editor__toolbar-separator')
        ->not->toContain('fff-rich-editor-toolbar-scroll')
        ->not->toContain('FormsIconAlias::')
        ->not->toContain('fi-fo-rich-editor-custom-blocks-ctn')
        ->not->toContain('fi-fo-rich-editor-merge-tags-list')
        ->not->toContain('<script')
        ->not->toContain('rich-editor-tooltips')
        ->not->toContain('richEditorFieldEnhancements')
        ->not->toContain('@tiptap/core');
});

it('declares rich editor field lazy stylesheet bundle', function () {
    expect(FlexFieldAssets::stylesheetsFor('rich-editor-field'))->toBe(['rich-editor-field']);
});

it('keeps the rich editor field bundle under the gzip budget', function () {
    $path = __DIR__.'/../../resources/dist/css/rich-editor-field.css';
    $gzipBytes = strlen((string) gzencode((string) file_get_contents($path), 9));
    $gzipKb = $gzipBytes / 1024;

    expect($gzipKb)->toBeLessThan(12);
});

it('keeps the flex rich editor javascript bundle under the gzip budget', function () {
    $path = __DIR__.'/../../resources/dist/components/flex-rich-editor.js';
    $gzipBytes = strlen((string) gzencode((string) file_get_contents($path), 9));
    $gzipKb = $gzipBytes / 1024;

    expect($gzipKb)->toBeLessThan(12);
});

it('ships flex rich editor as a thin shell without bundling filament tip tap runtime', function () {
    $path = __DIR__.'/../../resources/dist/components/flex-rich-editor.js';
    $source = (string) file_get_contents($path);
    $rawKb = strlen($source) / 1024;

    expect($rawKb)->toBeLessThan(35)
        ->and($source)->not->toContain('@tiptap')
        ->toContain('import(');
});

it('loads stylesheet via blade partial', function () {
    $blade = file_get_contents(__DIR__.'/../../resources/views/forms/components/flex-rich-editor-field.blade.php');
    foreach (glob(__DIR__.'/../../resources/views/forms/components/partials/rich-editor/*.blade.php') as $partial) {
        $blade .= file_get_contents($partial);
    }

    expect($blade)->toContain("load-stylesheet', ['component' => 'rich-editor-field'");
});

it('registers flex-only clear tools for shared toolbars', function () {
    $tools = FlexRichEditor::getFlexExtraTools();

    expect($tools)->toHaveCount(2)
        ->and(collect($tools)->map(fn ($tool) => $tool->getName())->all())->toBe(['clearFormatting', 'clearContent']);
});

it('registers flex rich editor playground sections', function () {
    $playground = app(FlexRichEditorPlayground::class);
    $components = $playground->components();
    $nativeToolbar = FlexRichEditor::make('_native')->getNativeComparisonToolbarButtons();

    expect($components)->toHaveCount(3)
        ->and($playground->defaultState())->toHaveKeys([
            'flex_rich_editor__basic',
            'flex_rich_editor__attachments',
            'flex_rich_editor__full_toolbar',
        ])
        ->and($playground->defaultState())->not->toHaveKey('filament_rich_editor__native')
        ->and(array_map(fn ($component) => $component->getExtraAttributes()['class'] ?? null, $components))->each->toBe('fff-playground-section')
        ->and($nativeToolbar[2][0])->toBeInstanceOf(ToolbarButtonGroup::class)
        ->and($nativeToolbar[2][0])->not->toBeInstanceOf(FlexToolbarButtonGroup::class);
});

it('uses root css tokens in rich editor field bundle', function () {
    $css = file_get_contents(__DIR__.'/../../resources/dist/css/rich-editor-field.css');
    $tokens = file_get_contents(__DIR__.'/../../resources/css/base.css');

    expect($css)
        ->toContain('var(--fff-rich-editor-text)')
        ->toContain('var(--fff-rich-editor-bubble-shadow)')
        ->and($tokens)
        ->toContain('--fff-rich-editor-text: rgb(250 250 250)');
});

it('defines reference toolbar and bubble sizing tokens', function () {
    $css = file_get_contents(__DIR__.'/../../resources/css/components/rich-editor-field.css');
    $tokens = file_get_contents(__DIR__.'/../../resources/css/base.css');

    expect($css)
        ->toContain('invisible absolute')
        ->toContain('rounded-full')
        ->toContain('--fff-rich-editor-icon-size: 1.125rem')
        ->toContain('var(--fff-rich-editor-bubble-radius)')
        ->toContain('var(--fff-rich-editor-tool-bg-hover)')
        ->toContain('border: 0')
        ->toContain('flex-nowrap')
        ->toContain('.fff-rich-editor__toolbar-group')
        ->toContain('.fff-rich-editor__toolbar-button--distraction-free-hidden')
        ->toContain('gap: 4px')
        ->toContain('overflow-y: visible')
        ->toContain('--fff-rich-editor-tooltip-z: 20')
        ->toContain('overflow-x-auto')
        ->toContain('overflow: hidden')
        ->toContain('var(--fff-rich-editor-radius, var(--fff-global-radius, 1rem))')
        ->toContain('var(--fff-rich-editor-border')
        ->toContain('z-[30]')
        ->toContain('var(--fff-rich-editor-radius, var(--fff-global-radius, 1rem))')
        ->toContain('.fi-fo-rich-editor-dropdown-tool-menu[x-cloak]')
        ->toContain('var(--fff-rich-editor-bubble-radius)')
        ->toContain('var(--fff-rich-editor-bubble-shadow)')
        ->toContain('fi-fo-rich-editor-dropdown-tool-chevron');

    expect($css)->toContain('.fff-rich-editor__panels')
        ->not->toContain('.fff-rich-editor-custom-block-preview')
        ->not->toContain('.fi-fo-rich-editor-custom-block-btn');

    expect($tokens)
        ->toContain('--fff-rich-editor-bg: var(--fff-field-secondary-bg)')
        ->toContain('--fff-rich-editor-bubble-bg: rgb(255 255 255)')
        ->toContain('--fff-rich-editor-tool-bg-hover: #ebebec')
        ->toContain('--fff-rich-editor-toolbar-divider: rgb(212 212 216)')
        ->toContain('--fff-rich-editor-border: var(--fff-field-secondary-border)');

    expect($css)
        ->not->toContain('body:has(.fff-rich-editor button:hover)')
        ->toContain("[data-theme~='fff-rich-editor']")
        ->toContain('display: none !important');

    $blade = file_get_contents(__DIR__.'/../../resources/views/forms/components/flex-rich-editor-field.blade.php');
    foreach (glob(__DIR__.'/../../resources/views/forms/components/partials/rich-editor/*.blade.php') as $partial) {
        $blade .= file_get_contents($partial);
    }

    expect($blade)
        ->toContain('x-on:click.capture')
        ->not->toContain('fff-rich-editor-toolbar-scroll')
        ->not->toContain('x-show="! isDistractionFreeToolbarButton')
        ->toContain('fff-rich-editor__toolbar-button--distraction-free-hidden')
        ->toContain('fi-fo-rich-editor-dropdown-tool-trigger')
        ->toContain('x-ref="floatingToolbar::{{ $nodeName }}"');
});

it('exposes rich editor attachment optimization and restriction helpers', function () {
    $field = FlexRichEditor::make('body')
        ->imagesOnly()
        ->maxAttachmentSizeKb(2048)
        ->maxImageLongEdge(2000)
        ->optimizeImages()
        ->optimizeImagesToWebp(true);

    expect($field->getFileAttachmentsAcceptedFileTypes())->toBe(['image/*'])
        ->and($field->getFileAttachmentsMaxSize())->toBe(2048)
        ->and($field->getFlexRichEditorMaxImageLongEdge())->toBe(2000)
        ->and($field->shouldOptimizeRichEditorImages())->toBeTrue()
        ->and($field->shouldOptimizeRichEditorImagesToWebp())->toBeTrue()
        ->and($field->shouldProcessRichEditorAttachmentImages())->toBeTrue()
        ->and($field->usesRichEditorFileAttachmentProvider())->toBeFalse();
});

it('builds and applies a rich editor image processor from field options', function () {
    Storage::fake('public');

    $path = 'rich-editor/photo.jpg';
    Storage::disk('public')->put($path, createFlexRichEditorTestJpeg(400, 200));

    $field = FlexRichEditor::make('body')
        ->maxImageLongEdge(100)
        ->optimizeImages();

    $reflection = new ReflectionClass($field);
    $method = $reflection->getMethod('makeRichEditorImageProcessor');
    $method->setAccessible(true);

    /** @var FileUploadImageProcessor $processor */
    $processor = $method->invoke($field, true);
    $processedPath = $processor->process(Storage::disk('public'), $path);

    Storage::disk('public')->assertExists($processedPath);

    [$width, $height] = getimagesize(Storage::disk('public')->path($processedPath));

    expect(max($width, $height))->toBeLessThanOrEqual(100);
});

it('resolves max long edge dimensions only when the image exceeds the limit', function () {
    $processor = new FileUploadImageProcessor(
        maxImageLongEdge: 200,
    );

    $reflection = new ReflectionClass($processor);
    $method = $reflection->getMethod('resolveTargetDimensions');
    $method->setAccessible(true);

    expect($method->invoke($processor, 400, 200))->toBe([200, 100])
        ->and($method->invoke($processor, 200, 400))->toBe([100, 200])
        ->and($method->invoke($processor, 120, 80))->toBe([120, 80]);
});

it('normalizes rich editor image variants and auto-selects a master variant', function () {
    $variants = RichEditorImageVariant::normalizeCollection([
        'thumb' => ['max_long_edge' => 320, 'webp' => true],
        'large' => ['max_long_edge' => 2000, 'webp' => true],
    ]);

    expect($variants)->toHaveCount(2)
        ->and(collect($variants)->firstWhere('name', 'large')?->master)->toBeTrue()
        ->and(collect($variants)->firstWhere('name', 'thumb')?->master)->toBeFalse();
});

it('generates named attachment variants and writes a manifest on disk', function () {
    Storage::fake('public');

    $path = 'rich-editor/photo.jpg';
    Storage::disk('public')->put($path, createFlexRichEditorTestJpeg(800, 600));

    $manifest = (new RichEditorAttachmentVariantGenerator)->generate(
        Storage::disk('public'),
        $path,
        RichEditorImageVariant::normalizeCollection([
            'thumb' => ['max_long_edge' => 100, 'webp' => true],
            'large' => ['max_long_edge' => 400, 'master' => true],
        ]),
    );

    expect($manifest->master)->toEndWith('.jpg')
        ->and($manifest->variants)->toHaveKey('thumb')
        ->and($manifest->widths)->toHaveKey('master')
        ->and(Storage::disk('public')->exists($manifest->variants['thumb']))->toBeTrue()
        ->and(Storage::disk('public')->exists($manifest->manifestPath()))->toBeTrue();
});

it('resolves image ids from rich editor json content', function () {
    $content = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'content' => [
                    [
                        'type' => 'image',
                        'attrs' => [
                            'id' => 'rich-editor/photo.webp',
                            'src' => 'https://example.test/photo.webp',
                        ],
                    ],
                ],
            ],
        ],
    ];

    expect(RichEditorAttachmentIdResolver::fromContent($content))
        ->toBe(['rich-editor/photo.webp']);
});

it('deletes removed rich editor attachments with all generated variants', function () {
    Storage::fake('public');

    $path = 'rich-editor/photo.jpg';
    Storage::disk('public')->put($path, createFlexRichEditorTestJpeg(800, 600));

    $manifest = (new RichEditorAttachmentVariantGenerator)->generate(
        Storage::disk('public'),
        $path,
        RichEditorImageVariant::normalizeCollection([
            'thumb' => ['max_long_edge' => 100, 'webp' => true],
            'large' => ['max_long_edge' => 400, 'master' => true],
        ]),
    );

    (new RichEditorAttachmentPruner)->deleteMastersWithVariants(
        Storage::disk('public'),
        [$manifest->master],
    );

    foreach ($manifest->allPaths() as $storedPath) {
        Storage::disk('public')->assertMissing($storedPath);
    }

    Storage::disk('public')->assertMissing($manifest->manifestPath());
});

it('enables orphan attachment pruning by default on flex rich editor', function () {
    expect(FlexRichEditor::make('body')->shouldPruneOrphanedRichEditorAttachments())->toBeTrue();
});

it('provides default WebP thumb and large image variants and allows opting out', function () {
    $field = FlexRichEditor::make('body');
    $variants = collect($field->getFlexRichEditorImageVariants())->keyBy('name');
    expect($variants['thumb']->maxLongEdge)->toBe(250)->and($variants['thumb']->webp)->toBeTrue()
        ->and($variants['large']->maxLongEdge)->toBe(1500)->and($variants['large']->master)->toBeTrue();
    expect($field->imageVariants([])->getFlexRichEditorImageVariants())->toBe([]);
});

it('generates WebP variants through a stream-only remote disk', function () {
    Storage::fake('remote');
    $storage = Storage::disk('remote');
    $storage->put('editor/photo.jpg', createFlexRichEditorTestJpeg(1800, 900));
    $remote = Mockery::mock(Filesystem::class);
    foreach (['readStream', 'exists', 'getVisibility', 'put', 'delete'] as $method) {
        $remote->shouldReceive($method)->andReturnUsing(fn (...$arguments) => $storage->{$method}(...$arguments));
    }
    $manifest = (new RichEditorAttachmentVariantGenerator)->generate($remote, 'editor/photo.jpg', FlexRichEditor::make('body')->getFlexRichEditorImageVariants());
    expect($manifest->master)->toBe('editor/photo.webp')
        ->and($manifest->widths)->toBe(['master' => 1500, 'thumb' => 250])
        ->and(mime_content_type($storage->path($manifest->variants['thumb'])))->toBe('image/webp');
    $storage->assertMissing('editor/photo.jpg');
});

it('cleans partial variant publication while preserving the original on failure', function () {
    Storage::fake('remote');
    $storage = Storage::disk('remote');
    $original = createFlexRichEditorTestJpeg(600, 300);
    $storage->put('editor/photo.jpg', $original);
    $remote = Mockery::mock(Filesystem::class);
    foreach (['readStream', 'exists', 'getVisibility', 'delete'] as $method) {
        $remote->shouldReceive($method)->andReturnUsing(fn (...$arguments) => $storage->{$method}(...$arguments));
    }
    $remote->shouldReceive('put')->andReturnUsing(fn ($path, $contents, $options = []) => str_ends_with($path, '.flex-variants.json') ? false : $storage->put($path, $contents, $options));
    expect(fn () => (new RichEditorAttachmentVariantGenerator)->generate($remote, 'editor/photo.jpg', FlexRichEditor::make('body')->getFlexRichEditorImageVariants()))->toThrow(RuntimeException::class, 'manifest');
    expect($storage->get('editor/photo.jpg'))->toBe($original)
        ->and($storage->allFiles('editor'))->toBe(['editor/photo.jpg']);
});

it('rejects unsafe variant names, invalid dimensions and multiple master variants', function () {
    expect(fn () => RichEditorImageVariant::make('../other'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => RichEditorImageVariant::make('thumb')->maxLongEdge(0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => RichEditorImageVariant::normalizeCollection(['one' => ['master' => true], 'two' => ['master' => true]]))->toThrow(InvalidArgumentException::class);
});

it('never deletes unrelated paths referenced by a tampered variant manifest', function () {
    Storage::fake('public');
    $disk = Storage::disk('public');
    $disk->put('editor/photo.webp', 'image');
    $disk->put('other/private.webp', 'unrelated');
    $disk->put('editor/photo.flex-variants.json', json_encode(['master' => 'editor/photo.webp', 'variants' => ['thumb' => 'other/private.webp']]));
    (new RichEditorAttachmentPruner)->deleteMastersWithVariants($disk, ['editor/photo.webp']);
    $disk->assertExists('other/private.webp');
    $disk->assertMissing('editor/photo.webp');
});

it('registers all spatie variants including the display master and respects queue selection', function () {
    $model = new class
    {
        use RegistersFlexRichEditorMediaConversions;

        public array $conversions = [];

        public function configure(bool $queued): void
        {
            $this->registerFlexRichEditorMediaConversions([
                'thumb' => ['max_long_edge' => 250, 'webp' => true],
                'large' => ['max_long_edge' => 1500, 'master' => true, 'webp' => true],
            ], $queued);
        }

        public function addMediaConversion(string $name): object
        {
            return $this->conversions[$name] = new class
            {
                public bool $queued = false;

                public function __call(string $name, array $arguments): self
                {
                    return $this;
                }

                public function queued(): self
                {
                    $this->queued = true;

                    return $this;
                }

                public function nonQueued(): self
                {
                    $this->queued = false;

                    return $this;
                }
            };
        }
    };

    $model->configure(true);
    expect(array_keys($model->conversions))->toBe(['thumb', 'large'])
        ->and($model->conversions['thumb']->queued)->toBeTrue()
        ->and($model->conversions['large']->queued)->toBeTrue();
    $model->configure(false);
    expect($model->conversions['large']->queued)->toBeFalse();
});
