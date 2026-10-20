import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import { digest } from './acceptance-contract.mjs'
import { verifyAcceptanceEnvironment } from './acceptance-environment.mjs'

const hash = /^[a-f0-9]{64}$/
const commitHash = /^[a-f0-9]{40}(?:[a-f0-9]{24})?$/
const boundedRead = (file, limit) => {
    const stat = fs.lstatSync(file)
    assert.ok(stat.isFile() && stat.size <= limit, 'Evidence must be a bounded regular file: ' + file)
    return fs.readFileSync(file)
}

/** Logs and receipts are data. Never execute commands or follow paths from an artifact. */
export function verifyAcceptanceEvidence({ directory, commit, sources, release, assetHashes, assetManifestSha256, requirements }) {
    assert.match(commit, commitHash, 'Expected commit identity is invalid.')
    assert.match(release, hash, 'Expected asset release is invalid.')
    assert.ok(Object.keys(requirements).length > 0, 'Acceptance requirements cannot be empty.')
    const expectedSource = digest(JSON.stringify(sources)), found = new Map()
    let visits = 0
    const releaseMetadata = {}
    const inspect = (location, depth = 0) => {
        assert.ok(depth <= 8 && fs.lstatSync(location).isDirectory(), 'Unsafe evidence directory.')
        for (const entry of fs.readdirSync(location, { withFileTypes: true })) {
            assert.ok(++visits <= 20000 && !entry.isSymbolicLink(), 'Evidence tree exceeds bounds or contains a symlink.')
            const file = path.join(location, entry.name)
            if (entry.isDirectory()) inspect(file, depth + 1)
            else if (entry.isFile() && entry.name === 'receipt.json') {
                const receipt = JSON.parse(boundedRead(file, 16 * 1024 * 1024))
                const key = receipt.phase + '/' + (receipt.engine ?? 'all')
                assert.ok(Object.hasOwn(requirements, key) && !found.has(key), 'Unknown or duplicate acceptance phase: ' + key)
                assert.equal(receipt.format, 2, 'Unsupported acceptance receipt format.')
                assert.equal(receipt.commit, commit, 'Acceptance receipt belongs to a different commit.')
                assert.equal(receipt.workingTreeDirty, false, 'Release evidence must come from a clean checkout.')
                assert.equal(receipt.sourceSha256, expectedSource, 'Acceptance source fingerprint differs.')
                assert.equal(JSON.stringify(receipt.sources), JSON.stringify(sources), 'Acceptance source inventory differs.')
                assert.equal(receipt.sourceUnchanged, true, 'Source changed during acceptance.')
                assert.equal(receipt.release, release, 'Acceptance asset release differs.')
                assert.equal(receipt.passed, true, 'Acceptance phase did not pass.')
                const environment = verifyAcceptanceEnvironment(receipt.environment, receipt.engine ?? null)
                assert.ok(Array.isArray(receipt.checks), 'Acceptance checks must be a list.')
                assert.deepEqual(receipt.checks.map(check => check.name), requirements[key], 'Acceptance checks are incomplete or reordered.')
                for (const check of receipt.checks) {
                    assert.ok(check.passed === true && check.code === 0 && check.signal === null
                        && check.expired === false && check.launchError === null, 'Acceptance check did not complete successfully.')
                    assert.ok(typeof check.log === 'string' && /^[a-zA-Z0-9._-]+\.log$/.test(check.log)
                        && check.log === check.name + '.log', 'Acceptance log must identify its own local check.')
                    assert.match(check.logSha256, hash, 'Acceptance log digest is invalid.')
                    assert.equal(digest(boundedRead(path.join(path.dirname(file), check.log), 128 * 1024 * 1024)),
                        check.logSha256, 'Acceptance log was modified.')
                    if (check.name === 'release-metadata') {
                        const names = ['release-metadata/build-provenance.json', 'release-metadata/sbom.cdx.json']
                        assert.deepEqual(Object.keys(check.artifacts ?? {}).sort(), names, 'Missing release metadata attachments.')
                        const documents = {}
                        for (const name of names) {
                            assert.match(check.artifacts[name], hash)
                            const source = path.join(path.dirname(file), name)
                            const bytes = boundedRead(source, 16 * 1024 * 1024)
                            assert.equal(digest(bytes), check.artifacts[name], 'Release metadata was modified.')
                            documents[name] = JSON.parse(bytes)
                            releaseMetadata[path.basename(name)] = { sha256: check.artifacts[name], source: path.relative(directory, source) }
                        }
                        const provenance = documents[names[0]]
                        assert.equal(provenance.format, 1)
                        assert.equal(provenance.signed, false, 'Local provenance must not claim a signature.')
                        assert.equal(provenance.commit, commit, 'Provenance commit differs.')
                        assert.equal(provenance.sourceSha256, expectedSource, 'Provenance source differs.')
                        assert.equal(provenance.release, release, 'Provenance release differs.')
                        assert.deepEqual(provenance.artifacts, assetHashes, 'Provenance sealed asset inventory differs.')
                        assert.equal(provenance.sbomSha256, check.artifacts[names[1]], 'SBOM is not bound to provenance.')
                        assert.equal(provenance.materials?.['resources/dist/asset-versions.json'], assetManifestSha256, 'Provenance seal identity differs.')
                        for (const name of ['composer.lock', 'package-lock.json']) {
                            assert.equal(provenance.materials?.[name], sources[name], 'Provenance lockfile identity differs.')
                        }
                        assert.equal(documents[names[1]].bomFormat, 'CycloneDX')
                        assert.equal(documents[names[1]].specVersion, '1.6')
                    }
                }
                if (receipt.phase === 'reproducibility') {
                    assert.equal(receipt.checks.length, 2, 'Reproducibility needs two complete builds.')
                    assert.equal(receipt.checks[1].identicalImmutableArtifacts, true, 'Builds were not identical.')
                    for (const check of receipt.checks) {
                        assert.equal(check.release, release, 'Rebuilt release differs.')
                        assert.ok(Number.isSafeInteger(check.artifacts) && check.artifacts > 0, 'Rebuilt artifact count is missing.')
                    }
                    assert.equal(receipt.checks[0].artifacts, receipt.checks[1].artifacts, 'Build artifact counts differ.')
                }
                found.set(key, { checks: receipt.checks.length, receiptSha256: digest(fs.readFileSync(file)), environment })
            } else assert.ok(entry.isFile(), 'Unexpected special evidence file.')
        }
    }
    inspect(path.resolve(directory))
    assert.deepEqual([...found.keys()].sort(), Object.keys(requirements).sort(), 'Missing required acceptance phases.')
    return { format: 1, commit, sourceSha256: expectedSource, release,
        automatedComplete: true, releaseComplete: false,
        scope: 'Source-matched automated core acceptance. Physical devices, deployment approval and private audit completion are separate.',
        releaseMetadata,
        phases: Object.fromEntries([...found].sort(([left], [right]) => left.localeCompare(right))) }
}
