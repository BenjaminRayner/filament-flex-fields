import assert from 'node:assert/strict'
import { createHash } from 'node:crypto'

export const startupCases = ['native', 'enabled-empty', 'one', 'twenty', 'hundred']
export const startupFields = { native: 0, 'enabled-empty': 0, one: 1, twenty: 20, hundred: 100 }

export const startupBudgets = { empty: { resources: 3, decodedBytes: 34 * 1024 }, active: { resources: 22, decodedBytes: 220 * 1024 } }

/** Only the sealed load-gate's two independent fallback transports may exceed the regular entry ceiling. */
export function startupResourcePolicy(manifestBytes, seal) {
    assert.equal(createHash('sha256').update(manifestBytes).digest('hex'), seal.metadata?.['resources/dist/components/alpine-manifest.json'], 'Startup manifest is not bound to the sealed release.')
    assert.match(seal.release, /^[a-f0-9]{64}$/)
    const manifest = JSON.parse(manifestBytes)
    assert.equal(manifest.__format__, 2, 'Unsupported startup chunk manifest.')
    const modules = manifest.__chunk_modules__, imports = manifest.__chunk_imports__, dynamic = manifest.__dynamic_imports__
    assert.ok(modules && imports && dynamic && Array.isArray(manifest.__shared_chunks__) && Array.isArray(manifest.__entries__), 'Incomplete startup chunk manifest.')
    const shared = new Set(manifest.__shared_chunks__)
    const filename = name => typeof name === 'string' && /^[a-zA-Z0-9][a-zA-Z0-9._-]*\.js$/.test(name)
    const dependencies = (graph, name) => {
        const names = graph[name]
        assert.ok(Array.isArray(names) && names.every(target => filename(target) && shared.has(target)) && new Set(names).size === names.length,
            'Invalid startup chunk dependencies for ' + name)
        return names
    }
    const owner = source => {
        const matches = Object.entries(modules).filter(([, names]) => Array.isArray(names) && names.includes(source))
        assert.ok(matches.length === 1 && filename(matches[0][0]) && shared.has(matches[0][0]), 'Startup manifest needs one owner for ' + source)
        return matches[0][0]
    }
    const regular = new Set(['css/core.css', 'css/flex-text-input.css', 'core/flex-fff-load-bootstrap.js', 'core/flex-field-asset-injector.js'])
    const visit = name => {
        assert.ok(filename(name), 'Invalid startup entry name.')
        const asset = 'components/' + name
        if (regular.has(asset)) return
        regular.add(asset)
        for (const target of dependencies(imports, name)) visit(target)
    }
    for (const entry of ['flex-core-runtime', 'flex-text-input']) {
        assert.ok(manifest.__entries__.includes(entry), 'Missing startup entry: ' + entry)
        visit(entry + '.js')
    }
    assert.ok(regular.size <= startupBudgets.active.resources, 'Regular startup graph exceeds its reviewed entry budget.')
    const gate = owner('runtime/alpine/load-gate.js'), boundary = owner('runtime/alpine/boundary-fallback.js')
    const store = owner('runtime/assets/asset-link-store.js')
    const targets = dependencies(dynamic, gate)
    assert.ok(targets.length === 2 && targets.includes(boundary), 'Load-gate fallback transport set changed; review its startup budget.')
    const facade = targets.find(name => name !== boundary)
    assert.ok(Array.isArray(modules[facade]) && modules[facade].length === 0 && dependencies(imports, facade).includes(store),
        'Load-gate asset-store transport must be the sealed re-export facade.')
    for (const target of targets) {
        assert.ok(!regular.has('components/' + target) && dependencies(imports, target).every(name => regular.has('components/' + name)),
            'Fallback transport unexpectedly introduces another startup dependency.')
    }
    const prefix = '/filament-flex-fields-assets/core/' + seal.release + '/'
    for (const asset of [...regular, ...targets.map(name => 'components/' + name)]) {
        assert.match(seal.assets?.[asset] ?? '', /^[a-f0-9]{64}$/, 'Startup resource is absent from the seal: ' + asset)
    }
    return Object.freeze({ regular: Object.freeze([...regular].map(asset => prefix + asset)),
        fallback: Object.freeze(targets.map(name => prefix + 'components/' + name)) })
}

