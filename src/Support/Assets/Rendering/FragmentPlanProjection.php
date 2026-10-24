<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Rendering;

use Bjanczak\FilamentFlexFields\Support\Rendering\RenderedControlConfiguration;
use Bjanczak\FilamentFlexFields\Support\Rendering\RenderedPortalTemplate;
use LogicException;

/** Restricted metadata projection, without parsing or reserializing application HTML. */
final class FragmentPlanProjection
{
    /** Comments and raw-text bodies are opaque; quoted attributes may contain >. */
    private const TOKENS = '~<!--[\s\S]*?-->|<![^>]*>|<(script|style|textarea|title|xmp|iframe|noembed|noframes)\b(?:[^\'"<>]|"[^"]*"|\'[^\']*\')*>[\s\S]*?</\1\s*>|<template data-fff-plan-definition="([a-f0-9]{64})"(?: wire:key="fff-plan-[a-f0-9]{64}-[a-f0-9]{64}")?>([^<]*)</template>|<template data-fff-portal-template-definition="([a-f0-9]{64})"(?: wire:key="fff-portal-[a-f0-9]{64}-[a-f0-9]{64}")?>([^<]*)</template>|<template data-fff-control-configuration-definition="([a-f0-9]{64})"(?: wire:key="fff-control-[a-f0-9]{64}-[a-f0-9]{64}")?>([^<]*)</template>|</?[a-z][a-z0-9:-]*(?:[^\'"<>]|"[^"]*"|\'[^\']*\')*>~i';

