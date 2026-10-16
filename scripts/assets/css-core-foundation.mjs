import { transform } from 'lightningcss'
import { canonicalCssNode, cssRuleContext } from './css-rule-identity.mjs'

const signature = value => JSON.stringify(canonicalCssNode(value))
const bounded = code => {
    if (!(code instanceof Uint8Array) || code.byteLength > 16 * 1024 * 1024) {
        throw Error('CSS foundation analysis requires a bounded compiled stylesheet.')
    }
}
const fallback = (rule, parents) => rule.type === 'style'
    && parents.length === 2 && parents[0].type === 'layer-block'
    && signature(parents[0].value.name) === '["properties"]' && parents[1].type === 'supports'
const variable = declaration => declaration.property === 'custom'
    && declaration.value.name.startsWith('--tw-')
const fallbackKey = (rule, parents) => signature({ parents, selectors: rule.value.selectors })

/** Core is already a mandatory predecessor of every builtin component boundary.
 * Remove only matching Tailwind initialization from lazy builtin CSS. No author
 * selectors, ordinary component rules, unknown properties or conditions merge.
 */
export function createCoreCssFoundation(core) {
    bounded(core)
    const declarations = new Map(), properties = new Set(), parents = []
    transform({ filename: 'core.css', code: Buffer.from(core), visitor: {
        Rule(rule) {
            if (fallback(rule, parents)) {
                const key = fallbackKey(rule, parents)
                let owned = declarations.get(key)
                if (!owned) { owned = new Set(); declarations.set(key, owned) }
                for (const declaration of rule.value.declarations.declarations) {
                    if (variable(declaration)) owned.add(signature(declaration))
                }
            }
            if (rule.type === 'property' && rule.value.name.startsWith('--tw-')) {
                properties.add(signature({ parents, value: rule.value }))
            }
            parents.push(cssRuleContext(rule))
        },
        RuleExit() { parents.pop() },
    } })
    if (parents.length) throw Error('Unbalanced core CSS foundation traversal.')

    return Object.freeze({
        prune(code, filename) {
            bounded(code)
            const stack = [], removals = [], lines = [0]
            const source = new TextDecoder('utf-8', { fatal: true, ignoreBOM: true }).decode(code)
            for (let offset = 0; offset < source.length; offset++) {
                if (source[offset] === '\n') lines.push(offset + 1)
            }
            let removedDeclarations = 0, removedProperties = 0
            const remove = rule => {
                // Lightning CSS locations are one-based UTF-16 columns, zero-based lines.
                const start = lines[rule.value.loc.line] + rule.value.loc.column - 1
                if (!Number.isSafeInteger(start) || start < 0 || start >= source.length
                    || (rule.type === 'property' ? !source.startsWith('@property', start) : source[start] !== '*')) {
                    throw Error(`Unexpected compiled CSS source location: ${filename}`)
                }
                removals.push([start, cssBlockEnd(source, start)])
            }
            transform({ filename, code: Buffer.from(code), visitor: {
                Rule(rule) {
                    if (rule.type === 'property' && rule.value.name.startsWith('--tw-')
                        && properties.has(signature({ parents: stack, value: rule.value }))) {
                        removedProperties++
                        remove(rule)
                    }
                    if (fallback(rule, stack)) {
                        const owned = declarations.get(fallbackKey(rule, stack))
                        const values = rule.value.declarations.declarations
                        // Preserve a mixed/unknown block intact rather than rewrite its declarations.
                        if (owned && values.length && !rule.value.declarations.importantDeclarations.length
                            && !rule.value.rules?.length
                            && values.every(value => variable(value) && owned.has(signature(value)))) {
                            removedDeclarations += values.length
                            remove(rule)
                        }
                    }
                    stack.push(cssRuleContext(rule))
                },
                RuleExit() { stack.pop() },
            } })
            if (stack.length) throw Error('Unbalanced component CSS foundation traversal.')
            removals.sort((left, right) => left[0] - right[0])
            const pieces = []
            let end = 0
            for (const [start, next] of removals) {
                if (start < end) throw Error('Overlapping CSS foundation source spans.')
                pieces.push(source.slice(end, start))
                end = next
            }
            pieces.push(source.slice(end))
            return { code: Buffer.from(pieces.join('')), removedDeclarations, removedProperties }
        },
    })
}


/** Delete complete parser-admitted blocks; leave every other source byte intact. */
function cssBlockEnd(source, start) {
    let depth = 0, opened = false, quote = null
    for (let offset = start; offset < source.length; offset++) {
        const character = source[offset]
        if (character === '\\') { offset++; continue }
        if (quote) {
            if (character === quote) quote = null
            continue
        }
        if (character === '"' || character === "'") { quote = character; continue }
        if (character === '/' && source[offset + 1] === '*') {
            const end = source.indexOf('*/', offset + 2)
            if (end === -1) break
            offset = end + 1
            continue
        }
        if (character === '{') { depth++; opened = true }
        if (character === '}' && opened && --depth === 0) return offset + 1
    }
    throw Error('Unterminated compiled CSS foundation block.')
}
