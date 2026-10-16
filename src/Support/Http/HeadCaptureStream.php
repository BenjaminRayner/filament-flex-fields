<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Http;

use Bjanczak\FilamentFlexFields\Support\Http\Html\HeadBoundary;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;

/** Deliberately terminates cURL after capturing the bounded HTML head. */
final class HeadCaptureStream implements StreamInterface
{
    use StreamDecoratorTrait;

    private string $captured = '';

    private bool $complete = false;

    private readonly HeadBoundary $boundary;

    public function __construct(private StreamInterface $stream, private readonly int $limit)
    {
        $this->boundary = new HeadBoundary;
    }

    public function write(string $string): int
    {
        $this->captured .= substr($string, 0, $this->limit - strlen($this->captured));
        $end = $this->boundary->end($this->captured);
        if ($end !== null || strlen($this->captured) >= $this->limit) {
            if ($end !== null) {
                $this->captured = substr($this->captured, 0, $end);
            }
            $this->complete = true;
            throw new HeadCaptureComplete('HTML head captured.');
        }

        return $this->stream->write($string);
    }

    public function captured(): string
    {
        return $this->captured;
    }

    public function complete(): bool
    {
        return $this->complete;
    }
}
