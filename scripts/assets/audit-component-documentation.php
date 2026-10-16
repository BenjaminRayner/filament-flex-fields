<?php

declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';

use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;

$root = dirname(__DIR__, 2);
$rows = [];
foreach (ComponentRegistry::builtIn()->all() as $id => $definition) {
    $catalog = $definition->catalog;
    if ($catalog === null) {
        continue;
    }
    $documentation = $catalog->documentation ?? $id.'.md';
    if (! is_file($root.'/docs/'.$documentation)) {
        throw new RuntimeException('Missing catalog documentation: '.$id);
    }
    foreach ($catalog->examples as $example) {
        if (! is_file($example->path)) {
            throw new RuntimeException('Missing catalog PHP example: '.$id.' -> '.$example->path);
        }
    }
    if ($catalog->demoClass !== null && ! class_exists($catalog->demoClass)) {
        throw new RuntimeException('Missing demo class: '.$id);
    }
    $rows[] = ['id' => $id, 'kind' => $definition->kind->value,
        'documentation' => $documentation, 'examples' => count($catalog->examples)];
}
echo json_encode(['passed' => true, 'components' => count($rows), 'rows' => $rows], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
