import assert from 'node:assert/strict'
import test from 'node:test'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { compileFirstPaintOwners } from '../../scripts/assets/first-paint-esbuild-plugin.mjs'

const root = path.resolve(import.meta.dirname, '../..')
test('real first-paint builds ignore conflicting application compiler settings and track their own explicit policy', async t => {
    const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'fff-compiler-isolation-'))
    t.after(() => fs.rmSync(temporary, { recursive: true, force: true }))
    const outputs = []
    for (const strict of [true, false]) {
        const parent = path.join(temporary, strict ? 'strict-host' : 'loose-host'), fixture = path.join(parent, 'package')
        fs.mkdirSync(fixture, { recursive: true })
        fs.writeFileSync(path.join(parent, 'tsconfig.json'), JSON.stringify({ compilerOptions: { strict, alwaysStrict: strict, useDefineForClassFields: !strict } }))
        fs.cpSync(path.join(root, 'resources/js'), path.join(fixture, 'resources/js'), { recursive: true })
        fs.mkdirSync(path.join(fixture, 'scripts/assets'), { recursive: true })
        fs.copyFileSync(path.join(root, 'scripts/assets/first-paint-esbuild-plugin.mjs'), path.join(fixture, 'scripts/assets/first-paint-esbuild-plugin.mjs'))
        fs.copyFileSync(path.join(root, 'tsconfig.json'), path.join(fixture, 'tsconfig.json'))
        fs.symlinkSync(path.join(root, 'node_modules'), path.join(fixture, 'node_modules'), 'dir')
        const built = await compileFirstPaintOwners(fixture)
        outputs.push(built.map(owner => ({ version: owner.contract.version, file: owner.filename, bytes: Buffer.from(owner.outputFiles[0].contents).toString('base64') })))
        if (!strict) {
            fs.writeFileSync(path.join(fixture, 'tsconfig.json'), JSON.stringify({ compilerOptions: { alwaysStrict: false, useDefineForClassFields: true } }))
            const changed = await compileFirstPaintOwners(fixture)
            assert.notEqual(changed[0].contract.version, built[0].contract.version, 'Compiler policy must participate in the first-paint protocol identity.')
            assert.notEqual(changed[0].filename, built[0].filename)
        }
    }
    assert.deepEqual(outputs[0], outputs[1], 'An application tsconfig changed a supposedly portable package build.')
})
