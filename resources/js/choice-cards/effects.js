/** Image listeners and transient ripples belong to their rendered instance. */
export function bindCardImages(root, scope) {
    const view = root.ownerDocument.defaultView
    const images = [...root.querySelectorAll('.fff-image-choice-cards__image')]
    for (const image of images) {
        const loaded = () => image.classList.add('is-loaded')
        if (image.complete && image.naturalWidth > 0) loaded()
        else {
            scope.listen(image, 'load', loaded, { once: true })
            scope.listen(image, 'error', loaded, { once: true })
        }
    }
    if (!view.IntersectionObserver) return
    const observer = new view.IntersectionObserver(entries => {
        for (const { target, isIntersecting } of entries) {
            if (!isIntersecting || scope.disposed) continue
            target.loading = 'eager'
            observer.unobserve(target)
        }
    }, { rootMargin: '1200px 0px', threshold: 0.01 })
    scope.trackObserver(observer)
    for (const image of images) if (image.loading === 'lazy') observer.observe(image)
}

export function cardRipple(root, card, event, scope) {
    const view = root.ownerDocument.defaultView
    if (event.pointerType === 'touch' || view.matchMedia('(pointer: coarse), (prefers-reduced-motion: reduce)').matches) return
    const rect = card.getBoundingClientRect(), diameter = Math.max(rect.width, rect.height)
    const circle = root.ownerDocument.createElement('span')
    circle.className = root.classList.contains('fff-image-choice-cards') ? 'fff-image-choice-cards__ripple' : 'fff-choice-cards__ripple'
    circle.dataset.cardEffect = ''
    Object.assign(circle.style, {width:`${diameter}px`,height:`${diameter}px`,left:`${event.clientX - rect.left - diameter / 2}px`,top:`${event.clientY - rect.top - diameter / 2}px`})
    card.appendChild(circle)
    const remove = scope.defer(() => circle.remove())
    let stopListener, stopTimer
    const finish = () => { remove(); stopListener?.(); stopTimer?.() }
    stopListener = scope.listen(circle, 'animationend', finish, { once: true })
    stopTimer = scope.timeout(finish, 650)
}
