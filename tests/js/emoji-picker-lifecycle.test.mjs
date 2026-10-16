import assert from 'node:assert/strict'
import test from 'node:test'
import { createEmojiPickerController } from '../../resources/js/emoji/controller.js'
import { prepareShadowStyles, disposeShadowStyles } from '../../resources/js/emoji/shadow-styles.js'

class Node extends EventTarget {
    isConnected = true
    hidden = false
    children = []
    dataset = {}
    attrs = {}
    setAttribute(key, value) { this.attrs[key] = value }
    append(...nodes) { this.children.push(...nodes) }
    replaceChildren(...nodes) { this.children = nodes }
    querySelector() { return null }
    remove() { this.removed = true }
}
class Observer {
    observe() {}
    disconnect() { this.disconnected = true }
}
globalThis.MutationObserver = Observer
globalThis.document = { body: new Node(), documentElement: new Node(), createElement: () => new Node() }
const deferred = () => {
    let resolve, reject
    const promise = new Promise((yes, no) => { resolve = yes; reject = no })
    return { promise, resolve, reject }
}
const fixture = cache => {
    const view = { panel: new Node(), loading: new Node(), content: new Node() }
    const surfaces = []
    const controller = createEmojiPickerController({ cache, present: () => {}, createContent: () => view,
        createSurface: ({ onClose }) => {
            const surface = { menuOpen: false, menuReady: true, disposed: false,
                closeTeleportedMenu() { onClose() }, closeTeleportedMenuImmediate() { onClose() },
                dispose() { this.disposed = true }, updateMenuPosition() {},
            }
            surfaces.push(surface)
            return surface
        },
    })
    const owner = new Node(), anchor = new Node()
    return { view, surfaces, controller, context: { $el: owner }, anchor }
}
const entry = () => ({ picker: new Node(), databaseReady: Promise.resolve(), stylesReady: Promise.resolve(), ready: false })
const select = picker => {
    const event = new Event('emoji-click')
    event.detail = { unicode: '😀' }
    picker.dispatchEvent(event)
}

test('two Alpine proxies share DOM ownership; disposal removes selection listeners', async () => {
    const content = entry()
    const f = fixture({ acquire: async () => content, ready: () => false })
    const picked = []
    await f.controller.open({ ...f, locale: 'en', stylesHref: '/shadow.css', onSelect: value => picked.push(value), closeOnSelect: false })
    assert.equal(f.controller.isOpenFor({ $el: f.context.$el }), true)
    select(content.picker)
    assert.deepEqual(picked, ['😀'])
    f.controller.disposeFor({ $el: f.context.$el })
    select(content.picker)
    await Promise.resolve()
    assert.deepEqual(picked, ['😀'])
    assert.equal(f.view.panel.hidden, true)
    assert.equal(f.surfaces[0].disposed, true)
})

test('closing during data loading never mounts late content or invokes readiness', async () => {
    const pending = deferred()
    const f = fixture({ acquire: () => pending.promise, ready: () => false })
    const ready = []
    const opening = f.controller.open({ ...f, locale: 'en', stylesHref: '/shadow.css', onReadyChange: value => ready.push(value) })
    f.controller.close()
    pending.resolve(entry())
    await opening
    assert.deepEqual(ready, [false])
    assert.equal(f.view.content.children.length, 0)
    assert.equal(f.controller.isActive(), false)
})

test('replacement session wins over an older async acquisition and selection closes once', async () => {
    const pending = deferred(), first = entry(), second = entry()
    const f = fixture({ acquire: locale => locale === 'en' ? pending.promise : Promise.resolve(second), ready: () => false })
    let closed = 0, oldPicked = 0, picked = 0
    const opening = f.controller.open({ ...f, locale: 'en', onClose: () => closed++, onSelect: () => oldPicked++ })
    await f.controller.open({ ...f, context: { $el: new Node() }, anchor: new Node(), locale: 'pl', onSelect: () => picked++ })
    pending.resolve(first)
    await opening
    assert.equal(f.view.content.children[0], second.picker)
    select(first.picker)
    select(second.picker)
    select(second.picker)
    assert.equal(closed, 1)
    assert.equal(oldPicked, 0)
    assert.equal(picked, 1)
})

test('content remains loading until both database and stylesheet complete', async () => {
    const css = deferred(), database = deferred(), content = entry()
    content.stylesReady = css.promise
    content.databaseReady = database.promise
    const f = fixture({ acquire: async () => content, ready: () => false })
    const opening = f.controller.open({ ...f, locale: 'en' })
    await Promise.resolve()
    database.resolve()
    await Promise.resolve()
    assert.equal(f.view.loading.hidden, false)
    css.resolve()
    await opening
    assert.equal(f.view.loading.hidden, true)
    assert.equal(content.ready, true)
    f.controller.close()
})

test('shadow stylesheet is single-flight and pending work can be cancelled safely', async () => {
    const picker = new Node()
    picker.ownerDocument = document
    picker.shadowRoot = new Node()
    const first = prepareShadowStyles(picker, '/shadow.css?v=abc')
    assert.equal(prepareShadowStyles(picker, '/shadow.css?v=abc'), first)
    assert.equal(picker.shadowRoot.children.length, 1)
    picker.shadowRoot.children[0].onload()
    await first
    const changed = prepareShadowStyles(picker, '/shadow.css?v=def')
    disposeShadowStyles(picker)
    await assert.rejects(changed, /owner disposed/)
    assert.equal(picker.shadowRoot.children[1].onload, null)
    assert.equal(picker.shadowRoot.children[1].removed, true)
})

