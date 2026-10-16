<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Location\Layers;

use InvalidArgumentException;

/** A semantic stop on an indexed route, independent of its geometry vertices. */
final class MapTrackStop
{
    private string $label;

    private int $wait = 0;

    private function __construct(private readonly string $id, private readonly float $progress)
    {
        if (! preg_match('/^[a-z][a-z0-9-]{0,47}$/D', $id) || ! is_finite($progress) || $progress <= 0 || $progress >= 1) {
            throw new InvalidArgumentException('A stop requires a stable ID and progress strictly between zero and one.');
        }
        $this->label = $id;
    }

    public static function make(string $id, float $progress): self
    {
        return new self($id, $progress);
    }

    public function label(string $label): self
    {
        if ($label === '' || strlen($label) > 256 || preg_match('/[\x00-\x1F\x7F]/', $label)) {
            throw new InvalidArgumentException('Invalid stop label.');
        }
        $this->label = $label;

        return $this;
    }

    /** Recorded playback only; live feeds remain authoritative about actual dwell. */
    public function wait(int $milliseconds): self
    {
        if ($milliseconds < 0 || $milliseconds > 300000) {
            throw new InvalidArgumentException('Stop wait must be between zero and five minutes.');
        }
        $this->wait = $milliseconds;

        return $this;
    }

    /** @return array{id: string, progress: float, label: string, wait: int} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'progress' => $this->progress, 'label' => $this->label, 'wait' => $this->wait];
    }
}
