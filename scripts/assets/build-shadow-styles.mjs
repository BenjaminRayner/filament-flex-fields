import fs from 'node:fs'
import path from 'node:path'
import { transform } from 'esbuild'
const root = path.resolve(import.meta.dirname, '../..')
const source = path.join(root, 'resources/css/shadow/emoji-picker.css')
const destination = path.join(root, 'resources/dist/assets/emoji-picker/shadow.css')
const result = await transform(fs.readFileSync(source, 'utf8'), { loader: 'css', minify: true })
fs.mkdirSync(path.dirname(destination), { recursive: true })
const temporary = `${destination}.${process.pid}.tmp`
try { fs.writeFileSync(temporary, result.code); fs.renameSync(temporary, destination) }
finally { if (fs.existsSync(temporary)) fs.unlinkSync(temporary) }
console.log('Built standalone emoji Shadow DOM stylesheet.')
