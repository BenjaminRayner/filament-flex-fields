<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Enterprise\FieldRbacMatrix;
use Bjanczak\FilamentFlexFields\Support\Enterprise\SchemaRegistry;
use Bjanczak\FilamentFlexFields\Support\Enterprise\TenantFieldPacks;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureOs;

beforeEach(function (): void {
    TenantFieldPacks::clear();
    FieldRbacMatrix::reset();
    SchemaRegistry::clear();
    MediaCaptureOs::registerVirusScanCallback(null);
});

it('isolates tenant field packs per tenant id', function (): void {
    TenantFieldPacks::registerPack('tenant-a', ['select', 'phone']);
    TenantFieldPacks::registerPack('tenant-b', ['rating', 'signature']);

    expect(TenantFieldPacks::packFor('tenant-a'))->toBe(['select', 'phone'])
        ->and(TenantFieldPacks::packFor('tenant-b'))->toBe(['rating', 'signature'])
        ->and(TenantFieldPacks::packFor('tenant-c'))->toBe([]);
});

it('denies field abilities when rbac matrix entries are set', function (): void {
    expect(FieldRbacMatrix::can('editor', FieldRbacMatrix::ABILITY_EDIT, 'select'))->toBeTrue();

    FieldRbacMatrix::deny('editor', FieldRbacMatrix::ABILITY_EDIT, 'select');

    expect(FieldRbacMatrix::can('editor', FieldRbacMatrix::ABILITY_EDIT, 'select'))->toBeFalse()
        ->and(FieldRbacMatrix::can('editor', FieldRbacMatrix::ABILITY_VIEW, 'select'))->toBeTrue();
});

it('rolls back schema registry to a prior version as a new live publish', function (): void {
    (require __DIR__.'/../../database/migrations/2026_08_27_000100_create_flex_field_schema_versions_table.php')->up();
    (require __DIR__.'/../../database/migrations/2026_10_02_000000_create_flex_field_schema_heads_table.php')->up();
    $schemaV1 = [
        'target' => 'App\\Models\\Lead',
        'fields' => [
            ['slug' => 'email', 'label' => 'Email', 'type' => 'single_line_text'],
        ],
    ];

    $schemaV2 = [
        'target' => 'App\\Models\\Lead',
        'fields' => [
            ['slug' => 'email', 'label' => 'Email', 'type' => 'single_line_text'],
            ['slug' => 'phone', 'label' => 'Phone', 'type' => 'phone'],
        ],
    ];

    $v1 = SchemaRegistry::publish('crm-lead', $schemaV1, 'admin@example.com');
    SchemaRegistry::publish('crm-lead', $schemaV2, 'admin@example.com', SchemaRegistry::STATE_LIVE);

    $rolledBack = SchemaRegistry::rollback('crm-lead', $v1);
    $versions = SchemaRegistry::versions('crm-lead');

    expect($v1)->toBe(1)
        ->and($rolledBack['version'])->toBe(3)
        ->and($rolledBack['state'])->toBe(SchemaRegistry::STATE_LIVE)
        ->and($rolledBack['schema'])->toBe($schemaV1)
        ->and($rolledBack['checksum'])->toBe(SchemaRegistry::checksum($schemaV1))
        ->and(count($versions))->toBe(3);
});
