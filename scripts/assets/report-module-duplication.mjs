import path from 'node:path'
import { firstPaintVirtualInputs } from './first-paint-esbuild-plugin.mjs'
import fs from 'node:fs'
import { analyseModuleBuilds, atomicWrite, loadModuleBuild } from './module-build-analysis.mjs'
import { loadOpaqueBuild } from './opaque-build-analysis.mjs'
import { describeTiptapSharedInput } from '../tiptap-shared-esbuild-plugin.mjs'
import { verifyModuleOutputCoverage } from './module-output-coverage.mjs'

const root = path.resolve(import.meta.dirname, '../..')
const virtualInputs = await firstPaintVirtualInputs(root)
for (const specifier of ['@tiptap/core', '@tiptap/pm/state', '@tiptap/pm/view', '@tiptap/pm/model']) {
    const descriptor = await describeTiptapSharedInput(specifier, root)
    virtualInputs.set(descriptor.identity, descriptor)
}
const singleOutputs = {
    bootstrap: 'core/flex-fff-load-bootstrap.js', injector: 'core/flex-field-asset-injector.js',
    inspector: 'core/flex-field-asset-inspector.js',
    'flex-rich-editor-paste-extension': 'support/flex-rich-editor-paste-extension.js',
    'flex-rich-editor-block-image-extension': 'support/flex-rich-editor-block-image-extension.js',
    'flex-rich-editor-youtube-extension': 'support/flex-rich-editor-youtube-extension.js',
}
const ids = ['components', ...Object.keys(singleOutputs)]
const builds = ids.map(id => loadModuleBuild(root, id, { virtualInputs }))
const manifest = JSON.parse(fs.readFileSync(path.join(root, 'resources/dist/components/alpine-manifest.json'), 'utf8'))
const expected = [
    ...manifest.__entries__.map(entry => `resources/dist/components/${entry}.js`),
    ...manifest.__shared_chunks__.map(chunk => `resources/dist/components/${chunk}`),
].sort()
if (JSON.stringify(Object.keys(builds[0].outputs).sort()) !== JSON.stringify(expected)) throw new Error('Component analysis does not cover the active build graph.')
for (const build of builds.slice(1)) {
    if (JSON.stringify(Object.keys(build.outputs)) !== JSON.stringify([`resources/dist/${singleOutputs[build.id]}`])) throw new Error('Auxiliary analysis has missing or unexpected outputs.')
}
const report = analyseModuleBuilds(builds)
const legacy = fs.existsSync(path.join(root, 'build/asset-analysis/legacy-retained.json')) ? [loadOpaqueBuild(root, 'legacy-retained')] : []
const whisper = loadOpaqueBuild(root, 'whisper-prebuilt')
report.coverage = { ...verifyModuleOutputCoverage(root, builds, legacy), opaque: [...legacy, whisper] }
const components = analyseModuleBuilds(builds.filter(build => build.id === 'components'))
if (components.sameSourceRepeated) throw new Error('Shared component graph emitted repeated source modules.')
atomicWrite(path.join(root, 'resources/dist/js-module-duplication.json'), report)
console.log(`Module duplication: ${builds.length} attributed graphs /${report.coverage.attributedJavascript} outputs; ${report.coverage.opaqueDistributedJavascript} opaque legacy output; ${Object.keys(whisper.files).length} separately installed vendor artifacts. Component graph ${components.sameSourceRepeated}; measured graph ${report.ownSourceRepeated} own / ${report.sameSourceRepeated} total repeated sources.`)
if (report.sameSourceRepeated) {
    throw new Error('F15 remains open: measured own or third-party runtime sources are still compiled more than once.')
}
if (!report.coverage.sourceAttributionComplete) throw new Error('Retained previous-release/legacy JavaScript has no current source attribution; F01/F07 migration remains open.')
