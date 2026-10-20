<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';

use Bjanczak\FilamentFlexFields\Enums\FieldType;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilySchemaDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilyValueSnapshotCodec;

$fixture = json_decode(file_get_contents(dirname(__DIR__).'/core/contracts/storage-protocol-v1.json'), true, 64, JSON_THROW_ON_ERROR);
if (($fixture['format'] ?? null) !== 1) {
    throw new RuntimeException('Unsupported reviewed storage protocol fixture.');
}
$types = [];
foreach (FieldType::cases() as $type) {
    $types[$type->name] = $type->value;
}
foreach ($fixture['fieldTypes'] as $name => $value) {
    if (($types[$name] ?? null) !== $value) {
        throw new RuntimeException('A persisted built-in field identity changed: '.$name);
    }
}
if ($fixture['familyDefinitionKeys'] !== FamilySchemaDefinition::DATA_KEYS) {
    throw new RuntimeException('Family schema keys changed; review compatibility and provide explicit migrations.');
}
foreach ($fixture['snapshots'] as $name => $json) {
    $snapshot = FamilyValueSnapshotCodec::decode($json);
    if (FamilyValueSnapshotCodec::encode($snapshot) !== $json) {
        throw new RuntimeException('Stored schema/value round trip changed: '.$name);
    }
    if (FamilySchemaDefinition::fromArray($snapshot->definition->export())->export() !== $snapshot->definition->export()) {
        throw new RuntimeException('Stored definition round trip changed: '.$name);
    }
}
echo json_encode(['passed' => true, 'storedFieldIdentities' => count($fixture['fieldTypes']),
    'goldenSnapshots' => count($fixture['snapshots']),
    'scope' => 'Frozen persisted field identities, family definition keys and exact typed snapshot round trips. Installed-family migrations and dist upgrades are exercised by core-family-schema and core-author-tooling.'], JSON_THROW_ON_ERROR).PHP_EOL;
