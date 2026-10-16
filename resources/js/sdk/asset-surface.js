import { runtimeReadiness } from '../runtime/alpine/runtime-readiness.js'

/** The controller belongs to the document runtime, never to a copied author bundle. */
export async function createAssetSurface(root, { signal = null, parent = null } = {}) {
    const runtime = await runtimeReadiness().wait({ signal })
    if (signal?.aborted || root?.isConnected === false) throw new DOMException('FLEX surface lifetime ended.', 'AbortError')
    if (!runtime?.registerAssetSurface || runtime.isDisposed?.()) throw Error('FLEX asset surface adapter is unavailable.')
    return runtime.registerAssetSurface(root, { signal, parent })
}