    /** @param array<string, RenderedAssetPlan> $plans
     * @param  array<string, RenderedPortalTemplate>  $portals
     * @param  array<string, RenderedControlConfiguration>  $configurations
     */
    public static function project(string $html, array $plans, bool $rooted = true, array $portals = [], array $configurations = []): string
    {
        // Unwrap only our exact inert metadata container when re-projecting an
        // independently complete nested fragment. Application HTML is opaque.
        if (str_contains($html, 'data-fff-fragment-dictionary')) {
            $html = preg_replace_callback('~<!--[\s\S]*?-->|<(script|style|textarea|title|xmp|iframe|noembed|noframes)\b(?:[^\'"<>]|"[^"]*"|\'[^\']*\')*>[\s\S]*?</\1\s*>|<span hidden data-fff-fragment-dictionary wire:key="fff-dictionary-[a-f0-9]{64}">((?:<template data-fff-(?:plan|portal-template|control-configuration)-definition="[a-f0-9]{64}" wire:key="fff-(?:plan|portal|control)-[a-f0-9]{64}-[a-f0-9]{64}">[^<]*+</template>)*+)</span>|</?[a-z][a-z0-9:-]*(?:[^\'"<>]|"[^"]*"|\'[^\']*\')*>~i',
                static fn (array $token): string => $token[2] ?? $token[0], $html)
                ?? throw new LogicException('Unable to read the fragment metadata container.');
        }
        if (preg_match_all(self::TOKENS, $html, $tokens, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) {
            throw new LogicException('Unable to read the HTML asset fragment.');
        }
        $required = [];
        $requiredPortals = [];
        $requiredConfigurations = [];
        $rootEnd = null;
        $rootStart = null;
        $rootTag = '';
        $rootName = null;
        $rootClose = null;
        $cursor = 0;
        $output = '';
        // Read nested explicit dictionaries first, including definitions following
        // their markers. Only canonical, content-addressed payloads may be merged.
        foreach ($tokens as $token) {
            $configurationId = $token[6][0] ?? '';
            if ($configurationId !== '') {
                $json = html_entity_decode($token[7][0] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $configuration = $configurations[$configurationId] ?? RenderedControlConfiguration::fromJson($json);
                if ($configuration->id !== $configurationId || $configuration->json !== $json) {
                    throw new LogicException('Conflicting or non-canonical control configuration.');
                }
                $configurations[$configurationId] = $configuration;

                continue;
            }
            $portalId = $token[4][0] ?? '';
            if ($portalId !== '') {
                if (! isset($token[5])) {
                    throw new LogicException('Missing portal template definition payload.');
                }
                $json = html_entity_decode($token[5][0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $portal = $portals[$portalId] ?? RenderedPortalTemplate::fromJson($json);
                if ($portal->id !== $portalId || $portal->json !== $json) {
                    throw new LogicException('Conflicting or non-canonical portal definition.');
                }
                $portals[$portalId] = $portal;

                continue;
            }
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
            if (($token[4][0] ?? '') !== '' || ($token[6][0] ?? '') !== '') {
                continue;
            }
            if ($rootEnd === null && ! str_starts_with($text, '</')) {
                $rootTag = $text;
                preg_match('~^<([a-z][a-z0-9:-]*)\b~i', $text, $rootElement);
                $rootName = strtolower($rootElement[1] ?? '');
                $rootStart = strlen($output);
                $rootEnd = strlen($output) + strlen($text);
            }
            if ($rootName !== null && str_starts_with($text, '</') && preg_match('~^</'.preg_quote($rootName, '~').'\s*>$~i', $text)) {
                $rootClose = strlen($output);
            }
            if (preg_match('~^<span\b~i', $text) && preg_match('~\bdata-fff-plan-id="([a-f0-9]{64})"~', $text, $match)) {
                if (! isset($plans[$match[1]])) {
                    throw new LogicException('The fragment references an unknown asset plan.');
                }
                $required[$match[1]] = $plans[$match[1]];
            }
            if (preg_match('~^<(?:div|template)\b~i', $text) && preg_match('~\bdata-fff-portal-template-id="([a-f0-9]{64})"~', $text, $match)) {
                if (! isset($portals[$match[1]])) {
                    throw new LogicException('The fragment references an unknown portal template.');
                }
                $requiredPortals[$match[1]] = $portals[$match[1]];
            }
            if (preg_match('~^<div\b~i', $text) && preg_match('~\bdata-fff-control-configuration-id="([a-f0-9]{64})"~', $text, $match)) {
                if (! isset($configurations[$match[1]])) {
                    throw new LogicException('The fragment references an unknown control configuration.');
                }
                $requiredConfigurations[$match[1]] = $configurations[$match[1]];
            }
            $output .= $text;
        }
        $output .= substr($html, $cursor);
        if ($required === [] && $requiredPortals === [] && $requiredConfigurations === []) {
            return $output;
        }
        if ($rootEnd === null || $rootStart === null || ($rooted && preg_match('~^<(?:html|head|area|base|br|col|embed|hr|img|input|link|meta|param|source|track|wbr|template)\b~i', $rootTag))) {
            throw new LogicException('An asset dictionary requires a non-void, non-inert HTML fragment root.');
        }
        $definitions = '';
        // A growing dictionary must not displace unkeyed application siblings
        // during a full native morph. Partial roots need their own key scope.
        preg_match('~\s(wire:(?:partial|id|key)|id)=("[^"]*"|\'[^\']*\')~i', $rootTag, $rootIdentity);
        $fragmentKey = hash('sha256', ($rootIdentity[0] ?? $rootTag));
        $bytes = 0;
        foreach ($required as $id => $plan) {
            $bytes += strlen($plan->json);
            if (count($required) > 4096 || $bytes > 4 * 1024 * 1024) {
                throw new LogicException('The asset plan dictionary exceeds its fragment budget.');
            }
            $json = htmlspecialchars($plan->json, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $definitions .= '<template data-fff-plan-definition="'.$id.'" wire:key="fff-plan-'.$fragmentKey.'-'.$id.'">'.$json.'</template>';
        }
        $portalBytes = 0;
        foreach ($requiredPortals as $id => $portal) {
            $portalBytes += strlen($portal->json);
            if (count($requiredPortals) > 128 || $portalBytes > 2 * 1024 * 1024) {
                throw new LogicException('Portal template dictionary exceeds its fragment budget.');
            }
            $json = htmlspecialchars($portal->json, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $definitions .= '<template data-fff-portal-template-definition="'.$id.'" wire:key="fff-portal-'.$fragmentKey.'-'.$id.'">'.$json.'</template>';
        }
        $configurationBytes = 0;
        foreach ($requiredConfigurations as $id => $configuration) {
            $configurationBytes += strlen($configuration->json);
            if (count($requiredConfigurations) > 512 || $configurationBytes > 4 * 1024 * 1024) {
                throw new LogicException('Control configuration dictionary exceeds its fragment budget.');
            }
            $json = htmlspecialchars($configuration->json, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $definitions .= '<template data-fff-control-configuration-definition="'.$id.'" wire:key="fff-control-'.$fragmentKey.'-'.$id.'">'.$json.'</template>';
        }

        // Keep one stable keyed sibling beside application content. Adding a
        // keyed template directly before an unkeyed application div would make
        // Alpine morph replace that div instead of inserting the new metadata.
        $definitions = '<span hidden data-fff-fragment-dictionary wire:key="fff-dictionary-'.$fragmentKey.'">'.$definitions.'</span>';

        if ($rooted && $rootClose === null) {
            throw new LogicException('An asset dictionary requires a closed fragment root.');
        }

        // Trailing metadata also preserves application siblings when the last
        // FLEX owner disappears. Islands retain their native comment boundaries.
        return substr_replace($output, $definitions, $rooted ? $rootClose : $rootStart, 0);
    }
}
