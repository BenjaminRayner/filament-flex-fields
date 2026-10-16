<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Values\Submission;

use InvalidArgumentException;

/** One bounded path trie per submission, with no cache of actor or field state. */
final class SubmissionPath
{
    /** @var array<string, self> */
    private array $children = [];

    private bool $terminal = false;

    public function add(string $path): void
    {
        $node = $this;
        foreach (explode('.', $path) as $segment) {
            if ($node->terminal) {
                throw new InvalidArgumentException('Submitted field paths overlap.');
            }
            $node = $node->children[$segment] ??= new self;
        }
        if ($node->terminal || $node->children !== []) {
            throw new InvalidArgumentException('Submitted field paths overlap or are duplicated.');
        }
        $node->terminal = true;
    }

    /** @param array<array-key, mixed> $values */
    public function assertKnownKeys(array $values): void
    {
        foreach ($values as $key => $value) {
            $branch = $this->children[$key] ?? throw new InvalidArgumentException('Submission contains an undeclared field path.');
            if ($branch->terminal) {
                continue;
            }
            if (! is_array($value)) {
                throw new InvalidArgumentException('Submitted path container must be an array.');
            }
            $branch->assertKnownKeys($value);
        }
    }
}
