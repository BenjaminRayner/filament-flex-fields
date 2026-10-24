<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Rendering\RenderedControlIcon;
use BladeUI\Icons\Factory;
use BladeUI\Icons\IconsManifest;
use Filament\Support\Enums\IconSize;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\ComponentAttributeBag;

it('keeps native glyph output for every size and explicit stroke', function (IconSize $size): void {
    $renderer = app(RenderedControlIcon::class);
    $attributes = new ComponentAttributeBag(['class' => 'public-control', 'stroke-width' => 2]);
    $native = \Filament\Support\generate_icon_html('heroicon-o-user', attributes: $attributes, size: $size)->toHtml();
    expect($renderer->render('heroicon-o-user', $size, $attributes))->toBe($native)
        ->and($renderer->render('heroicon-o-user', $size, $attributes))->toBe($native);
})->with(IconSize::cases());

it('never retains custom HTML or owner-specific attributes', function (): void {
    $icon = new class implements Htmlable
    {
        public int $calls = 0;

        public function toHtml(): string
        {
            return '<b>'.++$this->calls.'</b>';
        }
    };
    $renderer = app(RenderedControlIcon::class);
    expect($renderer->render($icon))->toContain('<b>1</b>')
        ->and($renderer->render($icon))->toContain('<b>2</b>');
    foreach (['first', 'second'] as $id) {
        expect($renderer->render('heroicon-o-user', attributes: new ComponentAttributeBag(['id' => $id])))
            ->toContain('id="'.$id.'"');
    }
});

it('invalidates glyphs when the native icon factory or its registrations change', function (): void {
    $original = app(Factory::class);
    $filesystem = new Filesystem;
    $directory = sys_get_temp_dir().'/fff-control-icon-'.bin2hex(random_bytes(8));
    $filesystem->makeDirectory($directory.'/default', 0755, true);
    $filesystem->makeDirectory($directory.'/probe', 0755, true);
    $filesystem->put($directory.'/default/probe-user.svg', '<svg viewBox="0 0 1 1"><path id="original"/></svg>');
    $filesystem->put($directory.'/probe/user.svg', '<svg viewBox="0 0 1 1"><path id="registered"/></svg>');
    try {
        $factory = new Factory($filesystem, new IconsManifest($filesystem, $directory.'/manifest.php'));
        $factory->add('default', ['prefix' => '', 'path' => $directory.'/default']);
        app()->instance(Factory::class, $factory);
        $renderer = app(RenderedControlIcon::class);
        expect($renderer->render('probe-user'))->toContain('id="original"');
        $factory->add('probe', ['prefix' => 'probe', 'path' => $directory.'/probe']);
        expect($renderer->render('probe-user'))->toContain('id="registered"');
        $replacement = new Factory($filesystem, new IconsManifest($filesystem, $directory.'/other.php'), config: ['class' => 'replacement']);
        $replacement->add('probe', ['prefix' => 'probe', 'path' => $directory.'/probe']);
        app()->instance(Factory::class, $replacement);
        expect($renderer->render('probe-user'))->toContain('replacement');
    } finally {
        app()->instance(Factory::class, $original);
        $filesystem->deleteDirectory($directory);
    }
});
