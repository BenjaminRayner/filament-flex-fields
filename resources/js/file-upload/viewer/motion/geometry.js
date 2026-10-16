/** The painted image bounds, rather than its object-fit element's empty margins. */
export function containedImageRect(rect, width, height) {
    if (![rect.x, rect.y, rect.width, rect.height, width, height].every(Number.isFinite)
        || Math.min(rect.width, rect.height, width, height) <= 0) return null
    const scale = Math.min(rect.width / width, rect.height / height)
    const w = width * scale, h = height * scale
    return { x: rect.x + (rect.width - w) / 2, y: rect.y + (rect.height - h) / 2, width: w, height: h }
}

/** Cover-crop a uniformly scaled photo inside the thumbnail; never stretch its pixels. */
export function thumbnailPose(thumbnail, image, radius = 0) {
    const scale = Math.max(thumbnail.width / image.width, thumbnail.height / image.height)
    const width = image.width * scale, height = image.height * scale
    return {
        x: thumbnail.x - (width - thumbnail.width) / 2,
        y: thumbnail.y - (height - thumbnail.height) / 2,
        scale, top: (height - thumbnail.height) / height * 50,
        side: (width - thumbnail.width) / width * 50,
        radius: Math.max(0, radius) / scale,
    }
}

/** Analytic damped spring, sampled once for compositor keyframes; no per-frame JS. */
export function springProgress(time, { damping = .86, frequency = 18 } = {}) {
    const damped = frequency * Math.sqrt(1 - damping * damping)
    return 1 - Math.exp(-damping * frequency * time)
        * (Math.cos(damped * time) + damping * frequency / damped * Math.sin(damped * time))
}

export function morphKeyframes(from, to, { duration = 520, samples = 48, damping = .86 } = {}) {
    const interpolate = (a, b, t) => a + (b - a) * t
    const css = value => Number(value.toFixed(6))
    return Array.from({ length: samples + 1 }, (_, index) => {
        const offset = index / samples
        const spring = index === samples ? 1 : springProgress(offset * duration / 1000, { damping })
        const clip = Math.min(1, Math.max(0, spring))
        const top = css(Math.max(0, interpolate(from.top ?? 0, to.top ?? 0, clip)))
        const side = css(Math.max(0, interpolate(from.side ?? 0, to.side ?? 0, clip)))
        return { offset,
            transform: `translate3d(${css(interpolate(from.x, to.x, spring))}px,${css(interpolate(from.y, to.y, spring))}px,0) scale(${css(Math.max(.001, interpolate(from.scale, to.scale, spring)))})`,
            clipPath: `inset(${top}% ${side}% round ${css(interpolate(from.radius ?? 0, to.radius ?? 0, clip))}px)`,
        }
    })
}
