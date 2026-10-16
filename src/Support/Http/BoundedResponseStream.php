<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Http;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/** Enforces the limit on decoded body bytes, including compressed responses. */
final class BoundedResponseStream implements StreamInterface
{
    use StreamDecoratorTrait;

    private StreamInterface $stream;

    private int $bytesWritten = 0;

    public function __construct(StreamInterface $stream, private readonly int $maxBytes)
    {
        $this->stream = $stream;
    }

    public function write(string $string): int
    {
        if (strlen($string) > $this->maxBytes - $this->bytesWritten) {
            throw new RuntimeException('Remote response exceeds its decoded byte budget.');
        }

        $written = $this->stream->write($string);
        $this->bytesWritten += $written;

        return $written;
    }
}
