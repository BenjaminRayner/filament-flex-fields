import fs from 'node:fs'
import path from 'node:path'

export const FLEX_SDK_SPECIFIER = '@bjanczak/flex-fields/sdk/v1'
const exports = ['createResourceScope', 'createOwnedSubscriptions', 'createLifecycleEvents', 'isMountedFactoryReady', 'isAssetElementActive', 'waitForAssetActivation', 'createAssetSurface', 'createAppearanceNamespace']
const privateRoot = fs.realpathSync(path.resolve(import.meta.dirname, '../../resources/js'))

/** Tiny ABI facade: implementations remain in the installed document runtime.
 * No import map, host URL, eval, source rewriting or copied private JS is needed.
 */
export function createFlexSdkPlugin() {
    return { name: 'fff-sdk-v1', setup(build) {
        if (build.initialOptions.metafile !== true) throw Error('FLEX SDK externalization requires esbuild metafile: true.')
        if (build.initialOptions.write !== false) throw Error('FLEX SDK externalization requires esbuild write: false; persist outputs only after successful verification.')
        build.onResolve({ filter: /^@bjanczak\/flex-fields\/sdk(?:\/|$)/ }, args => {
            if (args.path !== FLEX_SDK_SPECIFIER) return { errors: [{ text: `Unsupported FLEX SDK import: ${args.path}; use ${FLEX_SDK_SPECIFIER}` }] }
            return { path: 'v1', namespace: 'fff-runtime-sdk' }
        })
        build.onLoad({ filter: /^v1$/, namespace: 'fff-runtime-sdk' }, () => ({ loader: 'js', contents: `
const sdk = () => {
    const api = globalThis[Symbol.for('fff.sdk.v1')];
    if (!api || api.sdkABI !== 1) throw Error('FLEX SDK ABI 1 is not installed. Mount through the FLEX asset consumer boundary.');
    return api;
};
export const sdkABI = 1;
${exports.map(name => `export const ${name} = (...args) => {
    const fn = sdk().${name};
    if (typeof fn !== 'function') throw Error('FLEX SDK feature ${name} is unavailable; upgrade the installed runtime and reload.');
    return fn(...args);
};`).join('\n')}
` }))
        build.onEnd(result => {
            if (result.errors.length || !result.metafile) return
            try { assertFlexSdkExternalization(result.metafile, { workingDirectory: build.initialOptions.absWorkingDir ?? process.cwd() }) }
            catch (error) { return { errors: [{ text: error.message }] } }
        })
    } }
}

/** Refuse actual installed FLEX inputs, including canonical symlink aliases.
 * Full esbuild metafiles are mandatory. Output-only metadata cannot prove that
 * an independently built package did not embed another runtime implementation.
 */
export function assertFlexSdkExternalization(metafile, { workingDirectory }) {
    if (!metafile.inputs || typeof metafile.inputs !== 'object' || Array.isArray(metafile.inputs)
        || Object.keys(metafile.inputs).length > 16384) throw Error('Invalid FLEX SDK build input metadata.')
    for (const input of Object.keys(metafile.inputs)) {
        if (input === 'fff-runtime-sdk:v1') continue
        if (input.startsWith('fff-runtime-sdk:')) throw Error('Unsupported FLEX SDK facade input.')
        if (input.length > 4096 || input.includes('\0')) throw Error('Invalid FLEX SDK build input path.')
        const target = path.resolve(workingDirectory, input)
        const canonical = fs.existsSync(target) ? fs.realpathSync(target) : target
        if (canonical === privateRoot || canonical.startsWith(`${privateRoot}${path.sep}`)) {
            throw Error(`Do not bundle FLEX private implementation (${input}); import ${FLEX_SDK_SPECIFIER} with createFlexSdkPlugin().`)
        }
    }
}
