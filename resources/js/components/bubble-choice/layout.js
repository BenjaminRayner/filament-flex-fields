export const defaultLayoutOptions = {
    size: 160,
    minSize: 25,
    gutter: 8,
    provideProps: false,
    numCols: 6,
    fringeWidth: 100,
    yRadius: 200,
    xRadius: 200,
    cornerRadius: 100,
    compact: false,
    gravitation: 0,
}

export function mergeLayoutOptions(overrides = {}) {
    const options = { ...defaultLayoutOptions, ...overrides }
    const finite = (value, fallback) => Number.isFinite(Number(value)) ? Number(value) : fallback
    options.size = Math.max(1, Math.min(10000, finite(options.size, 160)))
    options.minSize = Math.max(0, Math.min(options.size, finite(options.minSize, 25)))
    options.numCols = Math.max(1, Math.min(4096, Math.floor(finite(options.numCols, 6)),
        Math.max(1, finite(overrides._optionCount, 4096))))
    for (const key of ['gutter', 'fringeWidth', 'xRadius', 'yRadius', 'cornerRadius', 'gravitation']) {
        options[key] = Math.max(0, Math.min(10000, finite(options[key], defaultLayoutOptions[key])))
    }
    options.cornerRadius = Math.min(options.cornerRadius, options.xRadius, options.yRadius)
    options.compact = !!options.compact
    options.provideProps = !!options.provideProps
    delete options._optionCount
    return options
}

export function interpolate(actualMin, actualMax, val, targetMin, targetMax) {
    return actualMax === actualMin ? (val > actualMin ? targetMax : targetMin)
        : ((val - actualMin) / (actualMax - actualMin)) * (targetMax - targetMin) + targetMin
}

/**
 * @returns {Array<Array<{ option: object|null, isPad: boolean }>>}
 */
export function buildBubbleRows(optionsList, numCols) {
    const cappedCols = Math.min(Math.max(1, numCols), Math.max(1, optionsList.length))
    const rows = []
    let colsRemaining = 0
    let evenRow = true

    for (let i = 0; i < optionsList.length; i += 1) {
        if (colsRemaining === 0) {
            colsRemaining = evenRow ? Math.max(1, cappedCols - 1) : cappedCols
            evenRow = ! evenRow
            rows.push([])
        }

        rows[rows.length - 1].push({
            option: optionsList[i],
            isPad: false,
        })
        colsRemaining -= 1
    }

    if (rows.length > 1) {
        if (rows[rows.length - 1].length % 2 === rows[rows.length - 2].length % 2) {
            rows[rows.length - 1].push({ option: null, isPad: true })
        }
    }

    return rows
}

