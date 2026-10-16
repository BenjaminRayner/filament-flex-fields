import crypto from 'node:crypto'
import fs from 'node:fs'
import path from 'node:path'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'
import { captureOpaqueBuild } from './assets/opaque-build-analysis.mjs'

// The browser runtime is installed separately; never copy it into Composer dist.
const packageRoot = path.resolve(import.meta.dirname, '..')
const transformersRoot = path.resolve(path.dirname(fileURLToPath(import.meta.resolve('@huggingface/transformers'))), '..')
const requireTransformers = createRequire(path.join(transformersRoot, 'package.json'))
const onnxRoot = path.resolve(path.dirname(requireTransformers.resolve('onnxruntime-web')), '..')
const transformers = JSON.parse(fs.readFileSync(path.join(transformersRoot, 'package.json'), 'utf8'))
const onnx = JSON.parse(fs.readFileSync(path.join(onnxRoot, 'package.json'), 'utf8'))
const artifactDist = path.join(packageRoot, 'artifacts/whisper-runtime')
const manifestPath = path.join(packageRoot, 'src/Support/WhisperRuntimeManifest.php')
const sources = {
    'transformers.min.js': path.join(transformersRoot, 'dist/transformers.min.js'),
    'ort-wasm-simd-threaded.mjs': path.join(onnxRoot, 'dist/ort-wasm-simd-threaded.mjs'),
    'ort-wasm-simd-threaded.wasm': path.join(onnxRoot, 'dist/ort-wasm-simd-threaded.wasm'),
}
const computed = Object.fromEntries(Object.entries(sources).map(([name, source]) => [
    name, crypto.createHash('sha256').update(fs.readFileSync(source)).digest('hex'),
]))
let manifestPhp = fs.readFileSync(manifestPath, 'utf8')
if (process.argv.includes('--update-manifest')) {
    manifestPhp = manifestPhp
        .replace(/public const VERSION = '[^']+';/, `public const VERSION = '${transformers.version}';`)
        .replace(/public const ONNX_VERSION = '[^']+';/, `public const ONNX_VERSION = '${onnx.version}';`)
        .replace(/public const FILES = \[[\s\S]*?\n    \];/, `public const FILES = [\n${Object.entries(computed).map(([name, hash]) => `        '${name}' => '${hash}',`).join('\n')}\n    ];`)
    fs.writeFileSync(manifestPath, manifestPhp)
}
const mismatches = []
if (!manifestPhp.includes(`public const PACKAGE = '${transformers.name}';`)
    || !manifestPhp.includes(`public const VERSION = '${transformers.version}';`)
    || !manifestPhp.includes(`public const ONNX_VERSION = '${onnx.version}';`)) {
    mismatches.push('Package/version pins do not match the installed dependency graph.')
}
for (const [name, hash] of Object.entries(computed)) {
    if (!manifestPhp.includes(`'${name}' => '${hash}'`)) mismatches.push(`${name}: SHA-256 differs from the manifest.`)
}
if (mismatches.length) {
    throw new Error(`${mismatches.join('\n')}\nReview the dependency update, then run node scripts/build-whisper-runtime.mjs --update-manifest.`)
}
if (fs.existsSync(path.join(packageRoot, 'resources/dist/assets/whisper'))) {
    throw new Error('Whisper binaries must stay outside the Composer package.')
}
captureOpaqueBuild(packageRoot, 'whisper-prebuilt', Object.values(sources).map(source => path.relative(packageRoot, source)), {
    reason: 'Optional separately installed browser JS/ONNX WASM; exact source bytes verified against the installer manifest.',
    provenance: ['src/Support/WhisperRuntimeManifest.php', path.relative(packageRoot, path.join(transformersRoot, 'package.json')), path.relative(packageRoot, path.join(onnxRoot, 'package.json'))],
})
if (process.argv.includes('--write-artifact')) {
    fs.mkdirSync(artifactDist, { recursive: true })
    for (const [name, source] of Object.entries(sources)) fs.copyFileSync(source, path.join(artifactDist, name))
    fs.writeFileSync(path.join(artifactDist, 'manifest.json'), `${JSON.stringify({ package: transformers.name, version: transformers.version, onnxVersion: onnx.version, files: computed }, null, 2)}\n`)
    console.log('Wrote the separately installed Whisper runtime artifact.')
} else {
    console.log('Whisper runtime matches pinned Hugging Face/ONNX source bytes (no package binaries).')
}
