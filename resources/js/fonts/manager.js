import {loadFontPreview,assertFontResource} from './transport.js'

const managers = new WeakMap()

/** Shared flights, individual leases. No borrowed FontFace or stylesheet is ever deleted. */
export function createFontPreviewManager({load = loadFontPreview, add, remove, concurrency = 4,
    origin = globalThis.location?.origin, frame = callback => requestAnimationFrame(callback), cancelFrame = id => cancelAnimationFrame(id)} = {}) {
    if (!Number.isInteger(concurrency) || concurrency < 1 || concurrency > 8) throw new TypeError('Invalid font concurrency.')
    const entries = new Map(), owners = new Map(), dirty = new Set()
    let flights = 0, sequence = 0, pumping = false, notifyFrame = null
    const notify = entry => {
        for (const owner of entry.owners.keys()) dirty.add(owner)
        if (notifyFrame !== null || !dirty.size) return
        notifyFrame = frame(() => {
            notifyFrame = null
            const pending = [...dirty];dirty.clear()
            for (const owner of pending) owners.get(owner)?.notify?.()
        })
    }
    const current = entry => entries.get(entry.key) === entry && entry.owners.size > 0
    const pump = () => {
        pumping = false
        while (flights < concurrency) {
            const entry = [...entries.values()].filter(value=>value.status === 'queued' && value.owners.size)
                .sort((a,b)=>Number([...b.owners.values()].some(Boolean))-Number([...a.owners.values()].some(Boolean)) || a.order-b.order)[0]
            if (!entry) break
            entry.status = 'loading';entry.controller = new AbortController();flights++;notify(entry)
            void Promise.resolve().then(()=>load(entry.resource,entry.controller.signal)).then(result=>{
                if (!current(entry) || entry.controller.signal.aborted) return
                add(result.face)
                entry.result = result;entry.status = 'ready';notify(entry)
            }).catch(error=>{
                if (!current(entry)) return
                entry.status = 'failed';entry.error = error?.name === 'AbortError' ? 'timeout' : 'unavailable';notify(entry)
            }).finally(()=>{flights--;schedulePump()})
        }
    }
    const schedulePump = () => {if (!pumping) {pumping=true;queueMicrotask(pump)}}
    const retire = entry => {
        entries.delete(entry.key)
        entry.controller?.abort()
        if (entry.result) {remove(entry.result.face);entry.result=null}
    }
    const release = owner => {
        const previous = owners.get(owner)
        if (!previous) return
        owners.delete(owner);dirty.delete(owner)
        for (const key of previous.keys) {
            const entry = entries.get(key)
            if (!entry) continue
            entry.owners.delete(owner)
            if (!entry.owners.size) retire(entry)
        }
        if (!owners.size && notifyFrame !== null) {cancelFrame(notifyFrame);notifyFrame=null}
    }
    return Object.freeze({
        replaceDemand(owner, resources, callback) {
            const next = new Map()
            for (const resource of resources) {
                assertFontResource(resource,origin)
                next.set(resource.key,resource)
            }
            if (!next.size) {release(owner);return}
            const previous = owners.get(owner)
            owners.set(owner,{keys:new Set(next.keys()),notify:callback})
            for (const [key,resource] of next) {
                let entry = entries.get(key)
                if (!entry) {entry={key,resource,status:'queued',owners:new Map(),order:++sequence,result:null,controller:null};entries.set(key,entry)}
                entry.owners.set(owner,!!resource.selected)
            }
            for (const key of previous?.keys ?? []) {
                if (next.has(key)) continue
                const entry = entries.get(key)
                if (!entry) continue
                entry.owners.delete(owner)
                if (!entry.owners.size) retire(entry)
            }
            schedulePump()
        },
        releaseOwner:release,
        read(key) {
            const entry = entries.get(key)
            return entry ? {status:entry.status,alias:entry.result?.alias ?? '',error:entry.error ?? null} : {status:'idle',alias:'',error:null}
        },
        retry(key) {
            const entry = entries.get(key)
            if (!entry || entry.status !== 'failed' || !entry.owners.size) return
            entry.status='queued';entry.error=null;notify(entry);schedulePump()
        },
        snapshot() {
            return {owners:owners.size,resources:entries.size,flights,ready:[...entries.values()].filter(e=>e.status==='ready').length,
                queued:[...entries.values()].filter(e=>e.status==='queued').length,concurrency}
        },
    })
}

export function getFontPreviewManager(document) {
    let manager = managers.get(document)
    if (!manager) {
        const window = document.defaultView
        manager = createFontPreviewManager({origin:window.location.origin,
            load:(resource,signal)=>loadFontPreview(resource,signal,{origin:window.location.origin,fetch:window.fetch.bind(window),Font:window.FontFace}),
            add:face=>document.fonts.add(face),remove:face=>document.fonts.delete(face),
            frame:callback=>window.requestAnimationFrame(callback),cancelFrame:id=>window.cancelAnimationFrame(id)})
        managers.set(document,manager)
    }
    return manager
}
