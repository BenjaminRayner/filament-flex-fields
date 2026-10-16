import { transform } from 'lightningcss'
import { createHash } from 'node:crypto'
import { canonicalCssNode as canonical, cssRuleContext } from './css-rule-identity.mjs'

/** Parse CSS; report equal AST rules in equal conditional/nesting contexts.
 * Analysis does not remove rules: different artifacts are not equal resources.
 */
export function analyzeStylesheetCascade(files) {
    const signatures = new Map(), animations = new Map(), fonts = new Map(), summaries = []
    for (const { path, code } of files) {
        const parents = []
        let styles = 0
        transform({ filename: path, code: Buffer.from(code), minify: false, visitor: {
            Rule(rule) {
                if (rule.type === 'style' && (rule.value.declarations.declarations.length || rule.value.declarations.importantDeclarations.length)) {
                    styles++
                    const body = canonical({ parents, selectors: rule.value.selectors, declarations: rule.value.declarations })
                    const identity = createHash('sha256').update(JSON.stringify(body)).digest('hex')
                    let occurrences = signatures.get(identity)
                    if (!occurrences) { occurrences = []; signatures.set(identity, occurrences) }
                    occurrences.push({ path, line: rule.value.loc.line + 1, column: rule.value.loc.column })
                }
                if (rule.type === 'keyframes') {
                    const name = rule.value.name.value
                    let occurrences = animations.get(name)
                    if (!occurrences) { occurrences = []; animations.set(name, occurrences) }
                    const body = canonical({ parents, keyframes: rule.value.keyframes, vendorPrefix: rule.value.vendorPrefix })
                    occurrences.push({ path, digest: createHash('sha256').update(JSON.stringify(body)).digest('hex') })
                }
                if (rule.type === 'font-face') {
                    const name = rule.value.properties.find(property => property.type === 'font-family')?.value
                    if (typeof name === 'string') {
                        const identity = name.toLowerCase()
                        let occurrences = fonts.get(identity)
                        if (!occurrences) { occurrences = []; fonts.set(identity, occurrences) }
                        occurrences.push({ path, family: name, line: rule.value.loc.line + 1,
                            digest: createHash('sha256').update(JSON.stringify(canonical({ parents, properties: rule.value.properties.map(property =>
                                property.type === 'font-family' ? { ...property, value: identity } : property) }))).digest('hex') })
                    }
                }
                parents.push(cssRuleContext(rule))
            },
            RuleExit() { parents.pop() },
        } })
        if (parents.length) throw Error('Unbalanced CSS analysis visitor.')
        summaries.push({ path, bytes: Buffer.byteLength(code), styleRules: styles })
    }
    const duplicates = [...signatures].filter(([, occurrences]) => new Set(occurrences.map(item => item.path)).size > 1)
        .map(([digest, occurrences]) => ({ digest, occurrences })).sort((a, b) => a.digest.localeCompare(b.digest))
    const sharedAnimations = [...animations].filter(([, occurrences]) => new Set(occurrences.map(item => item.path)).size > 1)
        .map(([name, occurrences]) => ({ name, identical: new Set(occurrences.map(item => item.digest)).size === 1, occurrences }))
        .sort((a, b) => a.name.localeCompare(b.name))
    const sharedFonts = [...fonts].filter(([, occurrences]) => new Set(occurrences.map(item => item.path)).size > 1)
        .map(([name, occurrences]) => ({ name, identical: new Set(occurrences.map(item => item.digest)).size === 1, occurrences }))
        .sort((a, b) => a.name.localeCompare(b.name))
    return { format: 1, scope: 'built-in compiled CSS artifacts', analysis: 'equal parsed style rules within equal conditional/nesting contexts; no removal or automatic merge',
        files: summaries, duplicateRuleGroups: duplicates.length, duplicateRules: duplicates,
        sharedAnimationNames: sharedAnimations, conflictingAnimationNames: sharedAnimations.filter(item => !item.identical).length,
        sharedFontNames: sharedFonts, conflictingFontNames: sharedFonts.filter(item => !item.identical).length }
}
