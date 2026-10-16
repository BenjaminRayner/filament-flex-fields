import assert from 'node:assert/strict'
import { readdir, readFile, writeFile } from 'node:fs/promises'
import { resolve, relative } from 'node:path'
import { analyzeStylesheetCascade } from '../assets/css-cascade-report.mjs'

const sample = analyzeStylesheetCascade([
    { path: 'first.css', code: '.same{color:red}@media(width>10px){.conditional{color:red}}@keyframes local{from{opacity:0}to{opacity:1}}@font-face{font-family:Local;src:url(first.woff2)}' },
    { path: 'second.css', code: '.same{color:#ff0000}@media(width>20px){.conditional{color:red}}@keyframes local{from{opacity:1}to{opacity:0}}@font-face{font-family:local;src:url(second.woff2)}' },
])
assert.equal(sample.duplicateRuleGroups, 1, 'Equivalent parsed colors should match; different media conditions must not.')
assert.equal(sample.conflictingAnimationNames, 1, 'Conflicting global animation definitions must be visible.')
assert.equal(sample.conflictingFontNames, 1, 'Conflicting font namespaces must be reported with their stylesheet locations.')
const equivalentFonts = analyzeStylesheetCascade([
    { path: 'first.css', code: '@font-face{font-family:Local;src:url(same.woff2)}' },
    { path: 'second.css', code: '@font-face{font-family:local;src:url(same.woff2)}' },
])
assert.equal(equivalentFonts.conflictingFontNames, 0, 'Case-insensitive family identity must not fabricate a font conflict.')

const root = resolve(import.meta.dirname, '../..'), directory = resolve(root, 'resources/dist/css')
const files = await Promise.all((await readdir(directory)).filter(name => name.endsWith('.css')).sort()
    .map(async name => ({ path: relative(root, resolve(directory, name)), code: await readFile(resolve(directory, name)) })))
const report = analyzeStylesheetCascade(files)
const foundationDuplicates = report.duplicateRules.filter(group => {
    const paths = new Set(group.occurrences.map(item => item.path))
    return paths.has('resources/dist/css/core.css') && paths.has('resources/dist/css/playground.css')
}).length
assert.equal(foundationDuplicates, 0, 'Core owns the shared foundation; catalog must reference it without recompiling its rules.')
const destination = resolve(root, '.internal/core-css-cascade-report.json')
const output = JSON.stringify(report, null, 2) + '\n'
if (process.argv.includes('--check')) {
    if (await readFile(destination, 'utf8') !== output) throw Error('CSS cascade report is stale; rebuild and regenerate it.')
} else await writeFile(destination, output)
process.stdout.write(JSON.stringify({ files: report.files.length, duplicateRuleGroups: report.duplicateRuleGroups,
    conflictingAnimationNames: report.conflictingAnimationNames, report: relative(root, destination) }) + '\n')
