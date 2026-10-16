import { adaptEmojiContent } from './content-adapter.js'
import { resolveIsDark } from '../core/theme-utils.js'
export function setEmojiPresentation(picker, anchor, getPrimaryColor) {
    const dark = resolveIsDark()
    picker.classList.toggle('dark', dark); picker.classList.toggle('light', !dark)
    adaptEmojiContent(picker)
    picker.dir = getComputedStyle(anchor).direction
    const primary = typeof getPrimaryColor === 'function' ? getPrimaryColor() : getPrimaryColor
    if (primary) picker.style.setProperty('--fff-emoji-primary', primary)
    else picker.style.removeProperty('--fff-emoji-primary')
}
export function createEmojiSurfaceContent(document) {
    const panel = document.createElement('div')
    panel.className = 'fff-teleported-menu fff-emoji-picker__panel'
    panel.hidden = true; panel.dataset.fffEmojiPickerPanel = ''
    const loading = document.createElement('div')
    loading.className = 'fff-emoji-picker__loading'; loading.setAttribute('aria-hidden', 'true')
    const content = document.createElement('div')
    content.className = 'fff-emoji-picker__host fff-teleported-menu__scroll'
    content.dataset.fffOverlayScroll = 'true'
    panel.append(loading, content)
    return { panel, loading, content }
}
