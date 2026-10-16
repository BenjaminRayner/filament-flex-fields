import { awaitSurfaceFactories as awaitDomSurfaceFactories } from '../surfaces/factory-readiness.js'
import { isAssetElementActive } from '../lifecycle/asset-activation.js'
import { changesFilamentActivation } from './activation-mutation.js'

/** Native compatibility adapter; the shared factory barrier owns no framework events. */
export function awaitSurfaceFactories(root, options) {
    return awaitDomSurfaceFactories(root, {
        navigationEvent: 'livewire:navigated', closeEvent: 'modal-closed',
        changesActivation: changesFilamentActivation,
        ...options,
        isActive: options.isActive ?? (element => isAssetElementActive(element, options)),
    })
}
