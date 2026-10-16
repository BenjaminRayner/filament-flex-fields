<?php

declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
use Bjanczak\FilamentFlexFields\Support\Icons\Picker\IconSelectPage;

$c = IconSelectPage::context('search', 'hugeicons', 64);
if (IconSelectPage::resolve(null, $c) !== 1) {
    throw new RuntimeException('Initial page');
}
if (IconSelectPage::resolve('2:'.$c, $c) !== 2) {
    throw new RuntimeException('Continuation');
}
foreach (['1:'.$c, '501:'.$c, '2:'.str_repeat('a', 64), 'x', str_repeat('x', 513)] as $bad) {
    try {
        IconSelectPage::resolve($bad, $c);
        throw new RuntimeException('Accepted invalid cursor');
    } catch (InvalidArgumentException) {
    }
}
$last = IconSelectPage::publish([], 500, true, $c);
if ($last['hasMore'] || $last['cursor'] !== null) {
    throw new RuntimeException('Page ceiling');
}
try {
    IconSelectPage::publish([['value' => 'x', 'label' => 'x', 'preview' => str_repeat('x', 1048576)]], 1, false, $c);
    throw new RuntimeException('Accepted oversized page');
} catch (InvalidArgumentException) {
}
echo "PASS: icon query/set/page-size continuation ownership, page and byte ceilings.\n";
