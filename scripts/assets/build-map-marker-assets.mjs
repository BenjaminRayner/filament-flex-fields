import fs from 'node:fs'
import path from 'node:path'

// User-supplied artwork; copy unchanged without a runtime resize or remote image host.
const root=path.resolve(import.meta.dirname,'../..')
for(const variant of ['car','scooter']) {
    const source=path.join(root,`resources/images/location/${variant}.png`)
    const target=path.join(root,`resources/dist/assets/location/delivery-${variant}.png`)
    fs.mkdirSync(path.dirname(target),{recursive:true})
    fs.copyFileSync(source,target)
}
console.log('Built the component-owned delivery vehicle images.')
