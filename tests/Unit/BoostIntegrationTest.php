<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FontSelect;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SignatureField;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

it('ships a discoverable skill with resolvable progressive references', function (): void {
    $root = dirname(__DIR__, 2).'/resources/boost';
    $skill = $root.'/skills/flex-fields-development/SKILL.md';
    $source = file_get_contents($skill);
    preg_match('/\A---\n(.*?)\n---/s', $source, $frontmatter);
    $metadata = Yaml::parse($frontmatter[1]);

    expect($metadata['name'])->toBe('flex-fields-development')
        ->and($metadata['description'])->toBeString()->not->toBeEmpty()
        ->and(is_file($root.'/guidelines/core.blade.php'))->toBeTrue();

    preg_match_all('/\]\((references\/[^)]+)\)/', $source, $links);
    expect(count($links[1]))->toBeGreaterThan(0);
    foreach ($links[1] as $reference) {
        expect(is_file(dirname($skill).'/'.$reference))->toBeTrue();
    }
});

it('executes the shipped skill PHP configurations against the installed API', function (): void {
    $root = dirname(__DIR__, 2).'/resources/boost/skills/flex-fields-development/references/';
    foreach (['fields', 'pickers', 'calculations'] as $reference) {
        preg_match_all('/```php\n(.*?)\n```/s', file_get_contents($root.$reference.'.md'), $blocks);
        expect($blocks[1])->not->toBeEmpty();
        foreach ($blocks[1] as $block) {
            $result = (static function (string $php): array {
                eval($php);

                return get_defined_vars();
            })($block);
            if ($reference === 'calculations') {
                expect($result['values']['total'])->toEqual(480)
                    ->and(fn () => $result['plan']->evaluate(['price' => -1, 'quantity' => 2])->validated())
                    ->toThrow(ValidationException::class);
            }
        }
    }

    expect(SignatureField::make('signature'))->toBeInstanceOf(SignatureField::class)
        ->and(FontSelect::make('heading_font')->previewSelectedFont(false))->toBeInstanceOf(FontSelect::class);
});

it('keeps new public guides navigable without referencing private maintenance reports', function (): void {
    $root = dirname(__DIR__, 2);
    $navigation = json_encode(json_decode(file_get_contents($root.'/docs.json'), true, flags: JSON_THROW_ON_ERROR)['navigation'], JSON_UNESCAPED_SLASHES);
    foreach (['ai-assisted-development.md', 'choosing-components.md', 'extensions/ai-guidance.mdx', 'recipes.md', 'scoped-select-recipe.md', 'file-upload-resource-recipe.md', 'verification.md'] as $page) {
        $source = file_get_contents($root.'/docs/'.$page);
        $slug = preg_replace('/\.(?:md|mdx)$/', '', $page);
        expect($navigation)->toContain('docs/'.$slug)
            ->and($source)->not->toContain('.internal/');
        preg_match_all('~(?:href="|\]\()/docs/([a-z0-9/-]+)~', $source, $links);
        foreach ($links[1] as $link) {
            expect(is_file($root.'/docs/'.$link.'.md') || is_file($root.'/docs/'.$link.'.mdx') || is_file($root.'/docs/'.$link.'/index.mdx')
                || is_file($root.'/docs/'.$link.'/index.md'))->toBeTrue();
        }
    }
});

it('retains consumer AI resources and excludes maintainer instructions in an actual distribution archive', function (): void {
    $root = dirname(__DIR__, 2);
    $directory = sys_get_temp_dir().'/fff-boost-distribution-'.bin2hex(random_bytes(8));
    $files = new Filesystem;
    $files->makeDirectory($directory, 0755, true);
    $files->copy($root.'/.gitattributes', $directory.'/.gitattributes');
    $files->copyDirectory($root.'/resources/boost', $directory.'/resources/boost');
    $files->copyDirectory($root.'/resources/recipes', $directory.'/resources/recipes');
    $files->put($directory.'/AGENTS.md', 'Private maintenance instructions');
    $files->makeDirectory($directory.'/.internal');
    $files->put($directory.'/.internal/report.md', 'Private report');

    try {
        foreach ([['git', 'init', '--quiet'], ['git', 'add', '.'],
            ['git', '-c', 'user.name=FLEX Distribution Test', '-c', 'user.email=test@example.invalid', '-c', 'commit.gpgsign=false',
                'commit', '--quiet', '-m', 'Temporary distribution fixture'],
            ['git', 'archive', '--format=tar', '--output='.$directory.'/package.tar', 'HEAD']] as $command) {
            (new Process($command, $directory))->setTimeout(20)->mustRun();
        }
        $archive = (new Process(['tar', '-tf', $directory.'/package.tar'], $directory))->mustRun()->getOutput();
        expect($archive)->toContain('resources/boost/guidelines/core.blade.php', 'resources/boost/skills/flex-fields-development/SKILL.md',
            'resources/boost/skills/flex-fields-development/references/media.md',
            'resources/recipes/post-gallery/app/Support/GalleryRows.php',
            'resources/recipes/scoped-select/app/Support/AssigneePicker.php')
            ->not->toContain('AGENTS.md', '.internal/');
    } finally {
        $files->deleteDirectory($directory);
    }
});
