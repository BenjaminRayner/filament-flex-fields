<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Http\Html;

/** Incremental head boundary scanner; quoted attributes/comments/raw script text cannot terminate the head. */
final class HeadBoundary
{
    private int $offset = 0;

    private ?string $raw = null;

    public function end(string $html): ?int
    {
        $length = strlen($html);
        while ($this->offset < $length) {
            if ($this->raw !== null) {
                if (! preg_match('~</'.preg_quote($this->raw, '~').'\s*>~i', $html, $match, PREG_OFFSET_CAPTURE, $this->offset)) {
                    return null;
                }
                $this->offset = $match[0][1] + strlen($match[0][0]);
                $this->raw = null;

                continue;
            }
            $start = strpos($html, '<', $this->offset);
            if ($start === false) {
                $this->offset = $length;

                return null;
            }
            if (substr($html, $start, 4) === '<!--') {
                $end = strpos($html, '-->', $start + 4);
                if ($end === false) {
                    $this->offset = $start;

                    return null;
                }
                $this->offset = $end + 3;

                continue;
            }
            $end = $start + 1;
            $quote = null;
            for (; $end < $length; $end++) {
                $char = $html[$end];
                if ($quote !== null) {
                    if ($char === $quote) {
                        $quote = null;
                    }
                } elseif ($char === '"' || $char === "'") {
                    $quote = $char;
                } elseif ($char === '>') {
                    break;
                }
            }
            if ($end === $length) {
                $this->offset = $start;

                return null;
            }
            $tag = substr($html, $start, $end - $start + 1);
            $this->offset = $end + 1;
            if (! preg_match('~^<\s*(/?)\s*([a-z][a-z0-9:-]*)\b~i', $tag, $match)) {
                continue;
            }
            $name = strtolower($match[2]);
            $closing = $match[1] === '/';
            if (($name === 'head' && $closing) || ($name === 'body' && ! $closing)) {
                return $name === 'body' ? $start : $end + 1;
            }
            if (! $closing && in_array($name, ['script', 'style', 'title', 'textarea'], true)) {
                $this->raw = $name;
            }
        }

        return null;
    }
}
