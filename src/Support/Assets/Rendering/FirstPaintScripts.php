<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Rendering;

use Bjanczak\FilamentFlexFields\Support\Assets\Build\AlpineBuildManifest;
use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;
use RuntimeException;

/** Request-scoped code emission; never owns field activation or DOM state. */
final class FirstPaintScripts
{
    /** @var array<string, string> */
    private array $invocations = [];

    public function inline(string $name): string
    {
        if (isset($this->invocations[$name])) {
            return $this->invocations[$name];
        }

        $file = RequestState::resolve(AlpineBuildManifest::class)->firstPaintFile($name);
        $path = dirname(__DIR__, 4).'/resources/dist/components/'.$file;
        if (! is_readable($path)) {
            return '';
        }
        $contents = (string) file_get_contents($path);
        if (preg_match('/\A\/\/ fff-first-paint:'.preg_quote($name, '/').':([a-f0-9]{64})\R/', $contents, $matches) !== 1) {
            throw new RuntimeException('Invalid first-paint build contract; rebuild FLEX assets.');
        }
        $key = json_encode('fff.first-paint.'.$name.'.v1', JSON_THROW_ON_ERROR);
        $version = json_encode($matches[1], JSON_THROW_ON_ERROR);
        $this->invocations[$name] = '(function(){const api=globalThis[Symbol.for('.$key.')];'
            .'if(!api||api.version!=='.$version.')throw new Error("FLEX first-paint incompatible; reload required.");'
            .'api.paint(document.currentScript)})();';

        return $contents;
    }

    /** Standalone renderers must start a new emission scope for each response. */
    public function reset(): void
    {
        $this->invocations = [];
    }
}
