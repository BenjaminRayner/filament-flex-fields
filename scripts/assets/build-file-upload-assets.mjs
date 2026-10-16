import fs from 'node:fs'
import path from 'node:path'

const root = path.resolve(import.meta.dirname, '../..')
const source = path.join(root, 'resources/images/file-upload/icons')
const target = path.join(root, 'resources/dist/assets/file-upload/icons')
fs.mkdirSync(target, { recursive: true })
const files = fs.readdirSync(source).filter(name => name !== '.DS_Store' && !name.startsWith('._')).sort()
for (const file of files) {
    if (!/^(?:color|line)-[a-z]+\.svg$/.test(file)) throw new Error(`Unexpected file icon: ${file}`)
    fs.copyFileSync(path.join(source, file), path.join(target, file))
}
for (const file of fs.readdirSync(target)) {
    if (/\.svg$/.test(file) && !files.includes(file)) fs.unlinkSync(path.join(target, file))
}
console.log('Built the component-owned file icons without modifying their artwork.')
