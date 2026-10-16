import fs from 'node:fs'
import path from 'node:path'
import { randomUUID } from 'node:crypto'

const leases = new WeakMap()
const inspect = target => {
    const stat = fs.lstatSync(target)
    if (!stat.isFile() || stat.isSymbolicLink() || stat.size > 256) throw new Error('Unsafe component build lock.')
    const bytes = fs.readFileSync(target)
    const record = JSON.parse(bytes)
    if (record.format !== 1 || !Number.isSafeInteger(record.pid) || record.pid < 1
        || typeof record.token !== 'string' || !/^[a-f0-9-]{36}$/.test(record.token)) throw new Error('Invalid component build lock.')
    return { stat, record, bytes }
}

/** A killed local build leaves a recoverable lock; a live build is never stolen. */
export async function withComponentOutputLock(root, action) {
    root = fs.realpathSync(root)
    const directory = path.join(root, 'build/asset-analysis')
    fs.mkdirSync(directory, { recursive: true })
    if (fs.realpathSync(directory) !== directory) throw new Error('Unsafe component build metadata directory.')
    const target = path.join(directory, '.component-output.lock')
    let descriptor
    for (let attempt = 0; attempt < 2; attempt++) {
        try { descriptor = fs.openSync(target, 'wx', 0o600); break } catch (error) {
            if (error.code !== 'EEXIST') throw error
            const prior = inspect(target)
            try { process.kill(prior.record.pid, 0) } catch (failure) {
                if (failure.code !== 'ESRCH') throw new Error('Cannot establish whether a component build still owns its lock.')
                const current = inspect(target)
                if (current.stat.dev !== prior.stat.dev || current.stat.ino !== prior.stat.ino
                    || current.bytes.compare(prior.bytes) !== 0) throw new Error('Component build lock changed during recovery.')
                fs.unlinkSync(target)
                continue
            }
            throw new Error('A live process already owns the component output build.')
        }
    }
    if (descriptor === undefined) throw new Error('Cannot acquire component output lock.')
    const record = { format: 1, pid: process.pid, token: randomUUID() }
    const lease = Object.freeze({})
    const acquired = fs.fstatSync(descriptor)
    let recorded = false
    try {
        fs.writeFileSync(descriptor, JSON.stringify(record))
        recorded = true
        leases.set(lease, root)
        return await action(lease)
    } finally {
        leases.delete(lease)
        fs.closeSync(descriptor)
        const current = fs.lstatSync(target)
        if (current.isSymbolicLink() || current.dev !== acquired.dev || current.ino !== acquired.ino) throw new Error('Component output lock ownership changed.')
        if (recorded) {
            const own = inspect(target)
            if (own.record.pid !== record.pid || own.record.token !== record.token) throw new Error('Component output lock ownership changed.')
        }
        fs.unlinkSync(target)
    }
}

export function assertComponentOutputLease(root, lease) {
    if (!lease || leases.get(lease) !== fs.realpathSync(root)) throw new Error('Component cleanup requires its live exclusive build lease.')
}
