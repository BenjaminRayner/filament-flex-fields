import { createHash } from 'node:crypto'
import { readFile, realpath, stat, writeFile } from 'node:fs/promises'
import { dirname, resolve, sep } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..')
const canonicalRoot = await realpath(root)
const auditPath = '.internal/core-architecture-audit-2026-10-09.md'
const ledgerPath = '.internal/core-audit-coverage.json'
const hash = value => createHash('sha256').update(value).digest('hex')
const audit = await readFile(resolve(root, auditPath), 'utf8')
// Implementation reports are not the requirements baseline. Historical evidence
// is retained in the audit, but must never count as proof of a new implementation.
const baseline = audit.split(/^## 15\./m)[0].trimEnd()
const requiredSections = new Set([2, 4, 5, 6, 7, 8, 10, 11, 13, 14])
const findings = new Map()
const sections = new Map()
const headings = [...baseline.matchAll(/^(#{2,3}) (.+)$/gm)]

for (let index = 0; index < headings.length; index++) {
    const heading = headings[index]
    const finding = heading[2].match(/^([FK]\d{2})\b/)
    const section = heading[1] === '##' ? heading[2].match(/^(\d+)\./) : null
    if (!finding && (!section || !requiredSections.has(Number(section[1])))) continue
    let end = baseline.length
    for (let next = index + 1; next < headings.length; next++) {
        if (finding || headings[next][1] === '##') { end = headings[next].index; break }
    }
    const source = baseline.slice(heading.index, end).trimEnd()
    const id = finding ? finding[1] : `S${section[1].padStart(2, '0')}`
    const item = { id, title: heading[2], sourceSha256: hash(source), source }
    ;(finding ? findings : sections).set(id, item)
}

const expectedFindings = [
    ...Array.from({ length: 22 }, (_, index) => `F${String(index + 1).padStart(2, '0')}`),
    ...Array.from({ length: 10 }, (_, index) => `K${String(index + 1).padStart(2, '0')}`),
]
if (expectedFindings.some(id => !findings.has(id)) || findings.size !== 32 || sections.size !== requiredSections.size) {
    throw new Error('The audit requirement structure changed. Review coverage before proceeding.')
}

const requirements = [...findings.values(), ...sections.values()]
if (process.argv.includes('--init')) {
    // Creation is deliberately exclusive: never silently reset verified or pending work.
    await writeFile(resolve(root, ledgerPath), `${JSON.stringify({
        format: 1,
        audit: auditPath,
        baselineSha256: hash(baseline),
        policy: 'Every finding, contract and acceptance section remains mandatory. Tracking coverage is not implementation completion. No waived or omitted statuses.',
        items: requirements.map(item => ({ ...item, owner: 'core-platform', stages: ['E0'], status: 'pending', implementation: [], evidence: [], remaining: 'Full source requirement and its acceptance criteria.' })),
    }, null, 2)}\n`, { flag: 'wx' })
}

const ledger = JSON.parse(await readFile(resolve(root, ledgerPath), 'utf8'))
if (ledger.format !== 1 || ledger.audit !== auditPath || ledger.baselineSha256 !== hash(baseline) || !Array.isArray(ledger.items)) {
    throw new Error('Coverage baseline drifted. Reconcile the ledger explicitly; do not overwrite or silently drop requirements.')
}
const actual = new Map()
const statuses = { pending: 0, 'in-progress': 0, verified: 0 }
async function sourcePath(reference) {
    if (typeof reference !== 'string' || reference.length === 0 || reference.length > 512
        || reference.startsWith('/') || reference.includes('\\') || reference.includes('\0')
        || reference.split('/').some(part => part === '.' || part === '..')) {
        throw new Error('Implementation evidence needs bounded repository-relative source paths.')
    }
    const path = await realpath(resolve(root, reference))
    if (!path.startsWith(`${canonicalRoot}${sep}`)) throw new Error(`Evidence source escaped the repository: ${reference}`)
    return path
}
for (const item of ledger.items) {
    if (!item || typeof item.id !== 'string' || actual.has(item.id) || !Object.hasOwn(statuses, item.status)) {
        throw new Error('Invalid, duplicated, omitted or waived coverage entry.')
    }
    actual.set(item.id, item)
    statuses[item.status]++
    if (typeof item.owner !== 'string' || item.owner.trim() === '' || !Array.isArray(item.stages)
        || item.stages.length === 0 || new Set(item.stages).size !== item.stages.length
        || item.stages.some(stage => !/^E[0-6]$/.test(stage))) {
        throw new Error(`Missing subsystem or implementation stage: ${item.id}`)
    }
    if (!Array.isArray(item.implementation) || !Array.isArray(item.evidence) || typeof item.remaining !== 'string'
        || item.evidence.some(value => typeof value !== 'string' || value.trim() === '' || value.length > 8192)
        || new Set(item.implementation).size !== item.implementation.length) {
        throw new Error(`Missing implementation/evidence/remaining contract: ${item.id}`)
    }
    for (const reference of item.implementation) {
        const path = await sourcePath(reference)
        if (item.status === 'verified' && !(await stat(path)).isFile()) {
            throw new Error(`Verified implementation must identify concrete source files: ${item.id}`)
        }
    }
    if (item.status === 'verified' && (item.implementation.length === 0 || item.evidence.length === 0 || item.remaining !== '')) {
        throw new Error(`Cannot close ${item.id} without implementation, evidence and no remaining criteria.`)
    }
    if (item.status === 'verified') {
        const fingerprints = item.verifiedSources
        if (!fingerprints || typeof fingerprints !== 'object' || Array.isArray(fingerprints)
            || item.implementation.some(reference => !Object.hasOwn(fingerprints, reference))) {
            throw new Error(`Verified source fingerprints are missing: ${item.id}`)
        }
        for (const [reference, digest] of Object.entries(fingerprints)) {
            if (typeof digest !== 'string' || !/^[a-f0-9]{64}$/.test(digest)
                || hash(await readFile(await sourcePath(reference))) !== digest) {
                throw new Error(`Verified source changed; reverify or reopen ${item.id}: ${reference}`)
            }
        }
    }
}
for (const requirement of requirements) {
    const item = actual.get(requirement.id)
    if (!item || item.title !== requirement.title || item.sourceSha256 !== requirement.sourceSha256 || item.source !== requirement.source) {
        throw new Error(`Requirement content is missing or changed: ${requirement.id}`)
    }
}
if (actual.size !== requirements.length) throw new Error('Unknown coverage entries require an explicit audit update.')

console.log(JSON.stringify({ findings: 22, contracts: 10, acceptanceSections: sections.size,
    allSourceRequirementsTracked: true, statuses, complete: statuses.verified === requirements.length }))
if (process.argv.includes('--require-complete') && statuses.verified !== requirements.length) {
    throw new Error('The complete audit has not been implemented and verified. Release completion is blocked.')
}
