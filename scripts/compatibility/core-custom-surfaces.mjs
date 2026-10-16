import assert from 'node:assert/strict'
import { createCustomSurfaces } from '../../resources/js/runtime/surfaces/custom-surfaces.js'
import { customSurfacesFor } from '../../resources/js/runtime/surfaces/document-surfaces.js'
import { isAssetElementActive } from '../../resources/js/runtime/lifecycle/asset-activation.js'
import { createSurfaceRegistry } from '../../resources/js/runtime/filament/surface-registry.js'
import { createAssetSurface } from '../../resources/js/sdk/asset-surface.js'
import { runtimeReadiness } from '../../resources/js/runtime/alpine/runtime-readiness.js'

const deferred = () => { let resolve, reject; const promise = new Promise((yes, no) => { resolve = yes; reject = no }); return { promise, resolve, reject } }
const flush = async () => { for (let tick = 0; tick < 8; tick++) await Promise.resolve() }
function fixture() {
    const document = {}, released = [], failures = [], pending = []; let retired = 0
    const element = (parent = document.body) => {
        const attributes = new Map(), classes = new Set()
        const node = { nodeType: 1, ownerDocument: document, parentElement: parent, parentNode: parent, isConnected: true,
            hidden: false, style: {}, classList: { contains: name => classes.has(name), add: name => classes.add(name) },
            getAttribute: name => attributes.get(name) ?? null, setAttribute: (name, value) => attributes.set(name, value),
            hasAttribute: name => attributes.has(name), contains(target) { for (let n = target; n; n = n.parentElement) if (n === node) return true; return false },
            closest() { return null },
        }
        return node
    }
    document.body = element(null)
    const registry = createCustomSurfaces({ document, prepare(root, signal) { const task = deferred(); pending.push({ root, signal, ...task }); return task.promise },
        release: root => released.push(root), retire: () => retired++, reportFailure: error => failures.push(error),
        isActive: root => root.enabled !== false, isReservedRoot: root => root.classList?.contains('fi-modal') === true, limit: 4 })
    return { document, registry, element, pending, released, failures, retired: () => retired }
}
const proofs = [], unhandled = []
const observe = error => unhandled.push(error)
process.on('unhandledRejection', observe)
try {
    const f = fixture(), root = f.element(), child = f.element(root)
    root.hidden = true
    const handle = f.registry.register(root)
    assert.equal(handle.state, 'closed'); assert.equal(isAssetElementActive(child), false)
    const first = handle.activate(); assert.equal(handle.activate(), first)
    assert.equal(isAssetElementActive(child), true); assert.equal(handle.ready, false)
    await flush(); assert.equal(f.pending.length, 1)
    f.pending[0].resolve(true); assert.equal(await first, true); assert.equal(handle.ready, true)
    root.enabled=false; assert.equal(handle.ready, false); root.enabled=true
    const native = createSurfaceRegistry({ document: f.document, customSurfaces: f.registry })
    assert.match(native.resolveAssetOwnerKey(child), /^surface:custom-/)
    proofs.push('closed root is dormant; hidden host prepares once, gates ready and receives an independent non-Filament owner')

    const second = handle.activate(); await flush(); handle.close()
    assert.equal(await second, false); assert.equal(f.pending[1].signal.aborted, true)
    f.pending[1].resolve(true); await flush(); assert.equal(handle.ready, false)
    const third = handle.activate(); await flush(); handle.suspend()
    assert.equal(await third, false); assert.equal(handle.state, 'suspended'); assert.equal(isAssetElementActive(child), true)
    const fourth = handle.activate(); await flush(); f.pending[2].reject(Error('obsolete'))
    f.pending[3].resolve(true); assert.equal(await fourth, true); assert.deepEqual(f.failures, [])
    proofs.push('close and suspend settle immediately; late completion/rejection cannot reveal or fail a replacement; suspension retains the parent')

    const remote = f.element(), remoteChild = f.element(remote), nested = f.registry.register(remote, { parent: handle })
    const opening = nested.activate(); await flush(); f.pending.at(-1).resolve(true); assert.equal(await opening, true)
    handle.suspend(); assert.equal(isAssetElementActive(remoteChild), true)
    const released = f.released.length; nested.close()
    assert.equal(f.released.length, released + 1); assert.equal(handle.state, 'suspended'); assert.equal(isAssetElementActive(child), true)
    const resume = handle.activate(); await flush(); f.pending.at(-1).resolve(true); await resume
    const n = nested.activate(); await flush(); handle.close()
    assert.equal(await n, false); assert.equal(nested.state, 'closed'); assert.equal(isAssetElementActive(remoteChild), false)
    assert.equal(await nested.activate(), false)
    proofs.push('explicit teleported child retains a suspended parent; child close preserves parent resources and parent close cascades to descendants')

    const failing = handle.activate(); await flush(); const failure = Error('active failure'); f.pending.at(-1).reject(failure)
    await assert.rejects(failing, error => error === failure); assert.equal(handle.ready, false); assert.equal(f.failures.length, 1)
    const retry = handle.activate(); await flush(); f.pending.at(-1).resolve(true); assert.equal(await retry, true)
    proofs.push('active preparation failures stay observable; retry uses a fresh generation without duplicating the registration')

    assert.throws(() => f.registry.register(root)); assert.throws(() => f.registry.register(f.element(), { parent: {} }))
    assert.throws(() => f.registry.register(f.element(), { signal: {} }))
    const nativeRoot = f.element(); nativeRoot.classList.add('fi-modal'); assert.throws(() => f.registry.register(nativeRoot))
    const foreign = f.element(); foreign.ownerDocument = {}; assert.throws(() => f.registry.register(foreign))
    const extra = f.registry.register(f.element()), last = f.registry.register(f.element())
    assert.throws(() => f.registry.register(f.element()))
    extra.dispose(); last.dispose(); assert.equal(f.registry.snapshot().surfaces, 2)
    proofs.push('native modal, foreign document, forged parent, malformed signal, duplicate root and registration budgets reject before admission')

    handle.dispose(); assert.equal(nested.state, 'disposed'); assert.throws(() => nested.activate())
    assert.equal(f.registry.snapshot().surfaces, 0); handle.dispose(); nested.dispose()
    for (let cycle = 0; cycle < 1000; cycle++) {
        const signal = new AbortController(), node = f.element(), current = f.registry.register(node, { signal: signal.signal })
        signal.abort(); assert.equal(current.state, 'disposed')
    }
    assert.equal(f.registry.snapshot().surfaces, 0); assert.equal(f.registry.snapshot().attempts, 0)
    proofs.push('1000 owner aborts and repeated disposal retain zero registrations or asynchronous interests; disposing a parent invalidates child handles')

    const detached = f.element(), d = f.registry.register(detached), openingDetached = d.activate()
    await flush(); detached.isConnected = false; f.registry.prune(detached)
    assert.equal(await openingDetached, false); assert.equal(d.state, 'disposed'); assert.equal(f.registry.snapshot().surfaces, 0)
    const a = f.element(), moved = f.registry.register(a); f.registry.prune(a); assert.equal(moved.state, 'closed')
    f.registry.dispose(); assert.equal(moved.state, 'disposed'); assert.equal(customSurfacesFor(f.document), null)
    proofs.push('DOM moves preserve handles; detached roots invalidate them; final adapter disposal releases document policy')

    const g = fixture(), parentSignal = new AbortController()
    const r = { isDisposed: () => false, awaitBundleReady() {}, registerAssetSurface: g.registry.register }
    runtimeReadiness().publish(r)
    const sdk = await createAssetSurface(g.element(), { signal: parentSignal.signal })
    parentSignal.abort(); assert.equal(sdk.state, 'disposed')
    await assert.rejects(createAssetSurface(g.element(), { signal: parentSignal.signal }), { name: 'AbortError' })
    runtimeReadiness().unpublish(r); g.registry.dispose()
    const same = fixture(), newer = createCustomSurfaces({ document: same.document, prepare: async () => true, release() {}, retire() {} })
    same.registry.dispose(); assert.equal(customSurfacesFor(same.document), newer)
    newer.dispose(); assert.equal(customSurfacesFor(same.document), null)
    proofs.push('public SDK uses the document runtime and owner cancellation; retirement of an older adapter cannot remove a newer policy')

    const broken = fixture(); broken.registry.dispose()
    const faulty = createCustomSurfaces({ document: broken.document, prepare: async () => { throw Error('prepare') },
        release() { throw Error('release') }, retire() { throw Error('retire') }, reportFailure: async () => { throw Error('report') } })
    const badSignal = { aborted:false, addEventListener() { throw Error('attach') }, removeEventListener() { throw Error('detach') } }
    assert.throws(() => faulty.register(broken.element(), { signal: badSignal }))
    assert.equal(faulty.snapshot().surfaces, 0)
    const active = faulty.register(broken.element()); await assert.rejects(active.activate())
    active.close(); active.dispose(); assert.equal(faulty.snapshot().surfaces, 0)
    faulty.dispose(); assert.equal(customSurfacesFor(broken.document), null)
    const ended = new AbortController(); ended.abort()
    assert.throws(() => createCustomSurfaces({ document: broken.document, signal:ended.signal, prepare() {}, release() {}, retire() {} }))
    proofs.push('throwing cleanup, signal attachment/removal and rejected error reporting cannot strand registrations or create unhandled failures')
    if (typeof globalThis.gc === 'function') {
        const memory = fixture(); memory.registry.dispose()
        const registry = createCustomSurfaces({ document: memory.document, prepare:async () => true, release() {}, retire() {} })
        const retained = (() => { const root=memory.element(), weak=new WeakRef(root), handle=registry.register(root); handle.dispose(); return { weak, handle } })()
        let collected=false
        for (let cycle=0; cycle<20; cycle++) {
            await new Promise(resolve => setImmediate(resolve)); globalThis.gc()
            if (!retained.weak.deref()) { collected=true; break }
        }
        assert.equal(collected, true, 'Retained disposed handle kept the detached root alive.')
        assert.equal(retained.handle.state, 'disposed'); registry.dispose()
        proofs.push('actual V8 GC collects the old root while the application deliberately retains its disposed public handle')
    }
    native.dispose()
    await new Promise(resolve => setImmediate(resolve)); assert.deepEqual(unhandled, [])
    console.log(JSON.stringify({ groups: proofs.length, proofs, unhandledRejections: unhandled.length, testsModified: false }))
} finally { process.removeListener('unhandledRejection', observe) }
