#!/usr/bin/env node
/**
 * Audit v4's typed composition graph, rendered ownership, CSS, artifacts and docs.
 * Component aliases inherit assets; per-class filename/lazy-intersection guesses
 * from the pre-v4 runtime are not a valid contract.
 */
import { spawnSync } from 'node:child_process'
import path from 'node:path'

const root = path.resolve(import.meta.dirname, '..')
const checks = [
    ['typed-schema-and-css-ownership', process.execPath, 'scripts/assets/audit-component-schema.mjs'],
    ['rendered-consumer-boundaries', process.env.PHP_BINARY ?? 'php', 'scripts/compatibility/schema-render-contract.php'],
    ['css-source-closure', process.execPath, 'scripts/assets/css-source-contract.mjs'],
    ['sealed-artifact-parity', process.env.PHP_BINARY ?? 'php', 'scripts/assets/validate-build.php'],
    ['component-documentation', process.env.PHP_BINARY ?? 'php', 'scripts/assets/audit-component-documentation.php'],
]
const results = checks.map(([name, executable, script]) => {
    const result = spawnSync(executable, [script], { cwd: root, encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 })
    return { name, passed: result.status === 0 && !result.error, code: result.status,
        output: result.stdout ?? '', error: result.error?.message ?? result.stderr ?? '' }
})
const passed = results.every(result => result.passed)
if (process.argv.includes('--json')) console.log(JSON.stringify({ passed, checks: results }, null, 2))
else for (const result of results) {
    console.log(`${result.passed ? 'PASS' : 'FAIL'}: ${result.name}`)
    process.stdout.write(result.output)
    if (result.error) process.stderr.write(result.error)
}
if (!passed) process.exitCode = 1