export function getBubbleSize(options, rows, row, col, scrollTop, scrollLeft) {
    const minProportion = options.minSize / options.size
    const yOffset = (options.size + options.gutter) * 0.866 * row
        - options.size
        + options.cornerRadius * (1.414 - 1) / 1.414
        - (options.yRadius - options.size)
    const xOffset = (options.size + options.gutter) * col
        + (options.numCols - rows[row].length) * (options.size + options.gutter) / 2
        - options.size
        + options.cornerRadius * (1.414 - 1) / 1.414
        - (options.xRadius - options.size)
    const dy = yOffset - scrollTop
    const dx = xOffset - scrollLeft
    const distance = Math.sqrt(dx * dx + dy * dy)
    const out = {
        bubbleSize: 1,
        translateX: 0,
        translateY: 0,
        distance,
    }

    let distanceFromEdge = 0
    let isInCornerRegion = false

    if (Math.abs(dx) <= options.xRadius && Math.abs(dy) <= options.yRadius) {
        if (Math.abs(dy) > options.yRadius - options.cornerRadius && Math.abs(dx) > options.xRadius - options.cornerRadius) {
            const distToInnerCorner = Math.sqrt(
                Math.pow(Math.abs(dy) - options.yRadius + options.cornerRadius, 2)
                + Math.pow(Math.abs(dx) - options.xRadius + options.cornerRadius, 2),
            )

            if (distToInnerCorner > options.cornerRadius) {
                distanceFromEdge = distToInnerCorner - options.cornerRadius
                isInCornerRegion = true
            }
        }
    } else if (
        Math.abs(dx) <= options.xRadius + options.fringeWidth
        && Math.abs(dy) <= options.yRadius + options.fringeWidth
    ) {
        if (Math.abs(dy) > options.yRadius - options.cornerRadius && Math.abs(dx) > options.xRadius - options.cornerRadius) {
            isInCornerRegion = true

            const distToInnerCorner = Math.sqrt(
                Math.pow(Math.abs(dy) - options.yRadius + options.cornerRadius, 2)
                + Math.pow(Math.abs(dx) - options.xRadius + options.cornerRadius, 2),
            )

            distanceFromEdge = distToInnerCorner - options.cornerRadius
        } else {
            distanceFromEdge = Math.max(Math.abs(dx) - options.xRadius, Math.abs(dy) - options.yRadius)
        }
    } else {
        isInCornerRegion = Math.abs(dy) > options.yRadius - options.cornerRadius
            && Math.abs(dx) > options.xRadius - options.cornerRadius

        if (isInCornerRegion) {
            const distToInnerCorner = Math.sqrt(
                Math.pow(Math.abs(dy) - options.yRadius + options.cornerRadius, 2)
                + Math.pow(Math.abs(dx) - options.xRadius + options.cornerRadius, 2),
            )

            distanceFromEdge = distToInnerCorner - options.cornerRadius
        } else {
            distanceFromEdge = Math.max(Math.abs(dx) - options.xRadius, Math.abs(dy) - options.yRadius)
        }
    }

    out.bubbleSize = interpolate(0, options.fringeWidth, Math.min(distanceFromEdge, options.fringeWidth), 1, minProportion)

    const translationMag = options.compact ? (options.size - options.minSize) / 2 : 0
    const interpolatedTranslationMag = interpolate(0, options.fringeWidth, distanceFromEdge, 0, translationMag)

    if (distanceFromEdge > 0 && distanceFromEdge <= options.fringeWidth) {
        out.translateX = interpolatedTranslationMag
        out.translateY = interpolatedTranslationMag
    } else if (distanceFromEdge - options.fringeWidth > 0) {
        const extra = Math.max(0, distanceFromEdge - options.fringeWidth - options.size / 2) * options.gravitation / 10
        out.translateX = translationMag + extra
        out.translateY = translationMag + extra
    }

    if (isInCornerRegion) {
        const cornerDx = Math.abs(dx) - options.xRadius + options.cornerRadius
        const cornerDy = Math.abs(dy) - options.yRadius + options.cornerRadius
        let theta = Math.atan2(-cornerDy, cornerDx)

        if (dx > 0) {
            if (dy > 0) {
                theta *= -1
            }
        } else if (dy > 0) {
            theta += Math.PI
        } else {
            theta += Math.PI - 2 * theta
        }

        out.translateX *= -Math.cos(theta)
        out.translateY *= -Math.sin(theta)
    } else if (Math.abs(dx) > options.xRadius || Math.abs(dy) > options.yRadius) {
        if (Math.abs(dx) > options.xRadius) {
            out.translateX *= -Math.sign(dx)
            out.translateY = 0
        } else {
            out.translateY *= -Math.sign(dy)
            out.translateX = 0
        }
    }

    return out
}

export function contrastTextForBackground(background, light = '#f8fafc', dark = '#0f172a') {
    if (! background || typeof background !== 'string') {
        return light
    }

    const value = background.trim()
    const hex = value.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i)
    let rgb = null

    if (hex) {
        let body = hex[1]

        if (body.length === 3) {
            body = body.split('').map((char) => char + char).join('')
        }

        rgb = [
            Number.parseInt(body.slice(0, 2), 16),
            Number.parseInt(body.slice(2, 4), 16),
            Number.parseInt(body.slice(4, 6), 16),
        ]
    } else {
        const match = value.match(/^rgba?\(\s*([\d.]+)\s*[, ]\s*([\d.]+)\s*[, ]\s*([\d.]+)/i)

        if (match) {
            rgb = [Number.parseFloat(match[1]), Number.parseFloat(match[2]), Number.parseFloat(match[3])]
        }
    }

    if (! rgb) {
        return light
    }

    const [r, g, b] = rgb.map((channel) => {
        const channelValue = channel / 255

        return channelValue <= 0.03928
            ? channelValue / 12.92
            : ((channelValue + 0.055) / 1.055) ** 2.4
    })

    const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b

    return luminance > 0.45 ? dark : light
}

function cornerOffsetPx(options) {
    return options.cornerRadius * (1.414 - 1) / 1.414
}

/**
 * Exact algebraic form of their scrollTo((scrollSize - clientSize) / 2)
 * given spacers/padding of calc(50% - (radius + size/2 - cornerOffset)).
 *
 * @returns {{ scrollLeft: number, scrollTop: number }}
 */
export function estimateCenterScroll(options, rows) {
    const offset = cornerOffsetPx(options)
    const contentWidth = options.size * options.numCols + options.gutter * Math.max(0, options.numCols - 1)
    let stackHeight = rows.length ? options.size : 0

    for (let index = 1; index < rows.length; index += 1) {
        stackHeight += options.size + options.size * -0.134 + options.gutter * 0.866
    }

    return {
        scrollLeft: Math.max(0, contentWidth / 2 - (options.xRadius + options.size / 2 - offset)),
        scrollTop: Math.max(0, stackHeight / 2 - (options.yRadius + options.size / 2 - offset)),
    }
}

