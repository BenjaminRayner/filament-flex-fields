import esbuild from 'esbuild'
import fs from 'node:fs'
import path from 'node:path'
import { captureModuleBuild } from './assets/module-build-analysis.mjs'

const packageRoot = path.resolve(import.meta.dirname, '..')
const entry = path.join(packageRoot, 'resources/js/core/startup/injector-launcher.js')
const outDir = path.join(packageRoot, 'resources/dist/core')
const outfile = path.join(outDir, 'flex-field-asset-injector.js')
fs.mkdirSync(outDir, { recursive: true })

const result = await esbuild.build({
    absWorkingDir: packageRoot,
    metafile: true,
    entryPoints: [entry],
    outfile,
    bundle: true,
    format: 'iife',
    platform: 'browser',
    target: ['es2020'],
    minify: true,
})
captureModuleBuild(packageRoot, 'injector', result.metafile)

console.log(`Built ${path.relative(packageRoot, outfile)}`)
