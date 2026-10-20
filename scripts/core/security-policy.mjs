import fs from 'node:fs'
import path from 'node:path'
import { verifySecurityPolicy } from './acceptance-contract.mjs'

const root = path.resolve(import.meta.dirname, '../..')
process.stdout.write(JSON.stringify(verifySecurityPolicy(
    fs.readFileSync(path.join(root, 'VERSION'), 'utf8').trim(),
    fs.readFileSync(path.join(root, 'SECURITY.md'), 'utf8'),
)) + '\n')
