<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\RichEditor;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Generate privately before publishing; local and remote disks share the same path. */
class RichEditorAttachmentVariantGenerator
{
    /** @param list<RichEditorImageVariant> $variants */
    public function generate(Filesystem $disk, string $masterPath, array $variants): RichEditorAttachmentManifest
    {
        $variants = RichEditorImageVariant::ensureMasterVariant($variants);
        if ($variants === []) {
            return new RichEditorAttachmentManifest(master: $masterPath);
        }

        $root = sys_get_temp_dir().'/flex-rich-images-'.Str::uuid();
        $local = Storage::build(['driver' => 'local', 'root' => $root, 'throw' => true]);
        $published = [];
        $replacedOriginal = false;
        $visibility = null;
        try {
            $source = $disk->readStream($masterPath);
            if (! is_resource($source)) {
                throw new RuntimeException('Unable to read the rich editor attachment.');
            }
            try {
                $local->makeDirectory('');
                $output = fopen($local->path('original'), 'wb');
                if (! is_resource($output)) {
                    throw new RuntimeException('Unable to stage the rich editor attachment.');
                }
                try {
                    $bytes = stream_copy_to_stream($source, $output, 64 * 1024 * 1024 + 1);
                    if ($bytes === false || $bytes > 64 * 1024 * 1024) {
                        throw new RuntimeException('Rich editor image processing is limited to 64 MiB per attachment.');
                    }
                } finally {
                    fclose($output);
                }
            } finally {
                fclose($source);
            }
            $size = @getimagesize($local->path('original'));
            if (! is_array($size)) {
                return new RichEditorAttachmentManifest(master: $masterPath);
            }
            if ($size[0] * $size[1] > 50_000_000) {
                throw new RuntimeException('Rich editor image processing is limited to 50 megapixels.');
            }

            $masterVariant = collect($variants)->first(fn (RichEditorImageVariant $variant): bool => $variant->master) ?? $variants[0];
            $extension = strtolower(pathinfo($masterPath, PATHINFO_EXTENSION));
            $local->copy('original', 'master.'.($extension ?: 'jpg'));
            $processed = $masterVariant->toImageProcessor()->process($local, 'master.'.($extension ?: 'jpg'));
            $directory = dirname($masterPath);
            $finalMaster = ($directory === '.' ? '' : $directory.'/').pathinfo($masterPath, PATHINFO_FILENAME).'.'.pathinfo($processed, PATHINFO_EXTENSION);
            $targets = [$finalMaster => $processed];
            $generated = [];
            $widths = ['master' => $this->width($local->path($processed))];
            foreach ($variants as $variant) {
                if ($variant->master) {
                    continue;
                }
                // Every variant starts from the source, avoiding repeated resampling.
                $input = $variant->name.'.'.($extension ?: 'jpg');
                $local->copy('original', $input);
                $result = $variant->toImageProcessor()->process($local, $input);
                $target = RichEditorAttachmentPaths::variantPath($finalMaster, $variant->name, pathinfo($result, PATHINFO_EXTENSION) === 'webp');
                $targets[$target] = $result;
                $generated[$variant->name] = $target;
                $widths[$variant->name] = $this->width($local->path($result));
            }
            $manifest = new RichEditorAttachmentManifest(master: $finalMaster, variants: $generated, widths: $widths);
            foreach ([...array_keys($targets), $manifest->manifestPath()] as $target) {
                if ($target !== $masterPath && $disk->exists($target)) {
                    throw new RuntimeException('An attachment variant already exists at the requested path.');
                }
            }
            $visibility = $disk->getVisibility($masterPath);
            foreach ($targets as $target => $sourcePath) {
                $stream = $local->readStream($sourcePath);
                if (! is_resource($stream)) {
                    throw new RuntimeException('Unable to read the generated attachment.');
                }
                $published[] = $target;
                $replacedOriginal = $replacedOriginal || $target === $masterPath;
                try {
                    if (! $disk->put($target, $stream, ['visibility' => $visibility])) {
                        throw new RuntimeException('Unable to publish an attachment variant.');
                    }
                } finally {
                    fclose($stream);
                }
            }
            $published[] = $manifest->manifestPath();
            if (! $disk->put($manifest->manifestPath(), json_encode($manifest->toArray(), JSON_THROW_ON_ERROR), ['visibility' => $visibility])) {
                throw new RuntimeException('Unable to publish the attachment manifest.');
            }
            if ($finalMaster !== $masterPath && ! $disk->delete($masterPath)) {
                throw new RuntimeException('Unable to remove the superseded original attachment.');
            }

            return $manifest;
        } catch (Throwable $error) {
            foreach ($published as $target) {
                if ($target !== $masterPath) {
                    rescue(fn () => $disk->delete($target), report: true);
                }
            }
            if ($replacedOriginal) {
                $original = $local->readStream('original');
                if (is_resource($original)) {
                    try {
                        $disk->put($masterPath, $original, $visibility === null ? [] : ['visibility' => $visibility]);
                    } finally {
                        fclose($original);
                    }
                }
            }
            throw $error;
        } finally {
            $local->deleteDirectory('');
        }
    }

    private function width(string $path): int
    {
        $size = @getimagesize($path);
        if (! is_array($size)) {
            throw new RuntimeException('The generated attachment is not a readable image.');
        }

        return $size[0];
    }
}
