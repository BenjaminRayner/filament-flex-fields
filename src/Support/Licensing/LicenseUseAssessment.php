<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Licensing;

/**
 * A scope guide for LICENSE v2.0; never an activation service or license grant.
 *
 * @phpstan-type LicenseFinding array{use: string, outcome: string, reason: string, references: list<string>, client: bool}
 * @phpstan-type LicenseAssessment array{outcome: string, reason: string, references: list<string>, client: bool, findings: list<LicenseFinding>}
 */
final class LicenseUseAssessment
{
    public const VERSION = '2.0';

    public const LICENSE_URL = 'https://github.com/janczakb/filament-flex-fields/blob/main/LICENSE';

    public const COMMERCIAL_URL = 'https://github.com/janczakb/filament-flex-fields/blob/main/COMMERCIAL.md';

    public const CONTACT_URL = 'mailto:barek122@gmail.com';

    public const USES = ['personal', 'internal', 'evaluation', 'bespoke_runtime', 'bespoke_client', 'hosted', 'distribution', 'kits', 'oem'];

    public const CONDITIONS = ['free_scope', 'evaluation_private', 'bespoke_runtime_scope', 'direct_client', 'client_free_scope', 'hosted_scope'];

    public const CLIENT_USES = ['internal', 'hosted', 'distribution'];

    /**
     * @param  list<string>  $uses
     * @return list<string>
     */
    public function conditionsFor(array $uses, string $clientUse = ''): array
    {
        $conditions = [];
        if (array_intersect($uses, ['personal', 'internal']) !== []) {
            $conditions[] = 'free_scope';
        }
        foreach (['evaluation' => 'evaluation_private', 'bespoke_runtime' => 'bespoke_runtime_scope'] as $use => $condition) {
            if (in_array($use, $uses, true)) {
                $conditions[] = $condition;
            }
        }
        if (in_array('bespoke_client', $uses, true) && $clientUse !== 'distribution') {
            $conditions[] = 'direct_client';
            if ($clientUse === 'internal') {
                $conditions[] = 'client_free_scope';
            }
        }
        if (in_array('hosted', $uses, true) || (in_array('bespoke_client', $uses, true) && $clientUse === 'hosted')) {
            $conditions[] = 'hosted_scope';
        }

        return $conditions;
    }

    /**
     * Evaluate each selected use independently. A Custom requirement does not
     * reclassify an unrelated hosted product; the agency and its client cannot
     * share one entity's grant merely because they submitted one questionnaire.
     *
     * @param  list<string>  $uses
     * @param  list<string>  $conditions
     * @return LicenseAssessment
     */
    public function assess(array $uses, array $conditions, string $ownership = 'one', string $products = 'one', string $clientUse = '', string $clientOwnership = 'one', string $clientProducts = 'one'): array
    {
        $uses = array_values(array_unique(array_intersect(self::USES, $uses)));
        $conditions = array_values(array_intersect(self::CONDITIONS, $conditions));
        $findings = [];
        foreach ($uses as $use) {
            $findings[] = ['use' => $use, ...$this->assessUse($use, $conditions, $use === 'bespoke_client' ? $clientOwnership : $ownership, $use === 'bespoke_client' ? $clientProducts : $products, $clientUse)];
        }
        if ($findings === []) {
            return [...$this->result('incomplete', 'choose_use', []), 'findings' => []];
        }
        $outcomes = array_unique(array_column($findings, 'outcome'));
        $references = array_values(array_unique(array_merge(...array_column($findings, 'references'))));
        $client = in_array('bespoke_client', $uses, true);
        $separateOwners = in_array('hosted', $uses, true) && $client && $clientUse === 'hosted';
        if (count($outcomes) > 1) {
            $summary = $this->result('mixed', 'mixed_uses', $references, $client);
        } elseif ($separateOwners && ! in_array($findings[0]['outcome'], ['review', 'incomplete'], true)) {
            $summary = $this->result('separate', 'mixed_owners', $references, true);
        } else {
            $summary = $this->result($findings[0]['outcome'], count($findings) > 1 ? 'combined_uses' : $findings[0]['reason'], $references, $client);
        }

        return [...$summary, 'findings' => $findings];
    }

    /** @param list<string> $conditions
     * @return array{outcome: string, reason: string, references: list<string>, client: bool}
     */
    private function assessUse(string $use, array $conditions, string $ownership, string $products, string $clientUse): array
    {
        if (in_array($use, ['distribution', 'kits', 'oem'], true)) {
            return $this->result('custom', 'distribution', ['1.5', '1.12', '3.2–3.5', '5.5']);
        }
        if ($use === 'bespoke_client') {
            if ($clientUse === 'distribution') {
                return $this->result('custom', 'client_distribution', ['5.5', '5.6(c)'], true);
            }
            if (! in_array('direct_client', $conditions, true) || ! in_array($clientUse, ['internal', 'hosted'], true)) {
                return $this->result('review', 'direct_client', ['5.6'], true);
            }
            if ($clientUse === 'hosted') {
                return $this->assessHosted($conditions, $ownership, $products, true);
            }
            if (! in_array('client_free_scope', $conditions, true)) {
                return $this->result('review', 'client_free_scope', ['1.7(a)', '5.6(c)'], true);
            }

            return $this->result('free', 'client_free', ['1.7(a)', '2', '3', '5.6'], true);
        }
        if ($use === 'hosted') {
            return $this->assessHosted($conditions, $ownership, $products);
        }
        [$condition, $reason, $reference] = match ($use) {
            'evaluation' => ['evaluation_private', 'evaluation', '1.7(c)'],
            'bespoke_runtime' => ['bespoke_runtime_scope', 'bespoke_runtime', '1.7(b)'],
            default => ['free_scope', 'free_scope', '1.7(a)'],
        };
        if (! in_array($condition, $conditions, true)) {
            return $this->result('review', $reason, [$reference]);
        }

        return $this->result('free', 'permitted_free', [$reference, '2', '3']);
    }

    /** @param list<string> $conditions
     * @return array{outcome: string, reason: string, references: list<string>, client: bool}
     */
    private function assessHosted(array $conditions, string $ownership, string $products, bool $client = false): array
    {
        if (! in_array('hosted_scope', $conditions, true)) {
            return $this->result('review', 'hosted_scope', ['1.9', '1.11', '5.1'], $client);
        }

        return match ($ownership) {
            'group' => $this->result('enterprise', 'group', ['1.14', '5.4'], $client),
            'other' => $this->result('separate', 'other_entities', ['1.2', '5.3–5.4'], $client),
            'one' => match ($products) {
                'one' => $this->result('single', 'one_product', ['1.10', '5.2'], $client),
                'two' => $this->result('two_single', 'two_products', ['5.2–5.3'], $client),
                'portfolio' => $this->result('unlimited', 'portfolio', ['5.3'], $client),
                default => $this->result('incomplete', 'choose_products', [], $client),
            },
            default => $this->result('incomplete', 'choose_ownership', [], $client),
        };
    }

    /**
     * @param  list<string>  $references
     * @return array{outcome: string, reason: string, references: list<string>, client: bool}
     */
    private function result(string $outcome, string $reason, array $references, bool $client = false): array
    {
        return compact('outcome', 'reason', 'references', 'client');
    }
}
