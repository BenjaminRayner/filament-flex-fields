import assert from 'node:assert/strict'
import { createHash } from 'node:crypto'
import { execFileSync } from 'node:child_process'
import fs from 'node:fs'
import path from 'node:path'

export const digest = bytes => createHash('sha256').update(bytes).digest('hex')
export const browserProof = name => name.endsWith('-browser.mjs') || name === 'core-overlay-interactions.mjs'
const sourcePattern = /^(src\/|resources\/(?!dist\/)|stubs\/|scripts\/|tests\/|config\/|\.github\/|\.gitattributes$|tsconfig\.json$|composer\.(json|lock)$|package(-lock)?\.json$|VERSION$|SECURITY\.md$|SUPPORT\.md$|phpstan(?:-baseline)?\.neon$)/

export function sourceSnapshot(root) {
    const names = [...new Set(execFileSync('git', ['ls-files', '-z', '--cached', '--others', '--exclude-standard'], { cwd: root })
        .toString().split('\0'))].filter(name => sourcePattern.test(name) && fs.existsSync(path.join(root, name))).sort()
    return Object.fromEntries(names.map(name => {
        const file = path.join(root, name)
        assert.ok(fs.lstatSync(file).isFile(), 'Acceptance source must be a regular file: ' + name)
        return [name, digest(fs.readFileSync(file))]
    }))
}

/** Shared executable requirements; private audit history is deliberately not an input. */
export function acceptanceRequirements(root) {
    const inventory = fs.readdirSync(path.join(root, 'scripts/compatibility')).filter(name => /^core-.+\.(mjs|php)$/.test(name)).sort()
    const contracts = inventory.filter(name => name !== 'core-framework-host.php' && !browserProof(name))
    const browsers = inventory.filter(name => browserProof(name) && name !== 'core-local-ownership-browser.mjs')
    return {
        'contracts/all': ['architecture-inventory', 'css-cascade', ...contracts, 'component-schema-v4.php',
            'esbuild-package-contract.mjs', 'overlay-retarget.mjs', 'css-source-contract', 'security-policy', 'public-api', 'build-parity', 'budgets', 'release-metadata'],
        'regression/all': ['php-regression', 'js-regression', 'static-analysis'],
        'reproducibility/all': ['clean-build-one', 'clean-build-two'],
        'performance/chromium': ['registry-performance', 'core-local-ownership-browser.mjs', 'startup-profile', 'startup-cpu4-profile'],
        ...Object.fromEntries(['chromium', 'firefox', 'webkit'].flatMap(engine => [
            ['browser/' + engine, [...browsers, 'signed-delivery-render-context', 'native-startup-lifecycle']],
            ['composer/' + engine, ['core-author-tooling.php']],
        ])),
    }
}

export function reportDestination(root, filename, args = process.argv.slice(2)) {
    let destination = path.join(root, 'build/core-reports', filename), provided = false, check = false
    for (const arg of args) {
        if (arg === '--check' && !check) { check = true; continue }
        if (arg.startsWith('--output=') && arg.length > 9 && !provided) {
            destination = path.resolve(root, arg.slice(9)); provided = true; continue
        }
        throw Error('Unknown or repeated report argument: ' + arg)
    }
    return { destination, check }
}

export function verifySecurityPolicy(version, policy) {
    assert.match(version, /^[1-9]\d*\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/, 'VERSION must name a supported SemVer release.')
    const major = version.split('.')[0]
    const rows = policy.split('\n').map(line => line.match(/^\|\s*(\d+)\.x\s*\|\s*([^|]+)\|$/)).filter(Boolean)
    const current = rows.filter(row => row[1] === major)
    assert.equal(current.length, 1, 'Security policy must declare exactly one row for the current major.')
    assert.match(current[0][2], /Security updates for the latest \d+\.x patch/i, 'Current major must receive security updates.')
    assert.ok(!/unreleased|development|not yet|unsupported|no longer/i.test(current[0][2]), 'Current released major cannot be described as unreleased or unsupported.')
    assert.ok(current[0][2].includes(major + '.x'), 'Security support must reference the current major.')
    return { valid: true, major: Number(major), version }
}
