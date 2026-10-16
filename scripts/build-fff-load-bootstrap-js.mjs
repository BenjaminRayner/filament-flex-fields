import esbuild from 'esbuild'
import fs from 'node:fs'
import path from 'node:path'
import { captureModuleBuild } from './assets/module-build-analysis.mjs'

const packageRoot = path.resolve(import.meta.dirname, '..')
const entry = path.join(packageRoot, 'resources/js/core/flex-fff-load-bootstrap.js')
const outDir = path.join(packageRoot, 'resources/dist/core')
const outfile = path.join(outDir, 'flex-fff-load-bootstrap.js')

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
captureModuleBuild(packageRoot, 'bootstrap', result.metafile)

console.log(`Built ${path.relative(packageRoot, outfile)}`)
