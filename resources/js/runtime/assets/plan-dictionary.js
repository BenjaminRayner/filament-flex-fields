import { isAbsoluteAssetUrl, resolveAssetUrl } from './asset-url.js'
import { factoryIdentity } from '../alpine/factory-contract.js'
import { getRuntimeState } from '../runtime-state.js'

const baseIndependentPlans = new WeakSet()

const ID = /^[a-f0-9]{64}(?![\s\S])/
const COMPONENT = /^[a-z][a-z0-9]*(?:-[a-z0-9]+)*(?![\s\S])/
const record = value => value !== null && typeof value === 'object' && !Array.isArray(value)

/** Validate once at admission; all projections are frozen and CSS order survives. */
export function decodeAssetPlan(raw, baseURI) {
    if (typeof raw !== 'string' || raw.length * 2 > 262144) throw Error('FLEX asset plan exceeds its metadata budget.')
    let value
    try { value = JSON.parse(raw) } catch { throw Error('Invalid FLEX asset plan JSON.') }
    const keys = ['format', 'component', 'stylesheets', 'chunks', 'sizes', 'factoryOwners']
    if (!record(value) || Object.keys(value).length !== keys.length || keys.some(key => !Object.hasOwn(value, key))
        || value.format !== 1 || typeof value.component !== 'string' || value.component.length > 96 || !COMPONENT.test(value.component)) {
        throw Error('Invalid FLEX asset plan format or component identity.')
    }
    const urls = list => {
        if (!Array.isArray(list) || list.length > 512) throw Error('Invalid FLEX asset plan resource list.')
        return Object.freeze([...new Set(list.map(url => {
            const resolved = typeof url === 'string' && url.length <= 4096 && !/[\s\u0000-\u001f\u007f]/.test(url)
                && (/^https?:\/\//.test(url) || /^\/(?!\/)/.test(url)) ? resolveAssetUrl(url, baseURI) : ''
            if (!resolved) throw Error('FLEX asset plans require HTTP(S) resources or same-origin paths.')
            return resolved
        }))])
    }
    const stylesheets = urls(value.stylesheets), chunks = urls(value.chunks)
    if (!record(value.sizes) || !record(value.factoryOwners) || Object.keys(value.factoryOwners).length > 128
        || Object.keys(value.sizes).length > 1024) throw Error('Invalid FLEX asset plan metadata maps.')
    const declared = new Set([...stylesheets, ...chunks]), sizes = Object.create(null), factoryOwners = Object.create(null)
    for (const [url, bytes] of Object.entries(value.sizes)) {
        const resolved = resolveAssetUrl(url, baseURI)
        if (!declared.has(resolved) || !Number.isSafeInteger(bytes) || bytes < 0) throw Error('Invalid FLEX asset plan resource size.')
        if (Object.hasOwn(sizes, resolved) && sizes[resolved] !== bytes) throw Error('Conflicting FLEX resource sizes.')
        sizes[resolved] = bytes
    }
    for (const [url, owner] of Object.entries(value.factoryOwners)) {
        const resolved = resolveAssetUrl(url, baseURI)
        if (!chunks.includes(resolved) || !record(owner) || Object.keys(owner).length !== 4
            || !['package', 'component', 'export', 'abi'].every(key => Object.hasOwn(owner, key))) throw Error('Invalid FLEX asset plan factory owner.')
        const identity = factoryIdentity({ ...owner, module: resolved })
        const next = Object.freeze({ package: identity.package, component: identity.component, export: identity.export, abi: identity.abi })
        if (factoryOwners[resolved] && JSON.stringify(factoryOwners[resolved]) !== JSON.stringify(next)) throw Error('Conflicting FLEX factory owners.')
        factoryOwners[resolved] = next
    }
    const plan = Object.freeze({ componentId: value.component, stylesheets, chunks, sizes: Object.freeze(sizes), factoryOwners: Object.freeze(factoryOwners) })
    // Classify original URL spellings only after complete admission. Resolved
    // projections alone cannot tell whether the source depended on <base>.
    if ([value.stylesheets, value.chunks, Object.keys(value.sizes), Object.keys(value.factoryOwners)]
        .every(urls => urls.every(isAbsoluteAssetUrl))) baseIndependentPlans.add(plan)
    return plan
}

/** Bounded immutable interning. Owners retain projections through weak marker keys,
 * so catalog eviction never invalidates a still-mounted owner's dependencies.
 * IDs are server content hashes, not browser resource-integrity attestations.
 */
export function createPlanDictionary({ limit = 256, byteLimit = 4 * 1024 * 1024 } = {}) {
    if (!Number.isSafeInteger(limit) || limit < 1 || !Number.isSafeInteger(byteLimit) || byteLimit < 1) throw Error('Invalid FLEX plan dictionary budget.')
    const plans = new Map()
    let bytes = 0, markers = new WeakMap(), roots = new WeakMap()
    const admit = (id, raw, baseURI) => {
        if (typeof id !== 'string' || !ID.test(id)) throw Error('Invalid FLEX asset plan identity.')
        const key = JSON.stringify([baseURI, id]), previous = plans.get(key)
        if (previous) {
            if (previous.raw !== raw) throw Error('Conflicting immutable FLEX asset plan definition.')
            plans.delete(key); plans.set(key, previous)
            return previous.plan
        }
        const plan = decodeAssetPlan(raw, baseURI), cost = (raw.length + key.length) * 2
        if (cost <= byteLimit) {
            while (plans.size && (plans.size >= limit || bytes + cost > byteLimit)) {
                const oldest = plans.keys().next().value
                bytes -= plans.get(oldest).cost; plans.delete(oldest)
            }
            plans.set(key, { raw, plan, cost }); bytes += cost
        }
        return plan
    }
    const definitionsFor = root => {
        let definitions = roots.get(root)
        if (definitions) return definitions
        definitions = new Map()
        let cost = 0
        const nodes = [...(root.querySelectorAll?.('[data-fff-plan-definition]') ?? [])]
        if (root.matches?.('[data-fff-plan-definition]')) nodes.unshift(root)
        if (nodes.length > 4096) throw Error('FLEX fragment plan dictionary exceeds its definition budget.')
        for (const node of nodes) {
            const id = node.getAttribute('data-fff-plan-definition'), raw = node.content?.textContent ?? node.textContent
            cost += (raw?.length ?? 0) * 2
            if (cost > 8 * 1024 * 1024 || !ID.test(id ?? '')
                || (definitions.has(id) && definitions.get(id) !== raw)) throw Error('Invalid FLEX fragment plan dictionary.')
            definitions.set(id, raw)
        }
        roots.set(root, definitions)
        return definitions
    }
    const admitFragment = (root, baseURI = root?.ownerDocument?.baseURI ?? 'http://localhost/') => {
        if (!root) return
        for (const [id, raw] of definitionsFor(root)) admit(id, raw, baseURI)
    }
    const read = (batch, baseURI) => {
        const id = batch?.getAttribute?.('data-fff-plan-id')
        if (id === null || id === undefined) return null
        if (!ID.test(id)) throw Error('Invalid FLEX asset plan identity.')
        const component = batch.dataset?.fffAssetConsumer, generation = batch.getAttribute('data-fff-plan-generation')
        if (!component || !/^[1-9][0-9]{0,14}$/.test(generation ?? '')) throw Error('FLEX asset plan requires an owner and positive generation.')
        if (['data-fff-stylesheets', 'data-fff-chunks', 'data-fff-asset-sizes', 'data-fff-factory-owners'].some(name => batch.hasAttribute(name))) {
            throw Error('FLEX asset boundary cannot mix referenced and inline plans.')
        }
        const cached = markers.get(batch)
        const unchanged = cached?.id === id && cached.component === component && cached.generation === generation
        // Re-read all owner/mixed-plan declarations above before using a frozen
        // absolute plan. Relative declarations and explicit bases keep URI checks.
        if (unchanged && baseURI === undefined && baseIndependentPlans.has(cached.plan)) return cached.plan
        if (baseURI === undefined) baseURI = batch?.ownerDocument?.baseURI ?? globalThis.document?.baseURI ?? 'http://localhost/'
        if (unchanged && cached.baseURI === baseURI) return cached.plan
        const key = JSON.stringify([baseURI, id])
        let plan = plans.get(key)?.plan
        // Detached incoming fragments have their own dictionary. Prefer it over
        // a live document cache; admission validates conflicts before activation.
        let root = batch.getRootNode?.()
        if (!root) { root = batch; while (root.parentElement) root = root.parentElement }
        if (root === batch.ownerDocument) {
            if (!plan) {
                const definitions = [...root.querySelectorAll(`[data-fff-plan-definition="${id}"]`)]
                if (definitions.length > 4096) throw Error('FLEX fragment plan dictionary exceeds its definition budget.')
                for (const definition of definitions) plan = admit(id, definition.content?.textContent ?? definition.textContent, baseURI)
            }
        } else if (root) {
            const definitions = definitionsFor(root)
            if (definitions.has(id)) plan = admit(id, definitions.get(id), baseURI)
        }
        if (!plan) throw Error(`Missing FLEX asset plan definition: ${id}.`)
        if (plan.componentId !== component) throw Error('FLEX asset plan does not belong to this instance component.')
        markers.set(batch, { id, baseURI, component, generation, plan })
        return plan
    }
    return {
        admit, admitFragment, read,
        clear() { plans.clear(); bytes = 0; markers = new WeakMap(); roots = new WeakMap() },
        inspect() { return { plans: plans.size, bytes, limit, byteLimit } },
    }
}

/** Shared by bootstrap and injector without retaining a document or DOM owner. */
function dictionaryFor(document) {
    if (!document) throw Error('A referenced FLEX plan requires its owning document.')
    const state = getRuntimeState()
    state.planDictionaries ??= new WeakMap()
    let dictionary = state.planDictionaries.get(document)
    if (!dictionary) { dictionary = createPlanDictionary(); state.planDictionaries.set(document, dictionary) }
    return dictionary
}

export function readReferencedPlan(batch, baseURI) {
    if (!batch?.hasAttribute?.('data-fff-plan-id')) return null
    return dictionaryFor(batch.ownerDocument ?? globalThis.document).read(batch, baseURI)
}

/** Admit response-local definitions before native morph starts. No owner activation,
 * observer or resource acquisition occurs; active projections survive later eviction.
 */
export function admitFragmentPlans(root, document = root?.ownerDocument ?? globalThis.document) {
    dictionaryFor(document).admitFragment(root, document.baseURI)
}

export function readAssetSizes(batch) {
    const plan = readReferencedPlan(batch)
    if (plan) return plan.sizes
    try { return JSON.parse(batch.getAttribute('data-fff-asset-sizes') ?? '{}') } catch { return {} }
}
