import assert from 'node:assert/strict'
import { execFileSync } from 'node:child_process'
import fs from 'node:fs'
import path from 'node:path'
import { digest, sourceSnapshot } from './acceptance-contract.mjs'
import { createLockfileSbom, createBuildProvenance, safeReleaseFile, verifyReleaseSeal } from './release-metadata.mjs'

const root = path.resolve(import.meta.dirname, '../..')
const args = process.argv.slice(2)
assert.ok(args.length === 1 && args[0].startsWith('--output=') && args[0].length > 9, 'Provide one --output=<metadata directory>.')
const output = path.resolve(root, args[0].slice(9))
assert.notEqual(output, root)
const read = name => JSON.parse(safeReleaseFile(root, name))
const seal = read('resources/dist/asset-versions.json')
const assets = verifyReleaseSeal(root, seal)
const sources = sourceSnapshot(root)
const sbom = createLockfileSbom({ version: safeReleaseFile(root, 'VERSION').toString().trim(), composer: read('composer.json'),
    composerLock: read('composer.lock'), npmLock: read('package-lock.json') })
const bom = JSON.stringify(sbom, null, 2) + '\n'
const materials = Object.fromEntries(['composer.lock', 'package-lock.json', 'resources/dist/asset-versions.json']
    .map(name => [name, digest(safeReleaseFile(root, name))]))
const provenance = createBuildProvenance({
    commit: execFileSync('git', ['rev-parse', 'HEAD'], { cwd: root, encoding: 'utf8' }).trim(),
    sourceSha256: digest(JSON.stringify(sources)), release: seal.release, assets, materials, sbomSha256: digest(bom),
    environment: { node: process.version, platform: process.platform, architecture: process.arch,
        php: execFileSync(process.env.PHP_BINARY ?? 'php', ['-r', 'echo PHP_VERSION;'], { encoding: 'utf8' }) },
})
assert.equal(JSON.stringify(sourceSnapshot(root)), JSON.stringify(sources), 'Source changed while generating release metadata.')
fs.mkdirSync(output, { recursive: true })
fs.writeFileSync(path.join(output, 'sbom.cdx.json'), bom)
fs.writeFileSync(path.join(output, 'build-provenance.json'), JSON.stringify(provenance, null, 2) + '\n')
process.stdout.write(JSON.stringify({ components: sbom.components.length, assets: Object.keys(assets).length,
    release: seal.release, sourceSha256: provenance.sourceSha256, signed: false }) + '\n')
