import { containedImageRect, thumbnailPose, morphKeyframes } from './geometry.js'
import { hideViewerOrigin } from './origin-presentation.js'
import { createEntryPhoto } from './entry-image.js'
import { createEntryChrome } from './entry-chrome.js'

/** Pending viewer intent: no form, focus trap, upload state or readiness bypass. */
export function startViewerEntry({ owner, root, fileId, ownerKey, name = '', position = 1, count = 1, onCancel = () => {}, hasInformation = false, document = globalThis.document }) {
    if (owner.disposed || !document?.body || globalThis.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return null
    const tile = [...(root?.querySelectorAll('[data-fff-upload-id]') ?? [])].find(node => node.getAttribute('data-fff-upload-id') === fileId)
    // A just-uploaded item's authoritative key is assigned on viewer intent.
    // Its painted preview already exists: entry must not wait for that binding.
    const previewSelector = 'img[data-fff-viewer-file-key], .fff-flex-file-upload__tile-image, .fff-flex-file-upload__file-art > img:not(.fff-flex-file-upload__file-icon), .fff-flex-file-upload__avatar-backdrop'
    const thumb = tile?.querySelector(previewSelector)
        ?? (!tile && !root?.querySelector('[data-fff-upload-id]') ? root?.querySelector(previewSelector) : null)
    if (!thumb?.complete || !thumb.naturalWidth) return null
    const rect = thumb.getBoundingClientRect(), viewport = document.documentElement
    if (rect.width <= 0 || rect.height <= 0 || rect.bottom <= 0 || rect.right <= 0 || rect.x >= viewport.clientWidth || rect.y >= viewport.clientHeight) return null
    const mobile = viewport.clientWidth < 768
    const width = viewport.clientWidth - (!mobile && hasInformation ? 352 : 0)
    const height = viewport.clientHeight * (mobile && hasInformation ? .62 : 1)
    const target = containedImageRect({ x: mobile ? 16 : 72, y: mobile ? 80 : 88,
        width: width - (mobile ? 32 : 144), height: height - (mobile ? 164 : 188) }, thumb.naturalWidth, thumb.naturalHeight)
    if (!target) return null
    const surface = document.createElement('div'), background = document.createElement('div')
    const photo = createEntryPhoto(document, thumb, target)
    if (typeof photo.animate !== 'function' || typeof surface.animate !== 'function') return null
    surface.className = 'fff-upload-viewer-entry'
    surface.setAttribute('data-fff-viewer-entry', ownerKey)
    background.setAttribute('aria-hidden', 'true'); photo.setAttribute('aria-hidden', 'true')
    background.className = 'fff-upload-viewer-entry__backdrop'
    Object.assign(photo.style, { width: `${target.width}px`, height: `${target.height}px` })
    surface.appendChild(background); surface.appendChild(photo); document.body.appendChild(surface)
    const style = getComputedStyle(thumb), radiusText = parseFloat(style.borderRadius) > 0 ? style.borderRadius : getComputedStyle(thumb.parentElement).borderRadius
    const radius = (parseFloat(radiusText) || 0) * (radiusText.includes('%') ? Math.min(rect.width, rect.height) / 100 : 1)
    const restoreOrigin = hideViewerOrigin(thumb)
    const animations = [photo.animate(morphKeyframes(thumbnailPose(rect, target, radius), { ...target, scale: 1 }), { duration: 520, fill: 'both', easing: 'linear' }),
        background.animate([{ opacity: 0 }, { opacity: 1 }], { duration: 300, fill: 'both', easing: 'ease-out' })]
    for (const animation of animations) animation.finished.catch(() => {})
    let consumed = false
    let cancelled = false
    let transferred = false
    let observer = null
    let releaseChrome = () => {}
    let closeViewer = null
    const cancel = () => {
        if (consumed && closeViewer) { closeViewer(); return }
        if (cancelled || consumed || owner.disposed) return
        cancelled = true; onCancel()
        releaseChrome.hide?.()
        // Modal preparation can move the grid or change its scrollbar gutter.
        // Return the existing photo to the live thumbnail, not the stale origin
        // captured before the server prepared the action.
        const painted = getComputedStyle(photo)
        const transform = painted.transform, clipPath = painted.clipPath
        const liveRoot = root?.isConnected === false
            ? [...document.querySelectorAll('[data-fff-viewer-owner]')].find(node => node.getAttribute('data-fff-viewer-owner') === ownerKey)
            : root
        const liveTile = [...liveRoot?.querySelectorAll('[data-fff-upload-id]') ?? []].find(node => node.getAttribute('data-fff-upload-id') === fileId)
        const liveThumb = liveTile?.querySelector(previewSelector) ?? thumb
        const destination = liveThumb.getBoundingClientRect()
        const duration = Math.max(120, Math.min(420, Number(animations[0].currentTime) || 420))
        animations[0].cancel()
        const visible = liveThumb.isConnected !== false && [destination.x, destination.y, destination.width, destination.height].every(Number.isFinite)
            && destination.width > 0 && destination.height > 0
        const end = visible ? morphKeyframes({ ...target, scale: 1 }, thumbnailPose(destination, target, radius)).at(-1) : { transform, clipPath, opacity: 0 }
        animations[0] = photo.animate([{ transform, clipPath, opacity: 1 }, end],
        { duration, fill: 'both', easing: 'cubic-bezier(.22,1,.36,1)' })
        animations[0].finished.catch(() => {})
        animations[1].reverse()
        Promise.all(animations.map(animation => animation.finished.catch(() => {}))).then(() => {
            if (!consumed) release()
        })
    }
    const cleanup = () => {
        releaseChrome()
        for (const animation of animations) animation.cancel()
        restoreOrigin()
        surface.remove(); delete surface.takeViewerEntry
        if (photo.tagName === 'CANVAS') { photo.width = 0; photo.height = 0 }
    }
    const release = owner.defer(() => {
        observer?.disconnect()
        if (!transferred) cleanup()
    })
    releaseChrome = createEntryChrome({ owner, root, surface, name, position, count, width, height, cancel })
    const ready = animations[0].finished.catch(() => {}).then(() => !cancelled && !owner.disposed)
    surface.takeViewerEntry = destination => {
        if (consumed || owner.disposed) return null
        consumed = true
        const current = getComputedStyle(photo)
        const pose = { width: target.width, height: target.height, transform: current.transform, clipPath: current.clipPath,
            background: getComputedStyle(background).backgroundColor }
        if (destination) {
            // Transfer ownership, not the live compositing layer. Reparenting an
            // animated image can invalidate its raster/layer during the spring.
            transferred = true
            Object.assign(pose, { photo, animation: animations[0], backdrop: background, target, release: cleanup,
                adoptSurface() { destination.appendChild(surface) },
                handoffChrome: () => releaseChrome.handoff?.(), informationReady: () => releaseChrome.informationReady?.(),
                hideChrome: () => releaseChrome.hide?.(), closeUsing(callback) { closeViewer = callback } })
        }
        release()
        return pose
    }
    return { release, ready, get cancelled() { return cancelled }, get consumed() { return consumed }, mounted() {
        if (consumed || owner.disposed) return
        const present = () => [...document.querySelectorAll('[data-fff-viewer-field]')].some(node => node.getAttribute('data-fff-viewer-field') === ownerKey)
        if (!present()) { release(); return }
        observer = new MutationObserver(() => { if (!present()) release() })
        observer.observe(document.body, { childList: true, subtree: true })
    } }
}

export function takeViewerEntry(ownerKey, document = globalThis.document, destination = null) {
    return [...(document.querySelectorAll('[data-fff-viewer-entry]') ?? [])]
        .find(node => node.getAttribute('data-fff-viewer-entry') === ownerKey)?.takeViewerEntry?.(destination) ?? null
}
