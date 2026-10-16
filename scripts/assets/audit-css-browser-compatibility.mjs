import fs from 'node:fs'
import path from 'node:path'

const root = path.resolve(import.meta.dirname, '../../resources/dist/css')
const failures = []
let checked = 0
for (const name of fs.readdirSync(root).filter(name => name.endsWith('.css'))) {
    const css = fs.readFileSync(path.join(root, name), 'utf8')
    for (const [, selector, declarations] of css.matchAll(/([^{}]+)\{([^{}]*)\}/g)) {
        if (!/(?:^|;)-webkit-backdrop-filter:/.test(declarations)) continue
        ++checked
        if (!/(?:^|;)backdrop-filter:/.test(declarations)) failures.push(`${name}: ${selector.trim()}`)
    }
}
if (failures.length) throw new Error(`Compiled blur lacks the standard browser property:\n${failures.join('\n')}`)
console.log(`PASS: ${checked} compiled backdrop-filter declarations retain standard and Safari support.`)
