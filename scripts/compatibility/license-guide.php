<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Licensing\LicenseUseAssessment;

require dirname(__DIR__, 2).'/src/Support/Licensing/LicenseUseAssessment.php';

$guide = new LicenseUseAssessment;
$all = LicenseUseAssessment::CONDITIONS;
$cases = [
    'empty' => [[], [], 'incomplete'],
    'internal unconfirmed' => [['internal'], [], 'review'],
    'internal qualifies' => [['internal'], ['free_scope'], 'free'],
    'personal qualifies' => [['personal'], ['free_scope'], 'free'],
    'evaluation unconfirmed' => [['evaluation'], [], 'review'],
    'private pre-production evaluation' => [['evaluation'], ['evaluation_private'], 'free'],
    'bespoke runtime unconfirmed' => [['bespoke_runtime'], [], 'review'],
    'documented internal runtime engagement' => [['bespoke_runtime'], ['bespoke_runtime_scope'], 'free'],
    'hosted unconfirmed' => [['hosted'], [], 'review'],
    'one hosted product' => [['hosted'], ['hosted_scope'], 'single'],
    'two hosted products' => [['hosted'], ['hosted_scope'], 'two_single', 'one', 'two'],
    'hosted portfolio' => [['hosted'], ['hosted_scope'], 'unlimited', 'one', 'portfolio'],
    'qualifying group' => [['hosted'], ['hosted_scope'], 'enterprise', 'group'],
    'unrelated or partially owned entities' => [['hosted'], ['hosted_scope'], 'separate', 'other'],
    'invalid ownership' => [['hosted'], ['hosted_scope'], 'incomplete', 'invalid'],
    'invalid product count' => [['hosted'], ['hosted_scope'], 'incomplete', 'one', 'invalid'],
    'internal and hosted are independently covered' => [['internal', 'hosted'], $all, 'mixed'],
    'unconfirmed internal not ignored by SaaS' => [['internal', 'hosted'], ['hosted_scope'], 'mixed'],
    'private evaluation plus live SaaS' => [['evaluation', 'hosted'], $all, 'mixed'],
    'unconfirmed evaluation not ignored by SaaS' => [['evaluation', 'hosted'], ['hosted_scope'], 'mixed'],
    'private research and staff tool' => [['personal', 'internal'], ['free_scope'], 'free'],
    'direct client missing use' => [['bespoke_client'], ['direct_client'], 'review'],
    'direct client missing exception' => [['bespoke_client'], [], 'review', 'one', 'one', 'internal'],
    'client internal missing own entitlement' => [['bespoke_client'], ['direct_client'], 'review', 'one', 'one', 'internal'],
    'direct client internal' => [['bespoke_client'], ['direct_client', 'client_free_scope'], 'free', 'one', 'one', 'internal'],
    'direct client SaaS' => [['bespoke_client'], ['direct_client', 'hosted_scope'], 'single', 'one', 'one', 'hosted'],
    'client independent product count' => [['bespoke_client'], $all, 'two_single', 'one', 'portfolio', 'hosted', 'one', 'two'],
    'client portfolio' => [['bespoke_client'], $all, 'unlimited', 'one', 'one', 'hosted', 'one', 'portfolio'],
    'client qualifying group' => [['bespoke_client'], $all, 'enterprise', 'one', 'one', 'hosted', 'group'],
    'client unrelated entities' => [['bespoke_client'], $all, 'separate', 'one', 'one', 'hosted', 'other'],
    'client distribution is not an agency entitlement' => [['bespoke_client'], [], 'custom', 'one', 'one', 'distribution'],
    'agency internal and client distribution' => [['internal', 'bespoke_client'], ['free_scope'], 'mixed', 'one', 'one', 'distribution'],
    'agency and client cannot pool same Single' => [['hosted', 'bespoke_client'], $all, 'separate', 'one', 'one', 'hosted'],
    'different agency and client portfolio' => [['hosted', 'bespoke_client'], $all, 'mixed', 'one', 'one', 'hosted', 'one', 'portfolio'],
    'agency SaaS and client internal' => [['hosted', 'bespoke_client'], $all, 'mixed', 'one', 'one', 'internal'],
    'agency distribution and client own internal route' => [['distribution', 'bespoke_client'], $all, 'mixed', 'one', 'one', 'internal'],
    'unknown use' => [['unknown'], $all, 'incomplete'],
];
foreach (['distribution', 'kits', 'oem'] as $use) {
    foreach (['one', 'group', 'other'] as $owner) {
        $cases[$use.' under '.$owner] = [[$use], $all, 'custom', $owner];
        $cases[$use.' plus internal under '.$owner] = [[$use, 'internal'], $all, 'mixed', $owner];
    }
}
$translations = [];
foreach (['en', 'pl'] as $locale) {
    $translations[$locale] = require dirname(__DIR__, 2).'/resources/lang/'.$locale.'/licensing.php';
}
foreach ($cases as $name => $case) {
    [$uses, $conditions, $expected] = $case;
    $result = $guide->assess($uses, $conditions, $case[3] ?? 'one', $case[4] ?? 'one', $case[5] ?? '', $case[6] ?? 'one', $case[7] ?? 'one');
    if ($result['outcome'] !== $expected) {
        throw new RuntimeException(json_encode(compact('name', 'case', 'result'), JSON_THROW_ON_ERROR));
    }
    foreach ([$result, ...$result['findings']] as $finding) {
        foreach ($translations as $language) {
            foreach (['title', 'detail'] as $part) {
                if (! is_string($language['outcomes'][$finding['outcome']][$part] ?? null)) {
                    throw new RuntimeException('Missing result translation');
                }
            }
            if (! is_string($language['reasons'][$finding['reason']] ?? null)) {
                throw new RuntimeException('Missing reason translation');
            }
        }
    }
}
// Mixed-use invariants: no selected activity is lost, independent requirements
// survive ordering, and each explanation agrees with that activity on its own.
$combinations = 0;
foreach (LicenseUseAssessment::USES as $a) {
    foreach (LicenseUseAssessment::USES as $b) {
        if ($a >= $b) {
            continue;
        }
        for ($mask = 0; $mask < 64; $mask++) {
            $conditions = array_values(array_filter($all, static fn ($key, $index): bool => (bool) ($mask & (1 << $index)), ARRAY_FILTER_USE_BOTH));
            foreach (['', 'internal', 'hosted', 'distribution'] as $clientUse) {
                foreach (['one', 'group', 'other'] as $owner) {
                    $result = $guide->assess([$a, $b], $conditions, $owner, 'portfolio', $clientUse, 'one', 'two');
                    $reverse = $guide->assess([$b, $a], $conditions, $owner, 'portfolio', $clientUse, 'one', 'two');
                    if ($result !== $reverse || count($result['findings']) !== 2) {
                        throw new RuntimeException('Ordering changed coverage or lost a selected use');
                    }
                    foreach ($result['findings'] as $finding) {
                        $single = $guide->assess([$finding['use']], $conditions, $owner, 'portfolio', $clientUse, 'one', 'two');
                        if ($finding !== $single['findings'][0]) {
                            throw new RuntimeException('A mixed selection changed the independent entitlement');
                        }
                    }
                    $combinations++;
                }
            }
        }
    }
}
foreach (['one', 'group', 'other'] as $owner) {
    foreach (['one', 'two', 'portfolio'] as $products) {
        if ($guide->assess(['internal'], ['free_scope'], $owner, $products)['outcome'] !== 'free') {
            throw new RuntimeException('Hidden commercial options changed a free use');
        }
    }
}
if ($guide->conditionsFor(['hosted', 'internal', 'distribution']) !== ['free_scope', 'hosted_scope']) {
    throw new RuntimeException('Custom hid conditions required by unrelated selected uses');
}
foreach (['single' => '249', 'two_single' => '498', 'unlimited' => '699', 'enterprise' => '1,499', 'custom' => '2,999'] as $outcome => $price) {
    foreach ($translations as $locale => $language) {
        $expected = $locale === 'pl' ? str_replace(',', '', $price) : $price;
        if (! str_contains($language['outcomes'][$outcome]['title'].' '.$language['outcomes'][$outcome]['detail'], $expected)) {
            throw new RuntimeException('Incorrect displayed price for '.$outcome);
        }
    }
}
$license = file_get_contents(dirname(__DIR__, 2).'/LICENSE');
foreach (['USD 249', 'USD 699', 'USD 1,499', 'USD 2,999', 'VERSION 2.0'] as $source) {
    if (! str_contains($license, $source)) {
        throw new RuntimeException('License source changed; reassess guide rules and prices');
    }
}
echo 'PASS: '.count($cases).' source-based scenarios, '.$combinations.' mixed-use combinations, EN/PL results/prices, independent client coverage and no pooled distribution rights.'.PHP_EOL;