test('a cancelled prior stylesheet request cannot cancel its replacement', async () => {
    const picker = new Node()
    picker.ownerDocument = document
    picker.shadowRoot = new Node()
    const first = prepareShadowStyles(picker, '/old.css')
    const next = prepareShadowStyles(picker, ['/input.css', '/new.css'])
    await assert.rejects(first, /owner disposed/)
    assert.equal(picker.shadowRoot.children[1].removed, undefined)
    picker.shadowRoot.children[1].onload()
    picker.shadowRoot.children[2].onload()
    await next
    disposeShadowStyles(picker)
})

test('content cache imports once, reacquires database readiness and evicts only inactive locale content', async () => {
    const { createEmojiContentCache } = await import('../../resources/js/emoji/content-cache.js')
    const originalCreate = document.createElement
    let imports = 0
    document.createElement = tag => {
        const node = new Node()
        if (tag === 'emoji-picker') {
            node.ownerDocument = document
            node.shadowRoot = new Node()
            node.database = { readyCalls: 0, closed: 0,
                ready() { this.readyCalls++; return Promise.resolve() },
                close() { this.closed++ },
            }
        }
        return node
    }
    const cache = createEmojiContentCache({ limit: 2, importPicker: async () => { imports++ } })
    const acquire = async (locale, active) => {
        const content = await cache.acquire(locale, '/shadow.css', active)
        for (const link of content.picker.shadowRoot.children) link.onload?.()
        await content.stylesReady
        return content
    }
    try {
        const first = await acquire('en')
        assert.equal(await acquire('en'), first)
        assert.equal(first.picker.database.readyCalls, 2)
        const second = await acquire('pl')
        const third = await acquire('fr', first.picker)
        assert.equal(imports, 1)
        assert.equal(second.scope.disposed, true)
        assert.equal(second.picker.database.closed, 1)
        assert.equal(first.scope.disposed, false)
        cache.forget('en')
        cache.forget('fr')
        assert.equal(first.scope.disposed, true)
        assert.equal(third.scope.disposed, true)
    } finally {
        document.createElement = originalCreate
    }
})

test('skin tone opening anchors the chosen hand and keeps it fixed during keyboard navigation', async () => {
    const { alignSkinToneSelection } = await import('../../resources/js/emoji/content-adapter.js')
    let expanded = 'false'
    const button = { textContent: '✋🏾', getAttribute: () => expanded }
    const values = new Map()
    const list = { dataset: {}, style: { setProperty: (key, value) => values.set(key, value) },
        querySelectorAll: () => ['✋', '✋🏻', '✋🏼', '✋🏽', '✋🏾', '✋🏿'].map(textContent => ({ textContent })) }
    const root = { querySelector: selector => selector === '#skintone-button' ? button : list }
    alignSkinToneSelection(root)
    expanded = 'true'
    alignSkinToneSelection(root)
    assert.equal(values.get('--fff-skin-selected-index'), '4')
    button.textContent = '✋🏿'
    alignSkinToneSelection(root)
    assert.equal(values.get('--fff-skin-selected-index'), '4')
    expanded = 'false'
    values.clear() // Native rendering replaces the complete style attribute.
    alignSkinToneSelection(root)
    assert.equal(values.get('--fff-skin-selected-index'), '4')
    expanded = 'true'
    alignSkinToneSelection(root)
    assert.equal(values.get('--fff-skin-selected-index'), '5')
})

test('native empty and loading alert placeholders do not fail grid readiness', async () => {
    const { waitForEmojiContent } = await import('../../resources/js/emoji/content-readiness.js')
    const OriginalObserver = globalThis.MutationObserver
    let inspect, grid = null, disconnected = 0
    globalThis.MutationObserver = class { constructor(callback) { inspect = callback } observe() {} disconnect() { disconnected++ } }
    try {
        const picker = { isConnected: true, shadowRoot: { querySelector: selector => selector === '.emoji-menu .emoji' ? grid : { textContent: 'Loading…' } } }
        let ready = false
        const opening = waitForEmojiContent(picker, new AbortController().signal).then(() => { ready = true })
        await Promise.resolve(); assert.equal(ready, false)
        grid = {}; inspect(); await opening
        assert.equal(ready, true); assert.equal(disconnected, 1)
    } finally { globalThis.MutationObserver = OriginalObserver }
})


test('emoji input geometry uses the full shell while retaining button and field ownership', async () => {
    const content = entry(), shell = new Node(), anchor = new Node(), owner = new Node()
    anchor.closest = selector => selector === '.fff-flex-text-input__shell' ? shell : null
    const view = {panel:new Node(),loading:new Node(),content:new Node()}
    let configuration
    const controller = createEmojiPickerController({cache:{acquire:async()=>content,ready:()=>false},present:()=>{},createContent:()=>view,
        createSurface:options=>{configuration=options;return {menuOpen:false,menuReady:true,updateMenuPosition(){},closeTeleportedMenu(){options.onClose()},closeTeleportedMenuImmediate(){options.onClose()},dispose(){}}}})
    try {
        await controller.open({context:{$el:owner},anchor,onSelect(){}})
        assert.equal(configuration.anchor,shell)
        assert.equal(configuration.ownershipAnchor,anchor)
        assert.equal(configuration.controllerOwner,owner)
        assert.equal(configuration.align,'end')
    } finally {controller.close()}
})
