import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { adaptEmojiContent } from './content-adapter.js'
import { renderPickerIcons } from './icons.js'
import { prepareShadowStyles, disposeShadowStyles } from './shadow-styles.js'

export const normalizeEmojiLocale = locale => String(locale || navigator.language || 'en').split('-')[0].toLowerCase()

/** Bounded detached content cache; open-session effects belong to the controller. */
export function createEmojiContentCache({ limit = 3, importPicker = () => import('emoji-picker-element') } = {}) {
    const entries = new Map()
    let library = null

    return {
        async acquire(locale, stylesHref, activePicker) {
            library ??= importPicker().catch(error => {
                library = null
                throw error
            })
            await library
            let entry = entries.get(locale)
            if (!entry) {
                const picker = document.createElement('emoji-picker')
                picker.className = 'fff-emoji-picker__element'
                picker.setAttribute('locale', locale)
                const scope = createResourceScope()
                const observer = new MutationObserver(() => { renderPickerIcons(picker.shadowRoot); adaptEmojiContent(picker) })
                observer.observe(picker.shadowRoot, { childList: true, subtree: true, attributes: true,
                    attributeFilter: ['aria-expanded', 'aria-selected'] })
                scope.defer(() => {
                    observer.disconnect()
                    disposeShadowStyles(picker)
                    picker.remove()
                    picker.database.close?.()
                })
                entry = { picker, scope, databaseReady: null, ready: false, stylesReady: null, stylesHref: null }
                entries.set(locale, entry)
            }

            entry.databaseReady = entry.picker.database.ready()
            entry.databaseReady.catch(() => {})
            entries.delete(locale)
            entries.set(locale, entry)
            for (const [key, cached] of entries) {
                if (entries.size <= limit) break
                if (cached !== entry && cached.picker !== activePicker) {
                    entries.delete(key)
                    cached.scope.dispose()
                }
            }
            const styleKey = JSON.stringify(stylesHref)
            if (entry.stylesHref !== styleKey) entry.ready = false
            entry.stylesHref = styleKey
            entry.stylesReady = prepareShadowStyles(entry.picker, stylesHref)
            entry.stylesReady.catch(() => {})
            return entry
        },
        ready(locale) { return entries.get(locale)?.ready ?? false },
        forget(locale) {
            const entry = entries.get(locale)
            entries.delete(locale)
            entry?.scope.dispose()
        },
    }
}
