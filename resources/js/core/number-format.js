/**
 * Shared numeric display formatting.
 *
 * `formatDecimal()` deliberately keeps the historical, locale-agnostic output
 * when no locale is configured, so fields that never opt in are unchanged.
 */

export function normalizeLocale(locale) {
    if (typeof locale !== 'string' || locale.trim() === '') {
        return 'en-US'
    }

    return locale.trim().replace(/_/g, '-')
}

function plainDecimal(numeric, decimalPlaces) {
    return decimalPlaces === null ? String(numeric) : numeric.toFixed(decimalPlaces)
}

function createLocaleFormatter(locale, decimalPlaces) {
    const hasLocale = typeof locale === 'string' && locale.trim() !== ''

    if (! hasLocale) {
        return null
    }

    try {
        return new Intl.NumberFormat(normalizeLocale(locale), {
            minimumFractionDigits: decimalPlaces ?? 0,
            // Intl caps fraction digits at 20, which matches `String(number)`.
            maximumFractionDigits: decimalPlaces ?? 20,
        })
    } catch {
        // An unsupported locale tag must never break the field.
        return null
    }
}

/**
 * Compile once for frequently updated controls; retain no numeric values.
 * @param {{ locale?: string|null, decimalPlaces?: number|null }} [options]
 * @returns {(numeric: number) => string}
 */
export function createDecimalFormatter({ locale = null, decimalPlaces = null } = {}) {
    const formatter = createLocaleFormatter(locale, decimalPlaces)
    return numeric => !Number.isFinite(numeric) ? '' : formatter ? formatter.format(numeric) : plainDecimal(numeric, decimalPlaces)
}

/** @param {number} numeric @param {{ locale?: string|null, decimalPlaces?: number|null }} [options] */
export function formatDecimal(numeric, { locale = null, decimalPlaces = null } = {}) {
    if (!Number.isFinite(numeric)) return ''
    const formatter = createLocaleFormatter(locale, decimalPlaces)
    return formatter ? formatter.format(numeric) : plainDecimal(numeric, decimalPlaces)
}
