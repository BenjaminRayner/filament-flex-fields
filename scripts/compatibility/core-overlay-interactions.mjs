import assert from 'node:assert/strict'
import { build } from 'esbuild'
import { createServer } from 'node:http'
import { launchCoreBrowser, browserEngine } from './browser-profile.mjs'
import { createFlexDropdownRegistry } from '../../resources/js/overlay/ownership/controller-registry.js'

const proofs = [], errors = []
{
    const registry = createFlexDropdownRegistry(), open = { a: true, b: false, c: false }
    let newest
    for (const id of ['a', 'b', 'c']) registry.register(id, () => ({
        isOpen: () => open[id],
        close() {
            open[id] = false
            if (id === 'a') { open.c = true; newest = registry.openExclusive('c') }
        },
    }))
    open.b = true
    const obsolete = registry.openExclusive('b')
    assert.deepEqual(open, { a: false, b: false, c: true })
    assert.equal(obsolete.current(), false); assert.equal(newest.current(), true)
    proofs.push('reentrant close opening a newer owner invalidates the old arbitration before it can close the newer surface')
}
{
    const registry = createFlexDropdownRegistry()
    let closed = 0, lease
    const release = registry.register('old', () => ({ isOpen() { release(); lease = registry.openExclusive('new'); return true }, close: () => closed++ }))
    assert.equal(registry.openExclusive('outer').current(), false)
    assert.equal(closed, 0); assert.equal(lease.current(), true)
    proofs.push('factory/open-state callbacks changing generation cannot publish an obsolete close; replaced registration release is identity-safe')
}

