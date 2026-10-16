import { createMediaOwner } from '../../media/lifecycle/owner.js'
import { publishOwnerData } from '../../runtime/alpine/owner-data.js'

/** A declared FLEX owner adapts the installed native transport without copying it. */
export default function fileUploadTransport(config) {
    const owner = createMediaOwner()
    let native = null
    let initialization = null

    return {
        state: config.state, pond: null, error: null, isInitializing: false,
        init() {
            if (native) return native.init.call(this)
            initialization ??= (async () => {
                const module = await import(/* @vite-ignore */ config.nativeComponentSrc)
                owner.assertActive()
                native = module.default(config)
                const additions = Object.fromEntries(Object.entries(native).filter(([key]) =>
                    !['state', 'init', 'destroy'].includes(key)))
                publishOwnerData(this, additions)
                await native.init.call(this)
                owner.assertActive()
            })()
            return initialization
        },
        destroy() {
            owner.dispose()
            native?.destroy.call(this)
        },
    }
}
