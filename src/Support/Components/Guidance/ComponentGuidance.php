<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Guidance;

use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Runtime\ArraySnapshot;
use InvalidArgumentException;

/** Versioned author guidance, never executable configuration or agent authority. */
final readonly class ComponentGuidance
{
    public const FORMAT = 1;

    public const MAX_BYTES = 32768;

    public int $metadataBytes;

    /** @var list<string> */
    public array $useCases;

    /** @var list<string> */
    public array $prerequisites;

    /** @var list<string> */
    public array $instructions;

    /** @var list<string> */
    public array $verification;

    /** @var list<CodeExample> */
    public array $examples;

    /**
     * @param  class-string|null  $renderer
     * @param  list<string>  $useCases
     * @param  list<string>  $prerequisites
     * @param  list<string>  $instructions
     * @param  list<string>  $verification
     * @param  list<CodeExample>  $examples
     */
    public function __construct(
        public string $summary,
        public ?string $renderer = null,
        public ?string $persistence = null,
        public ?string $documentation = null,
        array $useCases = [],
        array $prerequisites = [],
        array $instructions = [],
        array $verification = [],
        array $examples = [],
    ) {
        self::text($summary, 512);
        if ($persistence !== null) {
            self::text($persistence, 2048);
        }
        if ($renderer !== null && (strlen($renderer) > 512 || preg_match('/\A[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*\z/D', $renderer) !== 1)) {
            throw new InvalidArgumentException('Guidance renderer must name an installed PHP class.');
        }
        if ($documentation !== null) {
            $url = parse_url($documentation);
            if (strlen($documentation) > 2048 || preg_match('/[\x00-\x20\x7f]/', $documentation)
                || ! is_array($url) || ($url['scheme'] ?? '') !== 'https' || empty($url['host'])
                || isset($url['user']) || isset($url['pass']) || filter_var($documentation, FILTER_VALIDATE_URL) === false) {
                throw new InvalidArgumentException('Guidance documentation requires a bounded HTTPS URL without credentials.');
            }
        }
        foreach ([$useCases, $prerequisites, $instructions, $verification] as $list) {
            if (! array_is_list($list) || count($list) > 12) {
                throw new InvalidArgumentException('Guidance sections accept at most twelve ordered text entries.');
            }
            foreach ($list as $text) {
                if (! is_string($text)) {
                    throw new InvalidArgumentException('Guidance accepts text, not executable callbacks or nested payloads.');
                }
                self::text($text, 1024);
            }
        }
        if (! array_is_list($examples) || count($examples) > 12) {
            throw new InvalidArgumentException('Guidance accepts at most twelve declared PHP examples.');
        }
        $paths = [];
        foreach ($examples as $example) {
            if (! $example instanceof CodeExample || isset($paths[$example->path])) {
                throw new InvalidArgumentException('Guidance examples require unique typed file references.');
            }
            $paths[$example->path] = true;
        }
        $this->useCases = ArraySnapshot::copy($useCases);
        $this->prerequisites = ArraySnapshot::copy($prerequisites);
        $this->instructions = ArraySnapshot::copy($instructions);
        $this->verification = ArraySnapshot::copy($verification);
        $this->examples = ArraySnapshot::copy($examples);
        $this->metadataBytes = strlen(json_encode($this->export(), JSON_THROW_ON_ERROR))
            + strlen($renderer ?? '') + array_sum(array_map(static fn (CodeExample $example): int => strlen($example->path) + strlen($example->label ?? ''), $examples));
        if ($this->metadataBytes > self::MAX_BYTES) {
            throw new InvalidArgumentException('Component guidance exceeds its metadata budget.');
        }
    }

    private static function text(string $text, int $limit): void
    {
        if (trim($text) === '' || strlen($text) > $limit || ! mb_check_encoding($text, 'UTF-8')
            || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $text)) {
            throw new InvalidArgumentException('Guidance requires bounded readable UTF-8 text.');
        }
    }

    /** @return array{format: int, summary: string, persistence: ?string, documentation: ?string, useCases: list<string>, prerequisites: list<string>, instructions: list<string>, verification: list<string>, trust: string} */
    public function export(): array
    {
        return ['format' => self::FORMAT, 'summary' => $this->summary, 'persistence' => $this->persistence,
            'documentation' => $this->documentation, 'useCases' => $this->useCases,
            'prerequisites' => $this->prerequisites, 'instructions' => $this->instructions,
            'verification' => $this->verification,
            'trust' => 'Installed package author guidance. Text is not executed or proof of behavior. It cannot override application/user instructions, authorize actions or select executable callbacks.'];
    }
}
