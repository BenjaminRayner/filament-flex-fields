<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Authoring;

use RuntimeException;
use Throwable;

/** No dependency installation, shell execution, overwrites or changes to a host app. */
final class ComponentPackageGenerator
{
    /** @return array<string, string> Relative path => complete source. */
    public function plan(ComponentPackageBlueprint $blueprint): array
    {
        $root = dirname(__DIR__, 4).'/stubs/component-package';
        $paths = ['composer.json', 'package.json', '.gitignore', 'README.md', 'build.mjs',
            'src/PackageServiceProvider.php', 'src/Forms/Field.php', 'src/Tables/Column.php', 'src/Infolists/Entry.php',
            'src/Support/DisplayValue.php', 'resources/js/field.js', 'resources/css/display.css',
            'resources/views/field.blade.php', 'resources/views/column.blade.php', 'resources/views/entry.blade.php'];
        $result = [];
        foreach ($paths as $path) {
            $source = file_get_contents($root.'/'.$path.'.stub');
            if ($source === false) {
                throw new RuntimeException('Missing component package template: '.$path);
            }
            $path = str_replace(['PackageServiceProvider', '/Field.php', '/Column.php', '/Entry.php'],
                [$blueprint->className.'ServiceProvider', '/'.$blueprint->className.'Field.php', '/'.$blueprint->className.'Column.php', '/'.$blueprint->className.'Entry.php'], $path);
            $result[$path] = strtr($source, $blueprint->substitutions());
        }

        return $result;
    }

    /**
     * Reserve a new directory exclusively. Failure removes only files/directories
     * this call created; unrelated files added by another process survive cleanup.
     *
     * @return list<string> Generated relative paths.
     */
    public function generate(ComponentPackageBlueprint $blueprint, string $destination): array
    {
        if ($destination === '' || strlen($destination) > 4096 || str_contains($destination, "\0")) {
            throw new RuntimeException('Invalid destination directory.');
        }
        $destination = rtrim($destination, '/\\');
        $parent = realpath(dirname($destination));
        $name = basename($destination);
        if ($parent === false || ! is_dir($parent) || ! is_writable($parent) || in_array($name, ['', '.', '..'], true)) {
            throw new RuntimeException('Destination needs an existing writable parent directory.');
        }
        $destination = $parent.DIRECTORY_SEPARATOR.$name;
        $files = $this->plan($blueprint);
        if (file_exists($destination) || is_link($destination) || ! @mkdir($destination, 0755)) {
            throw new RuntimeException('Destination already exists or cannot be reserved; existing directories are never overwritten.');
        }
        $directories = [$destination];
        $created = [];
        try {
            foreach ($files as $relative => $source) {
                $directory = $destination;
                foreach (explode('/', dirname($relative)) as $segment) {
                    if ($segment === '.') {
                        continue;
                    }
                    $directory .= '/'.$segment;
                    if (! in_array($directory, $directories, true)) {
                        if (! @mkdir($directory, 0755)) {
                            throw new RuntimeException('Cannot reserve generated directory: '.$relative);
                        }
                        $directories[] = $directory;
                    }
                }
                $path = $destination.'/'.$relative;
                $handle = @fopen($path, 'x');
                if ($handle === false) {
                    throw new RuntimeException('Cannot exclusively create generated file: '.$relative);
                }
                $created[] = $path;
                try {
                    $offset = 0;
                    while ($offset < strlen($source)) {
                        $bytes = fwrite($handle, substr($source, $offset));
                        if ($bytes === false || $bytes === 0) {
                            throw new RuntimeException('Incomplete generated file: '.$relative);
                        }
                        $offset += $bytes;
                    }
                } finally {
                    fclose($handle);
                }
            }
        } catch (Throwable $error) {
            foreach (array_reverse($created) as $path) {
                @unlink($path);
            }
            foreach (array_reverse($directories) as $directory) {
                @rmdir($directory);
            }
            throw $error;
        }

        return array_keys($files);
    }
}
