<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Theme;

use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\ManifestIdentity;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilyRenderContext;
use InvalidArgumentException;

/** Pure names, shared by PHP and SDK ABI 1; no DOM, record or theme retention. */
final readonly class AppearanceNamespace
{
    public function __construct(public string $namespace)
    {
        ManifestIdentity::assert($namespace);
        if (strlen($namespace) > 48) {
            throw new InvalidArgumentException('Appearance namespace exceeds the component package budget.');
        }
    }

    public function token(string $name): string
    {
        return '--'.$this->name('token', $name);
    }

    public function font(string $name): string
    {
        return $this->name('font', $name);
    }

    public function keyframe(string $name): string
    {
        return $this->name('motion', $name);
    }

    public function svgId(string $instance, string $name): string
    {
        FamilyRenderContext::assertKey($instance);
        self::assertName($name);

        // Injective UTF-8 byte encoding keeps SSR and JS identical without async hashing.
        return 'fff-ext-'.$this->namespace.'--svg-'.bin2hex($instance).'--'.$name;
    }

    /** @param array<string, mixed> $tokens
     * @return array<string, string|int|float>
     */
    public function tokens(array $tokens): array
    {
        if (count($tokens) > ThemeTokenMap::MAX_TOKENS) {
            throw new InvalidArgumentException('Package theme exceeds its token budget.');
        }
        $variables = [];
        foreach ($tokens as $name => $value) {
            if (! is_string($name)) {
                throw new InvalidArgumentException('Package theme token needs a literal name.');
            }
            $variables[$this->token($name)] = $value;
        }

        return ThemeTokenMap::validate($variables);
    }

    private function name(string $kind, string $name): string
    {
        self::assertName($name);

        return 'fff-ext-'.$this->namespace.'--'.$kind.'-'.$name;
    }

    private static function assertName(string $name): void
    {
        ManifestIdentity::assert($name);
        if (strlen($name) > 64) {
            throw new InvalidArgumentException('Appearance name exceeds its local budget.');
        }
    }
}
