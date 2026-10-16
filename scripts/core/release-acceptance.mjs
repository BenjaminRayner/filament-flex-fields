import assert from 'node:assert/strict'
import { createHash } from 'node:crypto'
import { spawn, execFileSync } from 'node:child_process'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'

const root = path.resolve(import.meta.dirname, '../..')
const options = Object.fromEntries(process.argv.slice(2).map(argument => {
    const match = argument.match(/^--(phase|browser|output)=(.+)$/)
    assert.ok(match, `Unknown acceptance argument: ${argument}`)
    return [match[1], match[2]]
}))
const phase = options.phase ?? 'contracts'
const engine = options.browser ?? process.env.FFF_BROWSER ?? 'chromium'
assert.ok(['contracts', 'browser', 'composer', 'performance', 'reproducibility', 'regression'].includes(phase), 'Unknown acceptance phase')
assert.ok(['chromium', 'firefox', 'webkit'].includes(engine), 'Unknown browser engine')
const output = path.resolve(root, options.output ?? `build/core-acceptance/${phase}-${engine}`)
assert.notEqual(output, root, 'Acceptance logs need their own directory')
fs.mkdirSync(output, { recursive: true })
const digest = bytes => createHash('sha256').update(bytes).digest('hex')
const sourceFiles = () => [...new Set(execFileSync('git', ['ls-files', '-z', '--cached', '--others', '--exclude-standard'], { cwd: root }).toString().split('\0'))]
    .filter(name => /^(src\/|resources\/(?!dist\/)|stubs\/|scripts\/|tests\/|config\/|\.github\/|\.gitattributes$|composer\.(json|lock)$|package(-lock)?\.json$)/.test(name))
    .filter(name => fs.existsSync(path.join(root, name))).sort()
const sourceSnapshot = () => Object.fromEntries(sourceFiles().map(name => [name, digest(fs.readFileSync(path.join(root, name)))]))
const sources = sourceSnapshot()
const sourceSha256 = digest(JSON.stringify(sources))
const php = process.env.PHP_BINARY ?? 'php'
const node = process.execPath
const inventory = fs.readdirSync(path.join(root, 'scripts/compatibility')).filter(name => /^core-.+\.(mjs|php)$/.test(name)).sort()
const helpers = new Set(['core-framework-host.php'])
const browserProof = name => name.endsWith('-browser.mjs') || name === 'core-overlay-interactions.mjs'
const performance = new Set(['core-local-ownership-browser.mjs'])
const commands = []
const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'fff-core-acceptance-'))
process.on('exit', () => fs.rmSync(temporary, { recursive: true, force: true }))
if (phase === 'contracts') fs.mkdirSync(path.join(temporary, 'esbuild-fixture'))
const add = (name, command, env = {}) => commands.push({ name, command, env })
const probe = (name, args = []) => add(name, [name.endsWith('.php') ? php : node,
    ...(name.endsWith('.mjs') ? ['--expose-gc'] : []), `scripts/compatibility/${name}`, ...args])
if (phase === 'contracts') {
    // Public checkouts omit private audit evidence. Derive the reports required
    // by executable contracts from this checkout; verify retained reports for drift.
    for (const [name, script, report] of [
        ['architecture-inventory', 'scripts/core/architecture-inventory.mjs', '.internal/core-architecture-inventory.json'],
        ['css-cascade', 'scripts/core/css-cascade-report.mjs', '.internal/core-css-cascade-report.json'],
    ]) add(name, [node, script, ...(fs.existsSync(path.join(root, report)) ? ['--check'] : [])])
    for (const name of inventory) if (!helpers.has(name) && !browserProof(name)) probe(name)
    probe('component-schema-v4.php'); probe('esbuild-package-contract.mjs', [path.join(temporary, 'esbuild-fixture')]); probe('overlay-retarget.mjs')
    add('css-source-contract', [node, 'scripts/assets/css-source-contract.mjs'])
    add('build-parity', [php, 'scripts/assets/validate-build.php'])
    add('budgets', [node, 'scripts/check-bundle-budgets.mjs'])
} else if (phase === 'browser') {
    for (const name of inventory) if (browserProof(name) && !performance.has(name)) probe(name)
    add('signed-delivery-render-context', [node, 'scripts/compatibility/core-render-context-browser.mjs'], { FLEX_CORE_DELIVERY_PROBE: '1' })
} else if (phase === 'composer') {
    probe('core-author-tooling.php', ['--composer-install', '--composer-browser', '--filament-floor'])
} else if (phase === 'performance') {
    assert.equal(engine, 'chromium', 'CPU/heap performance budgets use the documented Chromium CDP profile')
    add('registry-performance', [php, 'scripts/core/registry-benchmark.php'])
    probe('core-local-ownership-browser.mjs')
} else if (phase === 'regression') {
    add('php-regression', [php, '-d', 'memory_limit=2G', 'vendor/bin/pest', '--compact'])
    add('js-regression', ['npm', 'run', 'test:js'])
    add('static-analysis', [php, 'vendor/bin/phpstan', 'analyse', '--memory-limit=512M'])
} else {
    add('clean-build-one', ['npm', 'run', 'build'])
    add('clean-build-two', ['npm', 'run', 'build'])
}
assert.ok(commands.length > 0)
const receipt = { format: 1, phase, engine: ['browser', 'composer', 'performance'].includes(phase) ? engine : null,
    sourceSha256, sources, commit: execFileSync('git', ['rev-parse', 'HEAD'], { cwd: root, encoding: 'utf8' }).trim(),
    workingTreeDirty: execFileSync('git', ['status', '--porcelain'], { cwd: root, encoding: 'utf8' }).length > 0,
    environment: { node: process.version, platform: process.platform, architecture: process.arch },
    scope: 'Executed automated core acceptance only. Physical devices and external CI execution remain separate release requirements.',
    physicalDevice: false, releaseComplete: false, passed: false, checks: [] }