const compiled = await build({ stdin: { resolveDir: process.cwd(), contents: `
import { documentOverlayInteractionSessions } from './resources/js/overlay/ownership/interaction-sessions.js';
import { createMenuListenersMixin } from './resources/js/overlay/menu/listeners.js';
import { lockOverlaySheetScroll, unlockOverlaySheetScroll, syncOverlaySheetToVisualViewport } from './resources/js/core/overlay-sheet-scroll-lock.js';
import { acquireSheetScrollLease } from './resources/js/overlay/ownership/sheet-scroll-leases.js';
import { createMenuSheetEnvironmentMixin, releaseOverlaySheetEnvironment } from './resources/js/overlay/menu/sheet-environment.js';
import { createImperativeMenuSurface } from './resources/js/overlay/menu/imperative-surface.js';
import { createSearchableSelectMenuMixin } from './resources/js/overlay/menu/menu-controller.js';
import { documentOwnerConnectivity } from './resources/js/runtime/lifecycle/owner-connectivity.js';
import { documentDropdownControllers } from './resources/js/overlay/ownership/document-controllers.js';
import { registerSelectOverlayMorphCleanup } from './resources/js/overlay/menu/legacy-cleanup.js';
import { createOverlayRuntime } from './resources/js/core/overlay-runtime.js';
window.overlayRuntime = () => createOverlayRuntime({ document, window });
window.legacyMorphRegister = registerSelectOverlayMorphCleanup;
window.surface = createImperativeMenuSurface;
window.menuMixin = createSearchableSelectMenuMixin;
window.connections = () => documentOwnerConnectivity(document);
window.controllers = () => documentDropdownControllers(document);
window.broker = () => documentOverlayInteractionSessions(document);
window.brokerFor = documentOverlayInteractionSessions;
window.sheetLease = acquireSheetScrollLease;
window.sheetOwners = () => document[Symbol.for('filament-flex-fields.sheet-scroll-owners.v1')];
window.sheetEnvironment = createMenuSheetEnvironmentMixin;
window.releaseEnvironment = releaseOverlaySheetEnvironment;
window.sheet = { lock: () => lockOverlaySheetScroll(document, window), unlock: () => unlockOverlaySheetScroll(document, window), sync: syncOverlaySheetToVisualViewport };
window.menuHost = (anchor, panel) => Object.assign(createMenuListenersMixin({ openKey: 'open', scrollHandlerKey: 'scroll', resizeHandlerKey: 'resize', scrollParentsKey: 'parents', dismissOutside: true }), {
  open: true, trigger: anchor, panel, calls: 0, reasons: [], __fffOverlayManaged: true,
  resolveMenuTriggerRef() { return this.trigger }, resolveMenuElement() { return this.panel }, updateMenuPosition() {},
  closeTeleportedMenu() { this.calls++; if (!this.holdExit) this.open = false }
});
` }, bundle: true, write: false, minify: true, format: 'iife', logLevel: 'silent' })
const server = createServer((req, res) => {
    res.writeHead(200, { 'Content-Type': req.url === '/probe.js' ? 'text/javascript' : 'text/html' })
    res.end(req.url === '/probe.js' ? compiled.outputFiles[0].contents : `<!doctype html><html><body>
<section role="dialog" aria-modal="true" id="modal"><button id="anchor">Open</button>
<div id="parent"><button id="inside">Inside</button><button id="child-anchor">Child</button></div>
<div id="child"><button id="child-focus">Child focus</button></div></section><button id="outside">Outside</button>
<script>window.morphHooks = 0; window.Livewire = { hook() { window.morphHooks++; } };</script>
<script src="/probe.js"></script></body></html>`)
})
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
const engine = browserEngine
const browser = await launchCoreBrowser({ headless: true, ...(engine === 'chromium' ? { args: ['--js-flags=--expose-gc'] } : {}) }), page = await browser.newPage()
page.on('pageerror', error => errors.push(String(error)))
try {
    await page.goto(`http://127.0.0.1:${server.address().port}/`)
    await page.evaluate(() => {
        window.counts = { key: 0, pointer: 0, resize: 0, guards: 0, native: 0 }
        const add = window.addEventListener.bind(window), remove = window.removeEventListener.bind(window)
        window.addEventListener = (type, fn, options) => { if (type === 'resize') counts.resize++; if (options === true) { if (type === 'keydown') counts.key++; if (type === 'pointerdown') counts.pointer++ }; return add(type, fn, options) }
        window.removeEventListener = (type, fn, options) => { if (type === 'resize') counts.resize--; if (options === true) { if (type === 'keydown') counts.key--; if (type === 'pointerdown') counts.pointer-- }; return remove(type, fn, options) }
        const addDoc = document.addEventListener.bind(document), removeDoc = document.removeEventListener.bind(document)
        document.addEventListener = (type, fn, options) => { if (type === 'touchmove') counts.guards++; return addDoc(type, fn, options) }
        document.removeEventListener = (type, fn, options) => { if (type === 'touchmove') counts.guards--; return removeDoc(type, fn, options) }
        add('keydown', event => { if (event.key === 'Escape') counts.native++ })
        window.get = id => document.getElementById(id)
        window.parentHost = menuHost(get('anchor'), get('parent'))
        window.childHost = menuHost(get('child-anchor'), get('child'))
        childHost.holdExit = true
        childHost.bindMenuListeners(); parentHost.bindMenuListeners()
        get('child-focus').focus()
    })
    assert.deepEqual(await page.evaluate(() => broker().snapshot()), { sessions: 2, listening: true, observing: true })
    await page.keyboard.press('Escape'); await page.keyboard.press('Escape')
    assert.deepEqual(await page.evaluate(() => [childHost.calls, parentHost.calls, counts.key, counts.pointer, counts.native, document.activeElement.id]), [1, 0, 1, 1, 0, 'child-anchor'])
    await page.evaluate(() => { childHost.open = false; childHost.unbindMenuListeners(); get('inside').focus() })
    await page.keyboard.press('Escape')
    assert.equal(await page.evaluate(() => parentHost.calls), 1)
    await page.evaluate(() => parentHost.unbindMenuListeners())
    assert.deepEqual(await page.evaluate(() => broker().snapshot()), { sessions: 0, listening: false, observing: false })
    proofs.push('actual shared menu listeners bind one document listener pair; innermost child wins even after parent rebind; repeated Escape during exit never closes its parent, then focus restores to the proper anchors')

    await page.evaluate(() => {
        parentHost.open = true; parentHost.calls = 0; parentHost.bindMenuListeners()
        const dialog = document.createElement('section'); dialog.id = 'second-modal'; dialog.setAttribute('role', 'dialog'); dialog.setAttribute('aria-modal', 'true'); dialog.innerHTML = '<input id="modal-input">'
        document.body.append(dialog); get('modal-input').focus()
    })
    await page.keyboard.press('Escape')
    assert.deepEqual(await page.evaluate(() => [parentHost.calls, counts.native, document.activeElement.id]), [0, 1, 'modal-input'])
    await page.evaluate(() => { get('second-modal').remove(); get('inside').focus() })
    await page.keyboard.press('Alt+Escape')
    await page.evaluate(() => window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', isComposing: true, bubbles: true })))
    assert.equal(await page.evaluate(() => parentHost.calls), 0)
    await page.keyboard.press('Escape')
    await page.evaluate(() => parentHost.unbindMenuListeners())
    proofs.push('an unrelated child modal retains native Escape/focus ownership; composition and modified shortcuts are not intercepted by a parent dropdown')

    await page.evaluate(() => {
        parentHost.open = true; parentHost.calls = 0; parentHost.bindMenuListeners()
        childHost.open = true; childHost.calls = 0; childHost.holdExit = false; childHost.bindMenuListeners()
    })
    await page.locator('#inside').click()
    assert.deepEqual(await page.evaluate(() => [childHost.calls, parentHost.calls]), [1, 0])
    await page.evaluate(() => childHost.unbindMenuListeners())
    await page.locator('#outside').click({ button: 'right' })
    assert.equal(await page.evaluate(() => parentHost.calls), 0)
    await page.locator('#outside').click()
    assert.equal(await page.evaluate(() => parentHost.calls), 1)
    await page.evaluate(() => parentHost.unbindMenuListeners())
    proofs.push('outside pointer dismissal targets one owner only, preserves parent interactions and ignores secondary-button input')

    await page.evaluate(() => {
        parentHost.open = true; parentHost.calls = 0; parentHost.bindMenuListeners()
        const next = document.createElement('button'); next.id = 'replacement-anchor'; next.textContent = 'Replacement'
        get('anchor').replaceWith(next); parentHost.trigger = next; get('inside').focus()
    })
    await page.keyboard.press('Escape')
    assert.equal(await page.evaluate(() => document.activeElement.id), 'replacement-anchor')
    await page.evaluate(() => { parentHost.open = true; parentHost.bindMenuListeners(); get('parent').remove() })
    await page.waitForFunction(() => broker().snapshot().sessions === 0)
    assert.deepEqual(await page.evaluate(() => [counts.key, counts.pointer]), [0, 0])
    await page.evaluate(() => parentHost.unbindMenuListeners())
    proofs.push('anchor replacement resolves the current DOM reference without stealing stale focus; disconnected panels retire sessions and listeners through the document observer')

    await page.evaluate(() => {
        document.body.style.setProperty('overflow', 'auto', 'important'); document.documentElement.style.setProperty('overflow', 'scroll', 'important')
        const input = document.createElement('input'); input.id = 'retained-focus'; document.body.append(input); input.focus()
        const dimmer = document.createElement('div'); dimmer.className = 'fff-overlay-backdrop'; dimmer.id = 'retained-dimmer'; document.body.append(dimmer)
        sheet.lock(); sheet.lock(); sheet.unlock()
    })
    assert.deepEqual(await page.evaluate(() => [counts.guards, document.body.style.overflow]), [1, 'hidden'])
    await page.evaluate(() => sheet.unlock())
    assert.deepEqual(await page.evaluate(() => [counts.guards, document.body.style.overflow, document.documentElement.style.overflow, document.activeElement.id, Boolean(get('retained-dimmer'))]), [0, 'auto', 'scroll', 'retained-focus', true])
    await page.evaluate(() => sheet.unlock())
    assert.deepEqual(await page.evaluate(() => [document.body.style.getPropertyPriority('overflow'), document.documentElement.style.getPropertyPriority('overflow'), document.body.style.overflow]), ['important', 'important', 'auto'])
    proofs.push('nested sheet locks retain guards until the final release, restore preexisting overflow and remove guard listeners without blurring another surface or deleting its backdrop')

    await page.evaluate(() => {
        const anchor = document.createElement('button'), panel = document.createElement('section'); document.body.append(anchor, panel)
        for (let i = 0; i < 1000; i++) {
            const release = broker().register({ anchor: () => anchor, panel: () => panel, current: () => true, dismiss() {} }); release(); release()
        }
        window.releaseBodyOwner = broker().register({ anchor: () => anchor, panel: () => panel, current: () => true, dismiss() {} })
        document.body.replaceWith(document.createElement('body'))
    })
    await page.waitForFunction(() => broker().snapshot().sessions === 0)
    assert.deepEqual(await page.evaluate(() => [counts.key, counts.pointer, broker().snapshot().observing]), [0, 0, false])
    await page.evaluate(() => {
        releaseBodyOwner()
        const anchor = document.createElement('button'), panel = document.createElement('section'); document.body.append(anchor, panel)
        window.newBodyRelease = broker().register({ anchor: () => anchor, panel: () => panel, current: () => true, dismiss() {} })
    })
    assert.equal(await page.evaluate(() => broker().snapshot().sessions), 1)
    await page.evaluate(() => newBodyRelease())
    proofs.push('1000 idempotent acquire/dispose cycles and real document.body replacement leave no listener/observer registrations; the same document service reconnects for a new body')

    assert.deepEqual(await page.evaluate(() => {
        const anchor = document.createElement('button'), panel = document.createElement('section'); document.body.append(anchor, panel)
        const releases = [], service = broker()
        let rejected = false
        for (let i = 0; i < 512; i++) releases.push(service.register({ anchor: () => anchor, panel: () => panel, current: () => true, dismiss() {} }))
        try { service.register({ anchor: () => anchor, panel: () => panel, current: () => true, dismiss() {} }) } catch { rejected = true }
        const size = service.snapshot().sessions
        releases.forEach(release => release())
        return [rejected, size, service.snapshot().sessions]
    }), [true, 512, 0])
    await page.evaluate(() => {
        const frame = document.createElement('iframe'); frame.id = 'frame'; document.body.append(frame)
        const doc = frame.contentDocument, anchor = doc.createElement('button'), panel = doc.createElement('section')
        anchor.id = 'frame-anchor'; doc.body.append(anchor, panel)
        window.frameClosed = 0
        window.frameRelease = brokerFor(doc).register({ anchor: () => anchor, panel: () => panel, current: () => true, dismiss() { frameClosed++ } })
        frame.contentWindow.dispatchEvent(new frame.contentWindow.KeyboardEvent('keydown', { key: 'Escape', cancelable: true }))
    })
    assert.equal(await page.evaluate(() => frameClosed), 1)
    assert.equal(await page.evaluate(() => broker().snapshot().sessions), 0)
    await page.evaluate(() => { frameRelease(); get('frame').remove() })
    proofs.push('bounded admission rejects the 513th owner without evicting active sessions; separate iframe documents arbitrate and dispose independently')

    await page.evaluate(() => {
        const anchor = document.createElement('button'), panel = document.createElement('section'); document.body.append(anchor, panel)
        window.failedCalls = 0
        window.failureRelease = broker().register({ anchor: () => anchor, panel: () => panel, current: () => true, dismiss() { failedCalls++; throw Error('controlled host failure') }, onError() { throw Error('controlled reporter failure') } })
    })
    await page.keyboard.press('Escape'); await page.keyboard.press('Escape')
    assert.equal(await page.evaluate(() => failedCalls), 2)
    await page.evaluate(() => failureRelease())
    assert.deepEqual(await page.evaluate(() => broker().snapshot()), { sessions: 0, listening: false, observing: false })
    proofs.push('throwing host dismissal/reporting does not leak registrations or poison retry admission; no unhandled browser error escapes the service')

    await page.evaluate(() => {
        document.body.innerHTML = '<button id="sheet-anchor">Sheet</button><section id="sheet-parent"><button id="sheet-child-anchor">Child</button><input id="parent-input"></section><section id="sheet-child"><input id="child-input"></section><input id="background-input">'
        window.makeSheetHost = (anchor, panel, id) => Object.assign(sheetEnvironment({ openKey: 'open' }), {
            open: true, resolveMenuOverlayId: () => id, resolveMenuTriggerRef: () => get(anchor), resolveMenuElement: () => get(panel), closeTeleportedMenu() { this.open = false; releaseEnvironment(this) },
        })
        window.parentSheet = makeSheetHost('sheet-anchor', 'sheet-parent', 'parent-sheet')
        window.childSheet = makeSheetHost('sheet-child-anchor', 'sheet-child', 'child-sheet')
        childSheet.syncOpenOverlaySheetEnvironment('sheet', null, { bindDismiss: false })
        parentSheet.syncOpenOverlaySheetEnvironment('sheet', null, { bindDismiss: false })
        parentSheet.syncOpenOverlaySheetEnvironment('sheet', null, { bindDismiss: false })
    })
    assert.deepEqual(await page.evaluate(() => [sheetOwners().snapshot(), document.documentElement.getAttribute('data-fff-overlay-sheet-locks'), counts.guards]), [{ owners: 2, observing: true }, '2', 1])
    assert.deepEqual(await page.evaluate(() => {
        const touch = id => { const event = new Event('touchmove', { bubbles: true, cancelable: true }); get(id).dispatchEvent(event); return event.defaultPrevented }
        const child = touch('child-input'), parent = touch('parent-input'), background = touch('background-input')
        const native = document.createElement('section'); native.setAttribute('aria-modal', 'true'); native.innerHTML = '<input id="native-input">'; document.body.append(native)
        const modal = touch('native-input'); native.remove()
        return [child, parent, background, modal]
    }), [false, true, true, false])
    proofs.push('actual shared sheet environment acquires one lease per owner despite repeated synchronization; nested touch input uses the innermost panel and preserves a native child modal scroll surface')

    await page.evaluate(() => { childSheet.open = false; releaseEnvironment(childSheet); releaseEnvironment(childSheet) })
    assert.deepEqual(await page.evaluate(() => [sheetOwners().snapshot().owners, document.documentElement.getAttribute('data-fff-overlay-sheet-locks'), counts.guards]), [1, '1', 1])
    await page.evaluate(() => { parentSheet.open = false; releaseEnvironment(parentSheet) })
    assert.deepEqual(await page.evaluate(() => [sheetOwners().snapshot(), counts.guards]), [{ owners: 0, observing: false }, 0])
    proofs.push('idempotent owner release cannot decrement another open sheet; final release retires owned observers and document scroll guards')

    await page.evaluate(() => {
        parentSheet.open = childSheet.open = true
        parentSheet.syncOpenOverlaySheetEnvironment('sheet', null, { bindDismiss: false })
        childSheet.syncOpenOverlaySheetEnvironment('sheet', null, { bindDismiss: false })
        document.body.append(get('sheet-child-anchor'))
        get('sheet-parent').remove()
    })
    await page.waitForFunction(() => sheetOwners().snapshot().owners === 1)
    assert.equal(await page.evaluate(() => document.documentElement.getAttribute('data-fff-overlay-sheet-locks')), '1')
    await page.evaluate(() => { releaseEnvironment(parentSheet); releaseEnvironment(childSheet) })
    assert.equal(await page.evaluate(() => counts.guards), 0)
    proofs.push('removing a parent panel retires its own scroll lease while a separately connected child retains its independent lock; late parent cleanup cannot unlock the child')

    await page.evaluate(() => {
        document.body.style.setProperty('overflow', 'auto', 'important')
        window.oldSheetBody = document.body
        const anchor = document.createElement('button'), panel = document.createElement('section'); document.body.append(anchor, panel)
        window.oldBodyLease = sheetLease({ document, anchor: () => anchor, panel: () => panel, current: () => true })
        const next = document.createElement('body'); next.style.setProperty('overflow', 'clip', 'important'); document.body.replaceWith(next)
    })
    await page.waitForFunction(() => sheetOwners().snapshot().owners === 0)
    assert.deepEqual(await page.evaluate(() => [oldSheetBody.style.overflow, oldSheetBody.style.getPropertyPriority('overflow'), document.body.style.overflow, document.body.style.getPropertyPriority('overflow'), counts.guards]), ['auto', 'important', 'clip', 'important', 0])
    await page.evaluate(() => oldBodyLease())
    proofs.push('real body replacement disposes detached scroll owners, restores the body originally locked and preserves independent styles on the replacement body')

    assert.deepEqual(await page.evaluate(() => {
        const anchor = document.createElement('button'), panel = document.createElement('section'); document.body.append(anchor, panel)
        const options = { document, anchor: () => anchor, panel: () => panel, current: () => true }
        for (let i = 0; i < 1000; i++) { const release = sheetLease(options); release(); release() }
        let invalid = false, bounded = false
        try { sheetLease({ ...options, current: () => false }) } catch { invalid = true }
        const releases = []
        for (let i = 0; i < 512; i++) releases.push(sheetLease(options))
        try { sheetLease(options) } catch { bounded = true }
        const admitted = sheetOwners().snapshot().owners
        releases.forEach(release => release())
        return [invalid, bounded, admitted, sheetOwners().snapshot(), counts.guards]
    }), [true, true, 512, { owners: 0, observing: false }, 0])
    proofs.push('1000 scroll lease cycles leave no guards or observers; invalid and over-budget admission never evicts another owner or creates an unowned lock')

    assert.deepEqual(await page.evaluate(() => {
        const anchor = document.createElement('button'), panel = document.createElement('section'); document.body.append(anchor, panel)
        const release = sheetLease({ document, anchor: () => anchor, panel: () => panel, current: () => true })
        const calls = [], host = { __fffSheetScrollLocked: true, __fffSheetScrollRelease: release,
            __fffSheetVisualViewportCleanup() { calls.push('viewport'); releaseEnvironment(host); throw Error('controlled viewport cleanup') },
            __fffSheetDismissCleanup() { calls.push('dismiss'); throw Error('controlled pointer cleanup') } }
        document.body.style.setProperty('overflow', 'visible', 'important')
        releaseEnvironment(host); releaseEnvironment(host)
        return [calls, sheetOwners().snapshot(), counts.guards, host.__fffSheetScrollLocked, document.body.style.overflow, document.body.style.getPropertyPriority('overflow')]
    }), [['viewport', 'dismiss'], { owners: 0, observing: false }, 0, false, 'visible', 'important'])
    proofs.push('throwing and reentrant environment cleanup still releases every captured resource exactly once; final unlock preserves a newer independent inline overflow policy')

    await page.evaluate(() => {
        window.retainedDisposers = []; window.ownerRefs = []
        const create = (autoRelease) => {
            const host = { anchor: document.createElement('button'), panel: document.createElement('section') }
            document.body.append(host.anchor, host.panel)
            const options = { anchor: () => host.anchor, panel: () => host.panel, current: () => true }
            const interaction = broker().register({ ...options, dismiss() {} })
            const scroll = sheetLease({ ...options, document })
            retainedDisposers.push(interaction, scroll); ownerRefs.push(new WeakRef(host))
            if (!autoRelease) { interaction(); scroll() }
            host.anchor.remove(); host.panel.remove()
        }
        create(false); create(true)
    })
    await page.waitForFunction(() => broker().snapshot().sessions === 0 && sheetOwners().snapshot().owners === 0)
    if (engine === 'chromium') for (let i = 0; i < 4; i++) await page.evaluate(() => { window.gc(); window.gc() })
    if (engine === 'chromium') assert.deepEqual(await page.evaluate(() => ownerRefs.map(reference => reference.deref() === undefined)), [true, true])
    await page.evaluate(() => retainedDisposers.forEach(release => release()))
    if (engine === 'chromium') proofs.push('actual forced V8 GC collects released and observer-retired host graphs even when their public disposer functions remain retained; late repeated disposal remains safe')

    assert.deepEqual(await page.evaluate(() => {
        const anchor = document.createElement('button'), panel = document.createElement('section'); document.body.append(anchor, panel)
        const releases = []
        for (let i = 0; i < 512; i++) releases.push(broker().register({ anchor: () => anchor, panel: () => panel, current: () => true, dismiss() {} }))
        const host = menuHost(anchor, panel), before = counts.resize
        let rejected = false
        try { host.bindMenuListeners() } catch { rejected = true }
        const clean = [rejected, counts.resize === before, host._menuListenerScope === null, broker().snapshot().sessions]
        releases.forEach(release => release()); host.unbindMenuListeners()
        return clean
    }), [true, true, true, 512])
    proofs.push('partial menu initialization at the session budget releases already-bound resize listeners and resets owner scope without disturbing admitted surfaces')
    assert.equal(await page.evaluate(() => morphHooks), 0)
    proofs.push('importing shared pickers and the compatibility facade does not install a global Livewire morph sweep')

    await page.evaluate(() => {
        const root = document.createElement('section'); root.id = 'owned-tree'; document.body.append(root)
        const make = (name, parent = root) => {
            const anchor = document.createElement('button'), panel = document.createElement('section')
            anchor.textContent = name; anchor.id = name + '-host'; parent.append(anchor)
            panel.id = name + '-panel'; panel.className = 'fff-teleported-menu'; panel.style.cssText = 'width:180px;height:100px'
            document.body.append(panel)
            return surface({ anchor, panel, presentation: 'panel', align: 'start' })
        }
        window.ownedParent = make('parent-owner')
        window.ownedChild = make('child-owner', ownedParent.resolveMenuElement())
        // Self markers cannot terminate the actual ancestor chain.
        ownedChild._pickerHostElement.dataset.fffDropdownOwner = ownedChild.resolveMenuOverlayId()
        window.ownedSibling = make('sibling-owner')
        const legacy = document.createElement('div'); legacy.id = 'unmanaged-panel'; legacy.className = 'fff-teleported-menu is-open'; document.body.append(legacy)
        ownedParent.menuOpen = true
    })
    await page.waitForFunction(() => ownedParent.menuReady && ownedParent.resolveMenuElement().classList.contains('is-open'))
    await page.evaluate(() => ownedChild.menuOpen = true)
    await page.waitForFunction(() => ownedChild.menuReady && ownedChild.resolveMenuElement().classList.contains('is-open'))
    assert.deepEqual(await page.evaluate(() => [ownedParent.menuOpen, ownedParent.resolveMenuElement().classList.contains('is-open'), get('unmanaged-panel').classList.contains('is-open')]), [true, true, true])
    proofs.push('actual nested imperative Select surfaces retain the ancestor and unrelated unmanaged markup; self owner markers do not break ancestry')
    assert.deepEqual(await page.evaluate(() => {
        ownedSibling.menuOpen = true
        return [ownedParent.menuOpen, ownedChild.menuOpen, ownedParent.resolveMenuElement().classList.contains('is-open'), ownedChild.resolveMenuElement().classList.contains('is-open')]
    }), [false, false, false, false])
    await page.waitForFunction(() => ownedSibling.menuReady)
    await page.evaluate(() => get('owned-tree').remove())
    await page.waitForFunction(() => connections().snapshot().owners === 0)
    assert.deepEqual(await page.evaluate(() => [ownedParent.__fffPickerDisposed, ownedChild.__fffPickerDisposed, ownedSibling.__fffPickerDisposed,
        connections().snapshot().observing, controllers().size(), broker().snapshot().sessions,
        Boolean(get('parent-owner-panel')), Boolean(get('child-owner-panel')), Boolean(get('sibling-owner-panel')), get('unmanaged-panel').classList.contains('is-open')]),
    [true, true, true, false, 0, 0, false, false, false, true])
    proofs.push('sibling arbitration hides owned panels synchronously; skipped framework destroy disposes scopes/controllers/portals and preserves unmanaged content')

    assert.deepEqual(await page.evaluate(async () => {
        const service = connections(), host = document.createElement('div'); document.body.append(host)
        let disconnected = 0
        const release = service.register(host, () => disconnected++)
        host.remove(); document.body.append(host)
        await new Promise(resolve => setTimeout(resolve, 0))
        const moved = [disconnected, service.snapshot().owners]
        release(); release(); host.remove()
        const broken = document.createElement('div'), healthy = document.createElement('div'); document.body.append(broken, healthy)
        service.register(broken, () => { throw Error('cleanup fault') })
        service.register(healthy, () => disconnected++)
        broken.remove(); healthy.remove()
        await new Promise(resolve => setTimeout(resolve, 0))
        return [...moved, disconnected, service.snapshot().owners, service.snapshot().observing]
    }), [0, 1, 1, 0, false])
    proofs.push('owner connectivity coalesces detach/reinsert moves, releases idempotently and isolates cleanup faults without retaining observers')

    assert.deepEqual(await page.evaluate(() => {
        const root = document.createElement('div'), panel = document.createElement('section'); document.body.append(root, panel)
        const results = []
        for (const failAt of [1, 2]) {
            let watches = 0, released = 0
            const owner = Object.assign(menuMixin(), { $el: root, $refs: { menuTrigger: root, menuMenu: panel },
                $watch() { if (++watches === failAt) throw Error('watch setup fault'); return () => released++ },
                $nextTick: callback => Promise.resolve().then(callback) })
            let rejected = false
            try { owner.bindSelectMenuLifecycle() } catch { rejected = true }
            results.push([rejected, owner.__fffPickerDisposed, connections().snapshot().owners, controllers().size(), released])
        }
        root.remove(); panel.remove()
        return results
    }), [[true, true, 0, 0, 0], [true, true, 0, 0, 1]])
    proofs.push('failed controller/state watcher initialization rolls back registered controllers, owner leases and acquired watchers before exposing a failed picker')

    assert.deepEqual(await page.evaluate(() => {
        const host = document.createElement('div'); document.body.append(host)
        const service = connections(), releases = []; let rejected = false
        for (let i = 0; i < 1000; i++) { const release = service.register(host, () => {}); release(); release() }
        for (let i = 0; i < 4096; i++) releases.push(service.register(host, () => {}))
        try { service.register(host, () => {}) } catch { rejected = true }
        const admitted = service.snapshot().owners
        releases.forEach(release => release()); host.remove()
        return [rejected, admitted, service.snapshot()]
    }), [true, 4096, { owners: 0, observing: false }])
    proofs.push('1000 lifetime release cycles and the explicit 4096-owner ceiling leave no observer; over-budget admission does not evict an existing owner')

    await page.evaluate(() => {
        window.connectivityRefs = []; window.connectivityDisposers = []
        const create = (automatic) => {
            const host = { node: document.createElement('div') }; document.body.append(host.node)
            const release = connections().register(host.node, () => { host.node.textContent = '' })
            connectivityDisposers.push(release); connectivityRefs.push(new WeakRef(host))
            if (!automatic) release()
            host.node.remove()
        }
        create(false); create(true)
    })
    await page.waitForFunction(() => connections().snapshot().owners === 0)
    if (engine === 'chromium') for (let i = 0; i < 4; i++) await page.evaluate(() => { window.gc(); window.gc() })
    if (engine === 'chromium') assert.deepEqual(await page.evaluate(() => connectivityRefs.map(reference => reference.deref() === undefined)), [true, true])
    await page.evaluate(() => connectivityDisposers.forEach(release => release()))
    if (engine === 'chromium') proofs.push('forced V8 GC collects explicitly released and observer-retired connectivity callbacks even while their public disposal handles remain retained')

    await page.evaluate(() => {
        const host = document.createElement('button'), panel = document.createElement('section'); document.body.append(host, panel)
        window.bodySurface = surface({ anchor: host, panel, presentation: 'sheet' }); bodySurface.menuOpen = true
    })
    await page.waitForFunction(() => bodySurface.menuReady)
    await page.evaluate(() => document.body.replaceWith(document.createElement('body')))
    await page.waitForFunction(() => bodySurface.__fffPickerDisposed)
    assert.deepEqual(await page.evaluate(() => [connections().snapshot().owners, controllers().size(), broker().snapshot().sessions, counts.guards]), [0, 0, 0, 0])
    await page.evaluate(() => {
        const host = document.createElement('div'); document.body.append(host)
        window.reconnectedOwnerRelease = connections().register(host, () => {})
    })
    assert.equal(await page.evaluate(() => connections().snapshot().observing), true)
    await page.evaluate(() => reconnectedOwnerRelease())
    proofs.push('body replacement disposes the actual sheet/controller and touch guards; the same document lifetime service reconnects for a new body')
    for (const reducedMotion of ['no-preference', 'reduce']) {
        await page.emulateMedia({ reducedMotion })
        await page.waitForFunction(reduced => matchMedia('(prefers-reduced-motion: reduce)').matches === reduced, reducedMotion === 'reduce')
        await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))))
        await page.setViewportSize({ width: 390, height: 844 })
        await page.evaluate(() => {
            const anchor = document.createElement('button'), panel = document.createElement('section')
            panel.innerHTML = '<input aria-label="Keyboard target">'; document.body.append(anchor, panel)
            window.viewportOwner = { anchor, panel, runtime: overlayRuntime() }
            viewportOwner.runtime.open({ id: 'viewport', anchor, panel, mode: 'sheet' })
            panel.querySelector('input').focus()
        })
        assert.equal(await page.evaluate(() => viewportOwner.panel.classList.contains('fff-overlay-reduced-motion')), reducedMotion === 'reduce')
        // Controlled visual-viewport keyboard geometry, not a physical IME claim.
        assert.deepEqual(await page.evaluate(() => {
            sheet.sync(viewportOwner.panel, { innerHeight: 844, visualViewport: { height: 420, offsetTop: 20 } })
            return [viewportOwner.panel.style.bottom, parseFloat(viewportOwner.panel.style.height) <= 420]
        }), ['404px', true])
        await page.setViewportSize({ width: 390, height: 420 })
        await page.evaluate(() => {
            viewportOwner.runtime.setMode('viewport', 'panel')
            window.dispatchEvent(new Event('scroll')); window.dispatchEvent(new Event('resize'))
            const replacement = document.createElement('button'); document.body.append(replacement)
            viewportOwner.runtime.retarget('viewport', { anchor: replacement })
            viewportOwner.anchor.remove()
            viewportOwner.runtime.setMode('viewport', 'sheet')
            viewportOwner.runtime.close('viewport'); viewportOwner.runtime.destroy()
            viewportOwner.panel.remove(); replacement.remove()
        })
        assert.deepEqual(await page.evaluate(() => [controllers().size(), connections().snapshot().owners]), [0, 0])
    }
    proofs.push('390px mobile viewport, focused input and controlled keyboard geometry in normal/reduced motion; panel-sheet transfers, scroll/resize and replaced anchor retire without retained owners')
    assert.deepEqual(errors, [])
    console.log(JSON.stringify({ engine, browser: browser.version(), physicalDevice: false, compiledSharedSource: true, groups: proofs.length, proofs, errors }))
} finally {
    await browser.close(); await new Promise(resolve => server.close(resolve))
}
