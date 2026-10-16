import { containedImageRect, thumbnailPose, morphKeyframes } from './geometry.js'
import { takeViewerEntry } from './entry-preview.js'
import { hideViewerOrigin } from './origin-presentation.js'
import { waitForViewerSurface } from './surface-readiness.js'

/** One gallery owns all transition clones/animations. Original files are never rasterized. */
export function createSharedElementMotion({ owner, surface, image, ownerKey, selected, onClose = () => {}, document = globalThis.document }) {
    const animations = new Set(), clones = new Set(), origins = new Map(), entries = new Set(), paintFrames = new Set()
    let entered = false, previous = null, epoch = 0, openingEntry = null, opening = null, prepared = false, chromeEntrance = []
    const reduced = () => globalThis.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false
    const backdrop = () => globalThis.getComputedStyle?.(surface).getPropertyValue?.('--fff-viewer-backdrop')?.trim() || '#f5f5f7'
    const cancelAnimations = () => { for (const animation of animations) animation.cancel(); animations.clear() }
    const removeClones = () => {
        for (const release of entries) release()
        entries.clear()
        for (const clone of clones) clone.remove()
        clones.clear(); previous = null; openingEntry = null
    }
    const restoreOrigins = () => { for (const restore of origins.values()) restore(); origins.clear() }
    const hideOrigin = node => { if (!origins.has(node)) origins.set(node, hideViewerOrigin(node)) }
    owner.defer(() => {
        takeViewerEntry(ownerKey, document)
        for (const frame of paintFrames) cancelAnimationFrame(frame)
        paintFrames.clear()
        cancelAnimations(); removeClones(); restoreOrigins()
        surface.classList.remove('is-viewer-ready')
        surface.classList.remove('has-entry-chrome', 'has-entry-information')
        image.style.removeProperty('visibility')
    })

    function origin() {
        const field = [...document.querySelectorAll('[data-fff-viewer-owner]')]
            .find(node => node.getAttribute('data-fff-viewer-owner') === ownerKey && node.isConnected)
        const thumb = [...(field?.querySelectorAll('img[data-fff-viewer-file-key]') ?? [])]
            .find(node => node.getAttribute('data-fff-viewer-file-key') === String(selected()))
        if (!thumb?.complete || !thumb.naturalWidth) return null
        const rect = thumb.getBoundingClientRect()
        const viewport = document.documentElement
        if (rect.width <= 0 || rect.height <= 0 || rect.right <= 0 || rect.bottom <= 0 || rect.x >= viewport.clientWidth || rect.y >= viewport.clientHeight) return null
        const ownRadius = getComputedStyle(thumb).borderRadius
        const value = parseFloat(ownRadius) > 0 ? ownRadius : getComputedStyle(thumb.parentElement).borderRadius
        const numeric = parseFloat(value) || 0
        const radius = value.includes('%') ? Math.min(rect.width, rect.height) * numeric / 100 : numeric
        return { rect, radius, thumb }
    }

    function photoRect() { return containedImageRect(image.getBoundingClientRect(), image.naturalWidth, image.naturalHeight) }
    function clonePhoto(rect) {
        const clone = document.createElement('img')
        clone.src = image.currentSrc || image.src
        clone.alt = ''; clone.setAttribute('aria-hidden', 'true')
        clone.className = 'fff-upload-viewer__transition-photo'
        Object.assign(clone.style, { width: `${rect.width}px`, height: `${rect.height}px` })
        surface.appendChild(clone); clones.add(clone)
        return clone
    }
    function animate(element, frames, options) {
        const animation = element.animate(frames, { fill: 'both', ...options })
        // Chrome/metadata animations are intentionally not awaited individually.
        // Cancelling their owner must not create unhandled promise rejections.
        animation.finished.catch(() => {})
        animations.add(animation)
        return animation
    }
    const settled = animation => animation.finished.catch(() => {})
    const chrome = () => surface.querySelectorAll('.fff-upload-viewer__chrome,.fff-upload-lightbox-information')
    function fadeChrome(open, commonAlreadyVisible = false) {
        const result = [...chrome()].filter(element => !open || !commonAlreadyVisible || !element.matches('.fff-upload-viewer__chrome')).map(element => animate(element,
            [{ opacity: open ? 0 : 1, transform: open ? 'translate3d(0,12px,0)' : 'none' },
                { opacity: open ? 1 : 0, transform: open ? 'none' : 'translate3d(0,8px,0)' }],
            { duration: open ? 340 : 180, easing: 'cubic-bezier(.22,1,.36,1)' }))
        if (open && typeof requestAnimationFrame === 'function') {
            // Prepare the first composited pose before starting its clock. A busy
            // readiness turn must not consume half the entrance offscreen.
            for (const animation of result) animation.pause()
            const frame = requestAnimationFrame(() => {
                paintFrames.delete(frame)
                if (owner.disposed) return
                for (const animation of result) if (animation.playState !== 'idle') animation.play()
            })
            paintFrames.add(frame)
        }
        return result
    }

    return {
        hasPreview() { return openingEntry !== null },
        prepareOpening() {
            if (opening) return opening
            if (owner.disposed) return Promise.resolve(false)
            prepared = true
            surface.classList.add('is-viewer-ready')
            if (reduced()) return Promise.resolve(false)
            const entry = takeViewerEntry(ownerKey, document, surface)
            if (!entry?.photo) {
                const version = epoch
                opening = waitForViewerSurface(owner, surface).then(() => {
                    if (owner.disposed || version !== epoch) return false
                    chromeEntrance = fadeChrome(true)
                    return Promise.all(chromeEntrance.map(settled)).then(() => false)
                })
                return opening
            }
            openingEntry = entry; entries.add(entry.release); animations.add(entry.animation)
            entry.closeUsing?.(onClose)
            surface.classList.add('has-entry-chrome')
            image.style.visibility = 'hidden'
            // Keep the same preview layer alive through the spring and subsequent
            // original download/decode. The ready form never covers it with paint.
            animate(surface, [{ backgroundColor: 'transparent' }, { backgroundColor: 'transparent' }], { duration: 1 })
            const version = epoch
            opening = Promise.all([settled(entry.animation), waitForViewerSurface(owner, surface)])
                .then(() => {
                    if (owner.disposed || version !== epoch || openingEntry !== entry) return false
                    // The spring is settled and the native form is ready. Adopt
                    // its backdrop/photo below the form's local content layer:
                    // leaving the body-level entry above the native modal would
                    // conceal every chrome/form animation until image decode.
                    entry.adoptSurface?.()
                    surface.classList.add('has-entry-information')
                    // Swap identically sized chrome in one paint, only when the
                    // entire configured form is ready. Its entrance runs once.
                    surface.classList.remove('has-entry-chrome')
                    entry.handoffChrome?.()
                    chromeEntrance = fadeChrome(true, true)
                    return Promise.all(chromeEntrance.map(settled)).then(() => {
                        if (owner.disposed || version !== epoch || openingEntry !== entry) return false
                        entry.informationReady?.()
                        surface.classList.remove('has-entry-information')
                        return true
                    })
                })
            return opening
        },
        prepareNavigation() {
            epoch++
            if (owner.disposed || reduced()) return
            cancelAnimations(); removeClones(); restoreOrigins()
            const rect = photoRect()
            if (!rect) return
            previous = clonePhoto(rect)
            previous.style.transform = `translate3d(${rect.x}px,${rect.y}px,0)`
            image.style.visibility = 'hidden'
            animate(previous, [{ opacity: 1 }, { opacity: .82 }], { duration: 180, easing: 'ease-out' })
        },
        async open(direction = 0) {
            const version = ++epoch
            if (owner.disposed) return
            if (reduced()) { surface.classList.add('is-viewer-ready'); entered = true; image.style.visibility = ''; return }
            if (entered) {
                const outgoing = previous
                image.style.visibility = ''
                const incoming = animate(image, [{ opacity: 0, translate: `${direction * 64}px 0` }, { opacity: 1, translate: '0 0' }], { duration: 320, easing: 'cubic-bezier(.22,1,.36,1)' })
                if (outgoing) animate(outgoing, [{ opacity: 1, translate: '0 0' }, { opacity: 0, translate: `${-direction * 64}px 0` }], { duration: 320, easing: 'cubic-bezier(.22,1,.36,1)' })
                await settled(incoming)
                if (!owner.disposed && version === epoch) { cancelAnimations(); removeClones() }
                return
            }
            if (openingEntry) {
                const entry = openingEntry, target = photoRect()
                entered = true
                if (target && Math.abs(target.width / target.height - entry.width / entry.height) < .01
                    && ['x', 'y', 'width', 'height'].some(key => Math.abs(target[key] - entry.target[key]) > 1)) {
                    await settled(animate(entry.photo, [
                        { transform: `translate3d(${entry.target.x}px,${entry.target.y}px,0) scale(1)` },
                        { transform: `translate3d(${target.x}px,${target.y}px,0) scale(${target.width / entry.width})` },
                    ], { duration: 120, easing: 'cubic-bezier(.22,1,.36,1)' }))
                }
                if (owner.disposed || version !== epoch) return
                image.style.visibility = ''
                // Decode has already completed. Upgrade pixels without replaying
                // the entrance or replacing its live compositing layer mid-spring.
                await settled(animate(image, [{ opacity: 0 }, { opacity: 1 }], { duration: 160, easing: 'ease-out' }))
                await Promise.all(chromeEntrance.map(settled))
                if (!owner.disposed && version === epoch) { cancelAnimations(); removeClones(); restoreOrigins() }
                return
            }
            entered = true
            cancelAnimations(); removeClones(); restoreOrigins()
            const target = photoRect(), entry = takeViewerEntry(ownerKey, document, surface), source = origin()
            if (entry?.release) entries.add(entry.release)
            surface.classList.add('is-viewer-ready')
            // The early image remains below the authoritative modal. Keep its
            // canvas unobscured until the spring finishes; chrome may enter above.
            const entryBackdrop = entry?.photo ? animate(surface,
                [{ backgroundColor: 'transparent' }, { backgroundColor: 'transparent' }], { duration: 1 }) : null
            if (!entryBackdrop) animate(surface, [{ backgroundColor: entry?.background || (entry || prepared ? backdrop() : 'transparent') }, { backgroundColor: backdrop() }], { duration: 360, easing: 'ease-out' })
            if (!prepared) fadeChrome(true)
            if (target && entry?.photo && Math.abs(target.width / target.height - entry.width / entry.height) < .01) {
                animations.add(entry.animation)
                // The adopted entry already owns the origin's hidden state.
                // A second lease would restore that hidden state after cleanup.
                image.style.visibility = 'hidden'
                await settled(entry.animation)
                if (!owner.disposed && version === epoch && ['x', 'y', 'width', 'height'].some(key => Math.abs(target[key] - entry.target[key]) > 1)) {
                    await settled(animate(entry.photo, [
                        { transform: `translate3d(${entry.target.x}px,${entry.target.y}px,0) scale(1)` },
                        { transform: `translate3d(${target.x}px,${target.y}px,0) scale(${target.width / entry.width})` },
                    ], { duration: 120, easing: 'cubic-bezier(.22,1,.36,1)' }))
                }
            } else if (target && entry && Math.abs(target.width / target.height - entry.width / entry.height) < .01) {
                const clone = clonePhoto(entry)
                if (source) hideOrigin(source.thumb)
                image.style.visibility = 'hidden'
                await settled(animate(clone, [
                    { transform: entry.transform, clipPath: entry.clipPath },
                    { transform: `translate3d(${target.x}px,${target.y}px,0) scale(${target.width / entry.width})`, clipPath: 'inset(0% 0% round 0px)' },
                ], { duration: 240, easing: 'cubic-bezier(.22,1,.36,1)' }))
            } else if (target && source && !entry) {
                const clone = clonePhoto(target)
                hideOrigin(source.thumb)
                image.style.visibility = 'hidden'
                const animation = animate(clone, morphKeyframes(thumbnailPose(source.rect, target, source.radius), { ...target, scale: 1 }), { duration: 520, easing: 'linear' })
                await settled(animation)
            } else {
                if (entry?.photo) { entry.release(); entries.delete(entry.release); entryBackdrop?.cancel() }
                image.style.visibility = ''
                await settled(animate(image, [{ opacity: 0, scale: .97 }, { opacity: 1, scale: 1 }], { duration: 320, easing: 'cubic-bezier(.22,1,.36,1)' }))
            }
            if (!owner.disposed && version === epoch) { image.style.visibility = ''; cancelAnimations(); removeClones(); restoreOrigins() }
        },
        reveal() { epoch++; takeViewerEntry(ownerKey, document); surface.classList.remove('has-entry-chrome', 'has-entry-information'); surface.classList.add('is-viewer-ready'); image.style.visibility = ''; cancelAnimations(); removeClones(); restoreOrigins() },
        async close() {
            const version = ++epoch
            const entry = openingEntry
            if (entry && !entered && !owner.disposed && !reduced()) {
                entry.hideChrome?.()
                const painted = getComputedStyle(entry.photo)
                const transform = painted.transform, clipPath = painted.clipPath
                const destination = origin()
                cancelAnimations()
                fadeChrome(false)
                animate(surface, [{ backgroundColor: 'transparent' }, { backgroundColor: 'transparent' }], { duration: 1 })
                const fading = animate(entry.backdrop, [{ opacity: 1 }, { opacity: 0 }], { duration: 420, easing: 'ease-in-out' })
                if (destination) {
                    const end = morphKeyframes({ ...entry.target, scale: 1 }, thumbnailPose(destination.rect, entry, destination.radius)).at(-1)
                    await settled(animate(entry.photo, [{ transform, clipPath }, end], { duration: 420, easing: 'cubic-bezier(.22,1,.36,1)' }))
                } else {
                    animate(entry.photo, [{ opacity: 1 }, { opacity: 0 }], { duration: 180 })
                    await settled(fading)
                }
                if (!owner.disposed && version === epoch) { removeClones(); restoreOrigins() }
                return
            }
            cancelAnimations(); removeClones(); restoreOrigins()
            if (owner.disposed || reduced()) return
            const current = photoRect(), destination = origin()
            fadeChrome(false)
            const fading = animate(surface, [{ backgroundColor: backdrop() }, { backgroundColor: 'transparent' }], { duration: 420, easing: 'ease-in-out' })
            if (current && destination) {
                const clone = clonePhoto(current)
                // The stored preview may use a cropped conversion. Finish on its
                // exact painted pixels, not a guessed centre crop of the original.
                // Both images are already decoded; no download joins the exit.
                const previewRatio = destination.thumb.naturalWidth / destination.thumb.naturalHeight
                if (Number.isFinite(previewRatio) && Math.abs(previewRatio - image.naturalWidth / image.naturalHeight) > .01) {
                    const thumbnail = document.createElement('img')
                    thumbnail.src = destination.thumb.currentSrc || destination.thumb.src
                    thumbnail.alt = ''; thumbnail.setAttribute('aria-hidden', 'true')
                    thumbnail.className = 'fff-upload-viewer__transition-photo'
                    Object.assign(thumbnail.style, {
                        width: `${destination.rect.width}px`, height: `${destination.rect.height}px`,
                        transform: `translate3d(${destination.rect.x}px,${destination.rect.y}px,0)`,
                        objectFit: getComputedStyle(destination.thumb).objectFit,
                        objectPosition: getComputedStyle(destination.thumb).objectPosition,
                        borderRadius: `${destination.radius}px`,
                    })
                    surface.appendChild(thumbnail); clones.add(thumbnail)
                    animate(thumbnail, [{ opacity: 0, offset: 0 }, { opacity: 0, offset: .72 }, { opacity: 1, offset: 1 }], { duration: 540, easing: 'linear' })
                    animate(clone, [{ opacity: 1, offset: 0 }, { opacity: 1, offset: .72 }, { opacity: 0, offset: 1 }], { duration: 540, easing: 'linear' })
                }
                hideOrigin(destination.thumb)
                image.style.visibility = 'hidden'
                await settled(animate(clone, morphKeyframes({ ...current, scale: 1 }, thumbnailPose(destination.rect, current, destination.radius), { duration: 540, damping: .82 }), { duration: 540, easing: 'linear' }))
            } else {
                animate(image, [{ opacity: 1 }, { opacity: 0 }], { duration: 180 })
                await settled(fading)
            }
            // Hold the transparent backdrop and hidden chrome until authoritative
            // unmount. Cancelling their filled animations here restores the opaque
            // CSS background while the request is pending and flashes the page.
            if (!owner.disposed && version === epoch) { removeClones(); restoreOrigins() }
        },
    }
}