const save = () => fs.writeFileSync(path.join(output, 'receipt.json'), JSON.stringify(receipt, null, 2) + '\n')
let firstBuild = null
save()
for (const { name, command, env } of commands) {
    const log = path.join(output, `${name}.log`)
    const fd = fs.openSync(log, 'w'), started = performanceNow()
    process.stdout.write(`Checking ${phase}/${engine}: ${name}\n`)
    const result = await new Promise(resolve => {
        const child = spawn(command[0], command.slice(1), { cwd: root, env: { ...process.env, FFF_BROWSER: engine, ...env }, stdio: ['ignore', fd, fd] })
        let expired = false, launchError = null
        let forcedTermination = null
        const deadline = setTimeout(() => {
            expired = true; child.kill('SIGTERM')
            forcedTermination = setTimeout(() => child.kill('SIGKILL'), 5000)
        }, 15 * 60 * 1000)
        child.on('error', error => { launchError = error.message })
        child.on('close', (code, signal) => { clearTimeout(deadline); clearTimeout(forcedTermination); resolve({ code, signal, expired, launchError }) })
    })
    fs.closeSync(fd)
    const check = { name, ...result, milliseconds: Math.round(performanceNow() - started), log: path.basename(log), logSha256: digest(fs.readFileSync(log)), passed: result.code === 0 && !result.expired && result.launchError === null }
    if (check.passed && phase === 'reproducibility') {
        const seal = JSON.parse(fs.readFileSync(path.join(root, 'resources/dist/asset-versions.json')))
        const artifacts = Object.fromEntries(Object.keys(seal.assets).sort().map(name => [name, digest(fs.readFileSync(path.join(root, 'resources/dist', name)))]))
        const build = { release: seal.release, artifacts }
        if (firstBuild) {
            check.passed = JSON.stringify(firstBuild) === JSON.stringify(build)
            check.identicalImmutableArtifacts = check.passed
        } else firstBuild = build
        check.release = seal.release
        check.artifacts = Object.keys(artifacts).length
    }
    receipt.checks.push(check); save()
    if (!check.passed) {
        process.stderr.write(`Acceptance failed at ${name}. See ${log}\n`)
        // Keep the full evidence artifact and expose the bounded failure detail
        // in CI itself, where archive downloads may be unavailable.
        const failure = fs.readFileSync(log)
        if (failure.length > 32768) process.stderr.write('Failure log truncated to the last 32 KiB.\n')
        process.stderr.write(failure.subarray(-32768))
        process.stderr.write('\n')
        process.exitCode = 1
        break
    }
}
// Source drift during the run cannot certify a different working tree.
receipt.sourceUnchanged = JSON.stringify(sourceSnapshot()) === JSON.stringify(sources)
receipt.passed = receipt.checks.length === commands.length && receipt.checks.every(check => check.passed) && receipt.sourceUnchanged
receipt.release = JSON.parse(fs.readFileSync(path.join(root, 'resources/dist/asset-versions.json'))).release
save()
if (!receipt.passed) process.exitCode = 1
process.stdout.write(JSON.stringify({ phase, engine: receipt.engine, checks: receipt.checks.length, passed: receipt.passed, sourceSha256, receipt: path.join(output, 'receipt.json'), releaseComplete: false }) + '\n')

function performanceNow() { return globalThis.performance.now() }
