import assert from 'node:assert/strict'
import { execFileSync } from 'node:child_process'
import fs from 'node:fs'
import path from 'node:path'
import { acceptanceRequirements, digest, sourceSnapshot } from './acceptance-contract.mjs'
import { verifyAcceptanceEvidence } from './acceptance-evidence.mjs'

const root = path.resolve(import.meta.dirname, '../..')
const options = {}
for (const argument of process.argv.slice(2)) {
    const match = argument.match(/^--(input|output)=(.+)$/)
    assert.ok(match && !Object.hasOwn(options, match[1]), 'Unknown or duplicate certification argument.')
    options[match[1]] = path.resolve(root, match[2])
}
assert.ok(options.input, 'Provide --input=<downloaded acceptance artifact directory>.')
assert.equal(execFileSync('git', ['status', '--porcelain'], { cwd: root, encoding: 'utf8' }).trim(), '', 'Certification requires a clean checkout.')
const sealBytes = fs.readFileSync(path.join(root, 'resources/dist/asset-versions.json'))
const seal = JSON.parse(sealBytes)
const report = verifyAcceptanceEvidence({
    directory: options.input, commit: execFileSync('git', ['rev-parse', 'HEAD'], { cwd: root, encoding: 'utf8' }).trim(),
    sources: sourceSnapshot(root),
    release: seal.release, assetHashes: seal.assets, assetManifestSha256: digest(sealBytes),
    requirements: acceptanceRequirements(root),
})
if (options.output) {
    fs.mkdirSync(path.dirname(options.output), { recursive: true })
    for (const [name, metadata] of Object.entries(report.releaseMetadata)) {
        const bytes = fs.readFileSync(path.join(options.input, metadata.source))
        assert.equal(digest(bytes), metadata.sha256, 'Release metadata changed after verification.')
        const destination = path.join(path.dirname(options.output), 'release-metadata', name)
        fs.mkdirSync(path.dirname(destination), { recursive: true })
        fs.writeFileSync(destination, bytes)
    }
    fs.writeFileSync(options.output, JSON.stringify(report, null, 2) + '\n')
}
process.stdout.write(JSON.stringify(report) + '\n')
