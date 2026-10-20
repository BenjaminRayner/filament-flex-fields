import fs from 'node:fs'
import path from 'node:path'
import { capturePublicApi, comparePublicApi } from './public-api.mjs'
import { reportDestination } from './acceptance-contract.mjs'

const root = path.resolve(import.meta.dirname, '../..')
const { destination, check } = reportDestination(root, 'public-api-report.json')
if (check) throw Error('Public API is compared with its reviewed fixture on every run; --check is unnecessary.')
const snapshot = await capturePublicApi(root)
const baseline = JSON.parse(fs.readFileSync(path.join(root, 'tests/fixtures/core-public-api-v1.json')))
const report = comparePublicApi(baseline, snapshot)
fs.mkdirSync(path.dirname(destination), { recursive: true })
fs.writeFileSync(destination, JSON.stringify(report, null, 2) + '\n')
process.stdout.write(JSON.stringify({ compatible: report.compatible, phpTypes: Object.keys(snapshot.php.classes).length,
    sdkExports: Object.keys(snapshot.sdk.exports).length, breaking: report.breaking, additions: report.additions }) + '\n')
if (!report.compatible) process.exitCode = 1
