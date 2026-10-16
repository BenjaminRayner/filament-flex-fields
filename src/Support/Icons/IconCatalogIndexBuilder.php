<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Icons;

use Closure;

/** Build one policy-scoped index; partitions also own the set counts. */
final class IconCatalogIndexBuilder
{
    public const string CACHE_REVISION = 'admitted-policy-v2';

    /**
     * @param  array<string, array{prefix: string, label: string, icons: list<string>}>  $catalog
     * @param  list<string>  $whitelist
     * @param  list<string>  $exclude
     * @param  Closure(string): string  $formatLabel
     */
    public function build(array $catalog, array $whitelist, array $exclude, ?int $limitPerSet, Closure $formatLabel): IconCatalogIndex
    {
        $excluded = array_fill_keys(array_map(static fn (string $name): string => mb_strtolower(trim($name)), $exclude), true);
        $entries = [];
        $allowed = [];
        $partitions = [];
        $limit = $limitPerSet === null ? null : max(0, $limitPerSet);
        $candidates = [];

        if ($whitelist !== []) {
            // Only whitelist mode needs an identity -> set map.
            $membership = [];
            foreach ($catalog as $set => $data) {
                foreach ($data['icons'] as $name) {
                    $membership[$name] ??= $set;
                }
            }
            foreach ($whitelist as $name) {
                $name = trim($name);
                if (isset($membership[$name])) {
                    $candidates[$membership[$name]][] = $name;
                }
            }
        } else {
            foreach ($catalog as $set => $data) {
                $candidates[$set] = $data['icons'];
            }
        }

        foreach ($candidates as $set => $names) {
            $count = 0;
            foreach ($names as $name) {
                if ($limit !== null && $count >= $limit) {
                    break;
                }
                if ($name === '' || isset($allowed[$name]) || isset($excluded[mb_strtolower($name)])) {
                    continue;
                }
                $label = $formatLabel($name);
                $entry = [
                    'name' => $name,
                    'label' => $label,
                    'set' => $set,
                    'nameLower' => mb_strtolower($name),
                    'labelLower' => mb_strtolower($label),
                ];
                $entries[] = $entry;
                $partitions[$set][] = $entry;
                $allowed[$name] = true;
                $count++;
            }
        }

        if ($whitelist !== []) {
            usort($entries, static fn (array $left, array $right): int => strnatcasecmp($left['name'], $right['name']) ?: strcmp($left['name'], $right['name']));
            // Keep the scoped dropdown order identical to the full whitelist order.
            $partitions = [];
            foreach ($entries as $entry) {
                $partitions[$entry['set']][] = $entry;
            }
        }

        $summaries = [];
        foreach ($catalog as $set => $data) {
            if (! isset($partitions[$set])) {
                continue;
            }
            $summaries[] = [
                'key' => $set,
                'prefix' => $data['prefix'],
                'label' => $data['label'],
                'count' => count($partitions[$set]),
            ];
        }

        return new IconCatalogIndex($entries, $partitions, $allowed, $summaries);
    }
}
