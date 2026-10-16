<?php

declare(strict_types=1);

require __DIR__.'/location-v4-contract.php';

use Bjanczak\FilamentFlexFields\Support\Enterprise\Schema\DatabaseSchemaVersionStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$app['config']->set('database.connections.flex_probe', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
$app['config']->set('database.default', 'flex_probe');
DB::purge('flex_probe');
$migrations = [];
foreach (glob('database/migrations/*.php') as $file) {
    $migration = require $file;
    $migration->up();
    $migrations[] = $migration;
}
$groups = array_column(Schema::getIndexes('flex_field_groups'), 'name');
$versions = array_column(Schema::getIndexes('flex_field_schema_versions'), 'name');
locationCheck(in_array('fff_groups_target_order', $groups, true) && in_array('fff_groups_tenant_order', $groups, true), 'Lookup index absent');
locationCheck(in_array('fff_versions_schema_state', $versions, true), 'Version-state lookup index absent');
$store = new DatabaseSchemaVersionStore('flex_probe');
$first = $store->publish('tenant-a:example', ['fields' => []], hash('sha256', 'one'), 'probe', 'live', null);
$second = $store->publish('tenant-a:example', ['fields' => [['name' => 'second']]], hash('sha256', 'two'), 'probe', 'live', null);
locationCheck($first === 1 && $second === 2 && $store->find('tenant-a:example', 1)['state'] === 'approved', 'Activation/version isolation failed');
$other = $store->publish('tenant-b:example', ['fields' => []], hash('sha256', 'other'), null, 'draft', null);
locationCheck($other === 1, 'Tenant schema version counters collided');
$store->purge('tenant-a:example');
$third = $store->publish('tenant-a:example', ['fields' => []], hash('sha256', 'three'), null, 'draft', null);
locationCheck($third === 3 && count($store->versions('tenant-b:example')) === 1, 'History purge reused IDs or crossed schemas');
array_pop($migrations)->down();
locationCheck(! in_array('fff_groups_target_order', array_column(Schema::getIndexes('flex_field_groups'), 'name'), true), 'Lookup index rollback failed');
locationCheck(count($store->versions('tenant-a:example')) === 1, 'Index rollback removed schema data');
foreach (array_reverse($migrations) as $migration) {
    $migration->down();
}
locationCheck(! Schema::hasTable('flex_field_groups') && ! Schema::hasTable('flex_field_schema_versions'), 'Full fresh migration rollback failed');
echo 'PASS: all five migrations up/down in isolated SQLite memory, lookup indexes, monotonic versions, one active version and schema isolation. Host database untouched.'.PHP_EOL;
