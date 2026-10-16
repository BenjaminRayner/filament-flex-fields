/** Bounded arithmetic grammar. User expressions are never executed as JavaScript. */
export function calculateExpression(source) {
    source = String(source ?? '').replace(/[−]/g, '-').replace(/[×]/g, '*').replace(/[÷]/g, '/').trim()
    if (!source || source.length > 2048) throw new RangeError('Invalid expression length.')
    const tokens = source.match(/(?:\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?|[()+\-*/%]/gi) ?? []
    if (tokens.length > 512 || tokens.join('') !== source.replace(/\s/g, '')) throw new SyntaxError('Invalid expression.')
    let cursor = 0
    const finite = (value) => {
        if (!Number.isFinite(value)) throw new RangeError('Non-finite result.')
        return value
    }
    const primary = (depth) => {
        if (depth > 64) throw new RangeError('Expression nesting limit.')
        const token = tokens[cursor++]
        let result
        if (token === '+' || token === '-') result = (token === '-' ? -1 : 1) * primary(depth + 1)
        else if (token === '(') {
            result = sum(depth + 1)
            if (tokens[cursor++] !== ')') throw new SyntaxError('Unbalanced expression.')
        } else if (token && /^(?:\d|\.)/.test(token)) result = finite(Number(token))
        else throw new SyntaxError('Expected a number.')
        while (tokens[cursor] === '%') { cursor++; result /= 100 }
        return finite(result)
    }
    const product = (depth) => {
        let value = primary(depth)
        while (tokens[cursor] === '*' || tokens[cursor] === '/') {
            const operator = tokens[cursor++]
            const next = primary(depth)
            value = finite(operator === '*' ? value * next : value / next)
        }
        return value
    }
    const sum = (depth) => {
        let value = product(depth)
        while (tokens[cursor] === '+' || tokens[cursor] === '-') {
            const operator = tokens[cursor++]
            const next = product(depth)
            value = finite(operator === '+' ? value + next : value - next)
        }
        return value
    }
    const value = sum(0)
    if (cursor !== tokens.length) throw new SyntaxError('Unexpected token.')
    return value
}

/** Applying a result obeys the field's precision policy; bounds reject rather than silently clamp. */
export function calculatorResult(value, {integer = false, decimalPlaces = null, roundingMode = 'truncate', min = null, max = null} = {}) {
    if (!Number.isFinite(value)) throw new RangeError('Non-finite result.')
    const precision = integer ? 0 : decimalPlaces
    if (precision !== null) {
        if (!Number.isInteger(precision) || precision < 0 || precision > 12) throw new RangeError('Invalid precision.')
        const round = {round:(number) => Math.sign(number) * Math.round(Math.abs(number)), floor:Math.floor, ceil:Math.ceil, truncate:Math.trunc}[roundingMode]
        if (!round) throw new TypeError('Invalid rounding mode.')
        const scale = 10 ** precision
        let scaled = value * scale
        const nearest = Math.round(scaled)
        if (Math.abs(scaled - nearest) <= Number.EPSILON * Math.max(1, Math.abs(scaled)) * 4) scaled = nearest
        value = round(scaled) / scale
    }
    // Normalize binary arithmetic noise without manufacturing unlimited precision.
    value = Number(value.toPrecision(15))
    if (!Number.isFinite(value) || (integer && !Number.isSafeInteger(value)) || (min !== null && value < Number(min)) || (max !== null && value > Number(max))) throw new RangeError('Result outside field bounds.')
    return Object.is(value, -0) ? 0 : value
}