export function assertStartupResourceBudget(resources, active, policy) {
    const flex = resources.filter(resource => resource.flex), seen = new Set(), fallback = []
    const details = () => JSON.stringify(flex.map(({ path, decoded, initiator, hasQuery, hasFragment }) => ({ path, decoded, initiator, hasQuery, hasFragment })))
    for (const resource of flex) {
        assert.ok(resource.local === true && typeof resource.path === 'string' && !seen.has(resource.path), 'Duplicate or nonlocal startup resource: ' + details())
        assert.ok(resource.hasQuery === false && resource.hasFragment === false, 'Startup resource requires a canonical URL without query or fragment: ' + details())
        seen.add(resource.path)
        const optional = active && policy.fallback.includes(resource.path)
        assert.ok(policy.regular.includes(resource.path) || optional, 'Unexpected resource outside the sealed startup graph: ' + details())
        if (optional) fallback.push(resource.path)
        assert.ok(Number.isFinite(resource.decoded) && resource.decoded >= 0, 'Invalid startup decoded-byte observation: ' + details())
    }
    const budget = active ? startupBudgets.active : startupBudgets.empty
    assert.ok(flex.length - fallback.length <= budget.resources, 'Startup exceeded its reviewed regular resource-entry budget: ' + details())
    assert.ok(flex.reduce((sum, resource) => sum + resource.decoded, 0) <= budget.decodedBytes, 'Startup exceeded its reviewed decoded-byte budget: ' + details())
    return { rawResourceEntries: flex.length, regularResourceEntries: flex.length - fallback.length,
        fallbackResourceEntries: fallback.length, fallbackResources: fallback, regularResourceLimit: budget.resources, decodedByteLimit: budget.decodedBytes }
}

export function distribution(values) {
    assert.ok(values.length > 0 && values.every(value => Number.isFinite(value) && value >= 0), 'Invalid cost samples.')
    const sorted = [...values].sort((a, b) => a - b)
    const percentile = proportion => sorted[Math.max(0, Math.ceil(proportion * sorted.length) - 1)]
    return { min: sorted[0], median: sorted.length % 2 ? sorted[(sorted.length - 1) / 2]
        : (sorted[sorted.length / 2 - 1] + sorted[sorted.length / 2]) / 2,
    p95: percentile(0.95), max: sorted.at(-1) }
}

export function summarizeStartup(samples, repetitions) {
    assert.ok(Number.isInteger(repetitions) && repetitions >= 1 && repetitions <= 50)
    assert.equal(samples.length, startupCases.length * 2 * repetitions, 'Incomplete startup profile.')
    const results = {}
    for (const mode of ['fresh-context', 'primed-context']) for (const name of startupCases) {
        const group = samples.filter(sample => sample.case === name && sample.mode === mode)
        assert.equal(group.length, repetitions, 'Missing or repeated startup case.')
        assert.deepEqual(group.map(sample => sample.sample).sort((a, b) => a - b), Array.from({ length: repetitions }, (_, i) => i))
        for (const sample of group) {
            assert.equal(sample.boundaries, startupFields[name], 'Incorrect consumer count.')
            assert.equal(sample.ready, startupFields[name], 'Unready consumers.')
            assert.deepEqual(sample.errors, [], 'Browser startup error.')
            assert.ok(sample.resources.every(resource => resource.local === true), 'External requests invalidate the isolated profile.')
        }
        const cost = sample => sample.resources.filter(resource => resource.flex)
        const sum = (sample, metric) => cost(sample).reduce((total, resource) => total + resource[metric], 0)
        const rendererMetrics = ['scriptMs', 'taskMs', 'layoutMs', 'styleMs', 'heapUsedBytes', 'heapTotalBytes', 'domNodes', 'eventListeners']
        const renderer = group.every(sample => sample.renderer) ? Object.fromEntries(rendererMetrics.map(metric =>
            [metric, distribution(group.map(sample => sample.renderer[metric]))])) : null
        results[mode + '/' + name] = {
            renderer,
            samples: group.length,
            flexResourceEntries: distribution(group.map(sample => cost(sample).length)),
            flexDecodedBytes: distribution(group.map(sample => sum(sample, 'decoded'))),
            flexTransferBytes: distribution(group.map(sample => sum(sample, 'transfer'))),
            domContentLoadedMs: distribution(group.map(sample => sample.domContentLoadedMs)),
            boundaryReadyMs: distribution(group.map(sample => sample.boundaryReadyMs)),
            longTaskMs: distribution(group.map(sample => sample.longTasks.reduce((sum, task) => sum + task.duration, 0))),
        }
    }
    return results
}
