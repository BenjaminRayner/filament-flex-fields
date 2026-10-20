import { createHash } from 'node:crypto'
import { readdir, readFile, writeFile, mkdir } from 'node:fs/promises'
import { dirname, relative, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { reportDestination } from './acceptance-contract.mjs'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..')

// This is an inventory contract, not a per-file verification certificate.
// Rules deliberately distinguish domain adapters from the shared core protocol.
const modules = [
    { id: 'component-registration', paths: ['src/Support/Components'], decision: 'replace-core', stage: 'E1' },
    { id: 'builtin-definition-contract', paths: ['resources/components'], decision: 'preserve-semantics-adapt-protocol', stage: 'E1-E4' },
    { id: 'asset-plan-publication', paths: ['src/Support/Assets', 'src/Assets'], decision: 'restructure', stage: 'E1-E2-E5' },
    { id: 'bootstrap-integration', paths: ['src/Support/Bootstrap'], decision: 'restructure', stage: 'E1-E4' },
    { id: 'schema-value-pipeline', paths: ['src/Support/Schema', 'src/Support/Values', 'src/Support/FormBuilder', 'src/Data', 'src/Models', 'src/Policies', 'src/Filament/Schema'], decision: 'preserve-value-pipeline-replace-extension-dispatch', stage: 'E0-E4' },
    { id: 'console-integration', paths: ['src/Console'], decision: 'preserve-core-diagnostics-explicit-domain-maintenance', stage: 'E0-E2-E4' },
    { id: 'schema-conditions', paths: ['src/Support/Schema/Conditions'], decision: 'preserve-subject-to-contract', stage: 'E0-E4' },
    { id: 'application-activity-events', paths: ['src/Support/Events'], decision: 'native-laravel-events-scoped-failure-boundary', stage: 'E0-E4' },
    { id: 'server-lifetime', paths: ['src/Support/Runtime'], decision: 'preserve-scoped-boundary', stage: 'E0-E5' },
    { id: 'theme-locale-icons', paths: ['src/Support/Theme', 'src/Support/Locale', 'src/Support/Icons'], decision: 'restructure-public-contract', stage: 'E4' },
    { id: 'enterprise-policy-hooks', paths: ['src/Support/Enterprise', 'src/Support/Intelligence', 'src/Support/Composition'], decision: 'preserve-scoped-policy-boundaries-bound-observability', stage: 'E0-E4' },
    { id: 'server-shared-security', paths: ['src/Support/Http', 'src/Support/Security'], decision: 'preserve-subject-to-contract', stage: 'E0-E6' },
    { id: 'browser-ownership', paths: ['resources/js/runtime'], decision: 'restructure', stage: 'E3-E5' },
    { id: 'shared-overlay', paths: ['resources/js/overlay'], decision: 'preserve-engine-replace-owner-lifetime-and-fault-boundaries', stage: 'E3-E4' },
    { id: 'browser-core-adapters', paths: ['resources/js/core', 'resources/js/entries', 'resources/js/sdk'], decision: 'public-sdk-extract-domain-adapters-from-minimal-core', stage: 'E0-E4' },
    { id: 'core-css', paths: ['resources/css/core', 'resources/css/core.css'], decision: 'preserve-tokens-restructure-protocol', stage: 'E3-E4' },
    { id: 'build-contract', paths: ['scripts/assets'], decision: 'restructure', stage: 'E2-E5' },
    { id: 'core-verification-tools', paths: ['scripts/core'], decision: 'add-repeatable-evidence', stage: 'E0-E6' },
    { id: 'shared-renderer-contract', paths: ['resources/views/components/text-input', 'resources/views/components/runtime'], decision: 'preserve-native-renderers-with-independent-asset-boundaries', stage: 'E0-E4' },
]
const decisions = {
    'schema-value-pipeline': { reason: 'Native hydration, model storage and condition semantics remain compatible. Public family codec/configurator/validator/submission and SQL heads replace closed extension boundaries; business authorization remains mandatory in the calling application.', evidence: ['scripts/compatibility/core-data-boundaries.php', 'scripts/compatibility/core-child-configuration.php', 'scripts/compatibility/core-author-tooling.php'] },
    'console-integration': { reason: 'Keep publication, doctor/explain, release retention, generators and explicit domain pruning. Retired upgrade-v3 commands are removed; installing a package does not silently migrate customer values or prune releases.', evidence: ['scripts/compatibility/core-bootstrap-contract.php', 'scripts/compatibility/core-release-lifecycle.php', 'scripts/compatibility/core-author-tooling.php'] },
    'enterprise-policy-hooks': { reason: 'Retain application policy and optional integrations. Monitoring now uses typed native Laravel events; FLEX retains no custom listeners or sinks. Scope the failure/reentrancy boundary and reject invalid boot callbacks before retention. Preserve fail-closed distributed locks and isolate optional cache outages.', evidence: ['scripts/compatibility/core-cache-lifetimes.php', 'scripts/compatibility/core-circuit-concurrency.php', 'scripts/compatibility/core-data-boundaries.php'] },
    'server-shared-security': { reason: 'Preserve URL/SSRF and HTML sanitization helpers as domain safety boundaries. Asset policy validates artifacts and origins; neither trusted PHP/JS extensions nor application business authorization are sandboxed by FLEX.', evidence: ['scripts/compatibility/core-delivery-policy.php', 'scripts/compatibility/core-data-boundaries.php'] },
    'shared-overlay': { reason: 'Keep geometry and existing presentation. One document interaction/session service, owner connectivity and scroll leases replace global sweeping; partial initialization rolls back, peers survive geometry faults, mode switches transfer listeners. Asset readiness is independent of visual motion.', evidence: ['scripts/compatibility/core-overlay-interactions.mjs', 'scripts/compatibility/overlay-retarget.mjs'] },
    'browser-core-adapters': { reason: 'Compiled SDK is the single shared owner. Native Filament knowledge stays in the adapter graph; immutable module cache is not unloadable. Controlled and installed-browser probes cover actual shared factories, local reconciliation and transport.', evidence: ['scripts/compatibility/core-runtime-adapters.mjs', 'scripts/compatibility/core-sdk-externalization.mjs', 'scripts/compatibility/core-author-tooling-browser.mjs'] },
    'shared-renderer-contract': { reason: 'Reuse real Flex renderers and native Filament lifecycle with distinct child identity/configuration; do not clone child HTML templates or reimplement native relation semantics in a generic asset manager.', evidence: ['scripts/compatibility/core-child-configuration.php', 'scripts/compatibility/core-author-tooling.php', 'scripts/compatibility/core-author-tooling-browser.mjs'] },
}
for (const module of modules) if (decisions[module.id]) Object.assign(module, decisions[module.id])
const exact = {
    'package.json': 'build-contract',
    'composer.json': 'bootstrap-integration',
    'src/FilamentFlexFieldsServiceProvider.php': 'bootstrap-integration',
    'src/FilamentFlexFieldsPlugin.php': 'bootstrap-integration',
    'config/filament-flex-fields.php': 'bootstrap-integration',
    'src/Facades/FlexFields.php': 'server-lifetime',
    'src/Concerns/HasFlexFields.php': 'schema-value-pipeline',
    'src/Filament/Concerns/EmitsFlexDisplayAssets.php': 'bootstrap-integration',
    'src/Support/Translations.php': 'theme-locale-icons',
    'src/Support/FlexIcon.php': 'theme-locale-icons',
    'src/Support/GravityIcon.php': 'theme-locale-icons',
    'src/Support/HtmlSanitizer.php': 'server-shared-security',
    'src/Support/Catalog/FlexCatalog.php': 'component-registration',
    'src/Support/Catalog/ComponentPresentation.php': 'component-registration',
    'src/Concerns/HasControlSize.php': 'theme-locale-icons',
    'src/Concerns/HasFieldRounding.php': 'theme-locale-icons',
    'src/Concerns/HasFieldFocusOutline.php': 'theme-locale-icons',
    'src/Concerns/ResolvesConfiguredIcons.php': 'theme-locale-icons',
    'src/Support/FlexFieldsConfig.php': 'bootstrap-integration',
    'src/Support/FlexFieldAssets.php': 'asset-plan-publication',
    'src/Support/FlexFieldAssetQueue.php': 'asset-plan-publication',
    'src/Support/FlexFieldStylesheetQueue.php': 'asset-plan-publication',
    'src/Support/FlexFieldAlpineQueue.php': 'asset-plan-publication',
    'src/Support/FlexFieldFormBuilder.php': 'schema-value-pipeline',
    'src/Support/FlexFieldSchemaRegistry.php': 'schema-value-pipeline',
    'src/Support/FlexFieldSchemaMigrator.php': 'schema-value-pipeline',
    'src/Support/FieldTypeRegistry.php': 'schema-value-pipeline',
    'src/Enums/FieldType.php': 'schema-value-pipeline',
    'resources/views/components/assets.blade.php': 'asset-plan-publication',
    'resources/views/components/schema.blade.php': 'bootstrap-integration',
    'resources/views/components/select-control.blade.php': 'shared-renderer-contract',
    'resources/views/components/select-menu.blade.php': 'shared-renderer-contract',
    'resources/views/components/segment-control.blade.php': 'shared-renderer-contract',
    'resources/views/components/segment-overflow-shell.blade.php': 'shared-renderer-contract',
    'resources/views/components/selection-indicator.blade.php': 'shared-renderer-contract',
    'resources/views/components/lazy-alpine-mount.blade.php': 'shared-renderer-contract',
    'resources/views/partials/emit-assets.blade.php': 'asset-plan-publication',
    'resources/views/partials/load-stylesheet.blade.php': 'asset-plan-publication',
    'resources/views/partials/flex-field-asset-injector.blade.php': 'bootstrap-integration',
    'resources/views/partials/flex-fff-load-bootstrap.blade.php': 'bootstrap-integration',
    'resources/views/partials/critical-stylesheets.blade.php': 'asset-plan-publication',
}
// Component cache facades are domain adapters, but their lifetime is part of the
// core scope contract. Record them without changing their presentation logic.
for (const name of ['StatusChipColumnRenderCache', 'RatingColumnRenderCache', 'MapPinColumnRenderCache',
    'ProgressColumnRenderCache', 'SignaturePreviewColumnRenderCache', 'IconColumnRenderCache',
    'UserColumnRenderCache', 'UserColumnSharedStackCache', 'UserColumnStackState']) {
    exact[`src/Support/${name}.php`] = 'server-lifetime'
}
const paths = []
async function walk(directory) {
    for (const item of await readdir(directory, { withFileTypes: true })) {
        if (['node_modules', 'vendor', '.git', 'dist', 'build'].includes(item.name)) continue
        const path = resolve(directory, item.name)
        if (item.isDirectory()) await walk(path)
        else if (item.isFile()) paths.push(relative(root, path))
    }
}
await walk(resolve(root, 'src'))
await walk(resolve(root, 'resources/components'))
await walk(resolve(root, 'resources/js'))
await walk(resolve(root, 'resources/css'))
await walk(resolve(root, 'resources/views'))
await walk(resolve(root, 'scripts'))
paths.push('config/filament-flex-fields.php', 'package.json', 'composer.json')

const records = []
for (const path of [...new Set(paths)].sort()) {
    let module = exact[path] ? modules.find(item => item.id === exact[path]) : null
    // Longest prefix owns specialized children (for example Conditions).
    module ??= modules.filter(item => item.paths.some(prefix => path === prefix || path.startsWith(`${prefix}/`)))
        .sort((a, b) => Math.max(...b.paths.map(p => p.length)) - Math.max(...a.paths.map(p => p.length)))[0]
    if (!module && /^scripts\/(build-.+|generate-css-entries|bundle-metrics|check-bundle-budgets|export-asset-registry)\.(mjs|php)$/.test(path)) {
        module = modules.find(item => item.id === 'build-contract')
    }
    if (!module) continue
    const contents = await readFile(resolve(root, path))
    records.push({ path, module: module.id, decision: module.decision, stage: module.stage,
        verification: 'inventory-only', sha256: createHash('sha256').update(contents).digest('hex') })
}
for (const path of Object.keys(exact)) {
    if (!records.some(item => item.path === path)) throw new Error(`Missing inventory source: ${path}`)
}
// This documents the actual registration source, rather than a stale hand list.
// New process bindings require an explicit retention decision; scope defaults
// must never quietly turn into worker singletons for a higher cache hit rate.
const processPolicies = {
    ComponentRegistry: 'boot collection then frozen compiled installation metadata',
    ComponentRegistrationCollector: 'boot-only candidates and prepared registration metadata',
    ComponentRegistrar: 'boot collection coordinator; mutation rejected after completion',
    ComponentRouteRegistrar: 'boot-only route registration latch; does not retain router, application, requests or route plans',
    CategoryRegistry: 'immutable official taxonomy; localized strings resolved at use',
    FlexCatalog: 'bounded boot-only plugin collection; one locale/revision presentation snapshot',
    HtmlSanitizer: 'fixed sanitization rules; no input or tenant results retained',
    IconCatalogResolver: 'stateless reader; optional cache failure state resolved in current scope',
    IconSvgCache: 'stateless reader; no SVG values or current request retained on the service',
    FlexFieldEntityDiscovery: 'stateless discovery; panel and locale consulted on each operation',
    FlexFieldGroupValidator: 'stateless validator; definitions and values passed per operation',
    FieldTypeHandlerRegistry: 'fixed handler collection; configured field instances returned, not retained',
}
const serviceBindings = []
const seenBindings = new Set()
for (const record of records.filter(item => /^src\/Support\/Bootstrap\/Bindings\/[^/]+\.php$/.test(item.path))) {
    const source = await readFile(resolve(root, record.path), 'utf8')
    const imports = new Map([...source.matchAll(/^use ([A-Za-z0-9_\\]+);$/gm)]
        .map(match => [match[1].split('\\').at(-1), match[1]]))
    const calls = [...source.matchAll(/\$app->(singleton|scoped)\(\s*([A-Za-z0-9_]+)::class\b/g)]
    if (calls.length !== [...source.matchAll(/\$app->(?:singleton|scoped)\(/g)].length) {
        throw new Error(`Unsupported lifetime declaration in ${record.path}; extend the inventory contract explicitly.`)
    }
    for (const call of calls) {
        const service = imports.get(call[2])
        if (!service || seenBindings.has(service)) throw new Error(`Missing or duplicate binding identity: ${record.path} ${call[2]}`)
        if (call[1] === 'singleton' && !Object.hasOwn(processPolicies, call[2])) {
            throw new Error(`A process service needs an explicit retention policy: ${service}`)
        }
        seenBindings.add(service)
        serviceBindings.push({ service, lifetime: call[1] === 'singleton' ? 'process' : 'request-or-job',
            registration: record.path,
            retention: call[1] === 'singleton' ? processPolicies[call[2]] : 'Laravel scoped reset; standalone adapters use the current bounded RequestState scope' })
    }
}
serviceBindings.sort((left, right) => left.service.localeCompare(right.service, 'en'))
const output = `${JSON.stringify({ format: 1, scope: 'core-and-shared-boundaries',
    excludes: ['individual component implementations', 'catalog demos', 'generated assets', 'tests'],
    modules, serviceBindings, files: records }, null, 2)}\n`
const { destination, check } = reportDestination(root, 'core-architecture-inventory.json')
if (check) {
    if (await readFile(destination, 'utf8') !== output) throw new Error('Core inventory is stale. Regenerate it after source changes.')
} else {
    await mkdir(dirname(destination), { recursive: true })
    await writeFile(destination, output)
}
console.log(JSON.stringify({ files: records.length, modules: modules.length, serviceBindings: serviceBindings.length, verification: 'inventory-only' }))
