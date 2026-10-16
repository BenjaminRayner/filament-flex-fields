<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Rendering;

use LogicException;

/** Restricted metadata projection, without parsing or reserializing application HTML. */
final class FragmentPlanProjection
{
    /** Comments and raw-text bodies are opaque; quoted attributes may contain >. */
    private const TOKENS = '~<!--[\s\S]*?-->|<![^>]*>|<(script|style|textarea|title|xmp|iframe|noembed|noframes)\b(?:[^\'"<>]|"[^"]*"|\'[^\']*\')*>[\s\S]*?</\1\s*>|<template data-fff-plan-definition="([a-f0-9]{64})">([^<]*)</template>|</?[a-z][a-z0-9:-]*(?:[^\'"<>]|"[^"]*"|\'[^\']*\')*>~i';

    /** @param array<string, RenderedAssetPlan> $plans */
    public static function project(string $html, array $plans, bool $rooted = true): string
    {
        if (preg_match_all(self::TOKENS, $html, $tokens, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) {
            throw new LogicException('Unable to read the HTML asset fragment.');
        }
        $required = [];
        $rootEnd = null;
        $rootStart = null;
        $rootTag = '';
        $cursor = 0;
        $output = '';
        // Read nested explicit dictionaries first, including definitions following
        // their markers. Only canonical, content-addressed payloads may be merged.
        foreach ($tokens as $token) {
            $id = $token[2][0] ?? '';
            if ($id === '') {
                continue;
            }
            if (! isset($token[3])) {
                throw new LogicException('Missing asset plan definition payload.');
            }
            $json = html_entity_decode($token[3][0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $plan = $plans[$id] ?? RenderedAssetPlan::fromJson($json);
            if ($plan->id !== $id || $plan->json !== $json) {
                throw new LogicException('Conflicting or non-canonical asset plan definition in fragment.');
            }
            $plans[$id] = $plan;
        }
        foreach ($tokens as $token) {
            [$text, $offset] = $token[0];
            $output .= substr($html, $cursor, $offset - $cursor);
            $cursor = $offset + strlen($text);
            if (str_starts_with($text, '<!') || ($token[1][0] ?? '') !== '') {
                $output .= $text;

                continue;
            }
            if (($token[2][0] ?? '') !== '') {
                $plan = $plans[$token[2][0]] ?? null;
                if ($plan === null || ! isset($token[3]) || html_entity_decode($token[3][0], ENT_QUOTES | ENT_HTML5, 'UTF-8') !== $plan->json) {
                    throw new LogicException('Conflicting or unknown asset plan definition in fragment.');
                }

                continue;
            }
            if ($rootEnd === null && ! str_starts_with($text, '</')) {
                $rootTag = $text;
                $rootStart = strlen($output);
                $rootEnd = strlen($output) + strlen($text);
            }
            if (preg_match('~^<span\b~i', $text) && preg_match('~\bdata-fff-plan-id="([a-f0-9]{64})"~', $text, $match)) {
                if (! isset($plans[$match[1]])) {
                    throw new LogicException('The fragment references an unknown asset plan.');
                }
                $required[$match[1]] = $plans[$match[1]];
            }
            $output .= $text;
        }
        $output .= substr($html, $cursor);
        if ($required === []) {
            return $output;
        }
        if ($rootEnd === null || $rootStart === null || ($rooted && preg_match('~^<(?:html|head|area|base|br|col|embed|hr|img|input|link|meta|param|source|track|wbr|template)\b~i', $rootTag))) {
            throw new LogicException('An asset dictionary requires a non-void, non-inert HTML fragment root.');
        }
        $definitions = '';
        $bytes = 0;
        foreach ($required as $id => $plan) {
            $bytes += strlen($plan->json);
            if (count($required) > 4096 || $bytes > 4 * 1024 * 1024) {
                throw new LogicException('The asset plan dictionary exceeds its fragment budget.');
            }
            $json = htmlspecialchars($plan->json, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $definitions .= '<template data-fff-plan-definition="'.$id.'">'.$json.'</template>';
        }

        // Islands keep their native start/end comments and sibling structure.
        // Definitions go inside those comments, before the first real element.
        return substr_replace($output, $definitions, $rooted ? $rootEnd : $rootStart, 0);
    }
}
