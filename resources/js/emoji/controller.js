import { waitForEmojiContent } from './content-readiness.js'
import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { createImperativeMenuSurface } from '../overlay/menu/imperative-surface.js'
import { createEmojiContentCache, normalizeEmojiLocale } from './content-cache.js'
import { createEmojiSurfaceContent, setEmojiPresentation } from './presentation.js'

/** One active surface, stable DOM identity, generation-owned asynchronous work. */
export function createEmojiPickerController({
    cache = createEmojiContentCache(),
    createSurface = createImperativeMenuSurface,
    createContent = createEmojiSurfaceContent,
    present = setEmojiPresentation,
} = {}) {
    let view = null
    let active = null
    let generation = 0
    const owns = context => Boolean(active && active.owner === context?.$el)
    const retire = session => {
        if (active !== session) return
        active = null
        generation++
        session.scope.dispose()
        view.panel.hidden = true
        view.content.replaceChildren()
        session.onClose?.()
        queueMicrotask(() => session.surface.dispose())
    }
    const current = session => active === session && generation === session.generation && session.anchor.isConnected
        && session.owner?.isConnected !== false && !session.scope.disposed
    const service = {
        isOpenFor: owns,
        isOpenForAnchor: anchor => active?.anchor === anchor,
        isActive: () => Boolean(active?.surface.menuOpen),
        isReady: locale => cache.ready(normalizeEmojiLocale(locale)),
        async open({ context, anchor, locale, stylesHref, inputStylesHref, onSelect, onClose, onReadyChange, onError,
            width = 304, position = 'auto', align = 'end', closeOnSelect = true, getPrimaryColor, retryLabel = 'Retry' }) {
            if (!anchor?.isConnected || !context?.$el) {
                onClose?.()
                return
            }
            if (active) {
                const old = active.surface
                old.closeTeleportedMenuImmediate('replaced')
                old.dispose()
            }
            if (!view?.panel.isConnected) {
                view = createContent(document)
                document.body.append(view.panel)
            }
            const session = { owner: context?.$el, anchor, locale: normalizeEmojiLocale(locale), generation: ++generation,
                scope: createResourceScope(), onClose, surface: null, picker: null, getPrimaryColor }
            active = session
            const positionAnchor = anchor.closest?.('.fff-flex-text-input__shell') ?? anchor.closest?.('.fff-flex-textarea__shell') ?? anchor
            session.surface = createSurface({ anchor:positionAnchor, ownershipAnchor:anchor, controllerOwner:context.$el,
                panel: view.panel, width, position, align, onClose: () => retire(session) })
            view.panel.hidden = false
            view.content.replaceChildren()
            view.loading.hidden = false
            view.content.setAttribute('aria-busy', 'true')
            onReadyChange?.(false)
            session.surface.menuOpen = true
            let releaseRetry = null
            const load = async () => {
                if (!current(session)) return
                releaseRetry?.()
                releaseRetry = null
                view.loading.hidden = false
                view.content.replaceChildren()
                view.content.setAttribute('aria-busy', 'true')
                try {
                    const entry = await cache.acquire(session.locale, [inputStylesHref, stylesHref].filter(Boolean), session.picker)
                    if (!current(session)) return
                    session.picker = entry.picker
                    present(entry.picker, anchor, getPrimaryColor)
                    view.content.replaceChildren(entry.picker)
                    // The opaque loading surface hides native markup until both
                    // database and the real external shadow stylesheet are ready.
                    await Promise.all([entry.databaseReady, entry.stylesReady])
                    if (!current(session)) return
                    const contentAbort = new AbortController()
                    const releaseContentWait = session.scope.defer(() => contentAbort.abort())
                    try { await waitForEmojiContent(entry.picker, contentAbort.signal) }
                    finally { releaseContentWait() }
                    if (!current(session)) return
                    entry.ready = true
                    view.loading.hidden = true
                    view.content.setAttribute('aria-busy', 'false')
                    onReadyChange?.(true)
                    session.scope.listen(entry.picker, 'emoji-click', event => {
                        if (!current(session) || session.surface.__fffPanelClosing || session.surface.__fffSheetClosing) return
                        onSelect?.(event.detail.unicode)
                        if (closeOnSelect) service.close()
                    })
                    session.surface.updateMenuPosition({ markReady: session.surface.menuReady })
                } catch (error) {
                    if (!current(session)) return
                    cache.forget(session.locale)
                    onError?.(error)
                    view.loading.hidden = true
                    const retry = document.createElement('button')
                    retry.type = 'button'
                    retry.className = 'fff-emoji-picker__retry'
                    retry.textContent = retryLabel
                    releaseRetry = session.scope.listen(retry, 'click', load)
                    view.content.replaceChildren(retry)
                    view.content.setAttribute('aria-busy', 'false')
                }
            }
            const themeObserver = new MutationObserver(() => service.syncTheme())
            themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] })
            session.scope.defer(() => themeObserver.disconnect())
            await load()
        },
        close() { active?.surface.closeTeleportedMenu() },
        disposeFor(context) { if (owns(context)) active.surface.closeTeleportedMenuImmediate('owner-disposed') },
        syncTheme() { if (active?.picker) present(active.picker, active.anchor, active.getPrimaryColor) },
    }
    return service
}
