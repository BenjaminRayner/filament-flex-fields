import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp, mkdir, writeFile, rm, symlink } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { renderRecipeBlocks } from '../../scripts/generate-recipe-docs.mjs'

test('documentation renders actual recipe source and rejects malformed or escaped source references', async () => {
    const root = await mkdtemp(join(tmpdir(), 'fff-recipe-docs-'))
    try {
        await mkdir(join(root, 'resources/recipes/example'), { recursive: true })
        await writeFile(join(root, 'resources/recipes/example/Field.php'), '<?php\nreturn "actual shipped source";\n')
        await writeFile(join(root, 'outside.php'), '<?php secret();')
        const block = path => `{/* recipe-source: ${path} */}\n\`\`\`php app/Field.php\nstale copy\n\`\`\`\n{/* /recipe-source */}`
        const rendered = await renderRecipeBlocks(block('resources/recipes/example/Field.php'), root)
        assert.match(rendered, /actual shipped source/)
        assert.doesNotMatch(rendered, /stale copy/)
        assert.equal(await renderRecipeBlocks(rendered, root), rendered)
        await assert.rejects(renderRecipeBlocks('{/* recipe-source: broken */}', root), /Malformed/)
        await assert.rejects(renderRecipeBlocks(block('resources/recipes/../../outside.php'), root), /outside/)
        await symlink(join(root, 'outside.php'), join(root, 'resources/recipes/example/linked.php'))
        await assert.rejects(renderRecipeBlocks(block('resources/recipes/example/linked.php'), root), /outside/)
    } finally {
        await rm(root, { recursive: true, force: true })
    }
})
