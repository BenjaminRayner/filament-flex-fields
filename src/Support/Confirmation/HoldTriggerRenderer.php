<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Confirmation;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/** Build the two presentation layers once on the server, retaining native button markup. */
final class HoldTriggerRenderer
{
    public static function render(string $html, string $sweep, string $feedback = ''): string
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NONET);
            $buttons = (new DOMXPath($document))->query('//body//button | //body//a');
            $button = $buttons === false ? null : $buttons->item(0);
            if (! $button instanceof DOMElement) {
                return $html;
            }
            $content = [];
            $loading = [];
            foreach (iterator_to_array($button->childNodes) as $node) {
                if ($node instanceof DOMElement && self::isLoading($node)) {
                    $loading[] = $node;
                } elseif ($node->nodeType !== XML_COMMENT_NODE && trim($node->textContent ?? '') !== '') {
                    $content[] = $node;
                } elseif ($node instanceof DOMElement) {
                    $content[] = $node;
                }
            }
            while ($button->firstChild) {
                $button->removeChild($button->firstChild);
            }
            foreach (['overlay', 'base'] as $role) {
                $layer = $document->createElement('span');
                $layer->setAttribute('class', 'fff-hold-confirm-action__'.$role);
                $layer->setAttribute('data-slot', 'hold-confirm-'.$role);
                if ($role === 'overlay') {
                    $layer->setAttribute('aria-hidden', 'true');
                    $layer->setAttribute('data-sweep', $sweep);
                }
                foreach ($content as $node) {
                    $copy = $node->cloneNode(true);
                    if ($role === 'overlay') {
                        self::removeIds($copy);
                    }
                    $layer->appendChild($copy);
                }
                $button->appendChild($layer);
            }
            if ($feedback === '') {
                foreach ($loading as $node) {
                    $button->appendChild($node);
                }
            }
            if ($feedback !== '') {
                $fragment = new DOMDocument;
                $fragment->loadHTML('<?xml encoding="UTF-8"><html><body>'.$feedback.'</body></html>', LIBXML_NONET);
                $body = $fragment->getElementsByTagName('body')->item(0);
                if ($body !== null) {
                    foreach ($body->childNodes as $node) {
                        $button->appendChild($document->importNode($node, true));
                    }
                }
            }
            foreach (iterator_to_array($button->attributes) as $attribute) {
                if (str_starts_with($attribute->name, 'wire:loading') || $attribute->name === 'wire:target') {
                    $button->removeAttribute($attribute->name);
                }
            }
            $button->setAttribute('data-fff-hold-layers-ready', 'true');
            $button->setAttribute('data-confirm-role', 'hold');

            return $document->saveHTML($button) ?: $html;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function isLoading(DOMElement $element): bool
    {
        foreach ($element->attributes as $attribute) {
            if (str_starts_with($attribute->name, 'wire:loading') || $attribute->name === 'x-cloak') {
                return true;
            }
        }

        return false;
    }

    private static function removeIds(DOMNode $node): void
    {
        if ($node instanceof DOMElement) {
            $node->removeAttribute('id');
        }
        foreach ($node->childNodes as $child) {
            self::removeIds($child);
        }
    }
}
