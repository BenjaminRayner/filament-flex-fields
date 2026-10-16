import { prepareCatalogSelectConfiguration } from '../../select/catalogs/select-options-registry.js'
import { createSelectConfiguration } from '../../select/adapters/alpine/configuration.js'
import { optionalMixinStubs } from '../../select/adapters/alpine/optional-defaults.js'
import { createSelectMenu } from '../../select/adapters/alpine/menu-configuration.js'
import { createSelectCapabilitiesMixin } from '../../select/adapters/alpine/optional-capabilities.js'
import { createSelectEngineBindingMixin } from '../../select/adapters/alpine/engine-binding.js'
import { createSelectReactiveSyncMixin } from '../../select/adapters/alpine/reactive-sync.js'
import { createSelectLifecycleMixin } from '../../select/adapters/alpine/lifecycle.js'
import { createHeadlessComboboxKeyboardMixin } from './headless-combobox-keyboard.js'
import { createHeadlessComboboxScrollVirtMixin } from './headless-combobox-scroll-virt.js'
import { createHeadlessComboboxSsrHandoffMixin } from './headless-combobox-ssr-handoff.js'
import { createHeadlessComboboxEmptyUiMixin } from './headless-combobox-empty-ui.js'
import { createHeadlessComboboxEngineSyncMixin } from './headless-combobox-engine-sync.js'
import { createHeadlessComboboxMenuPositionMixin } from './headless-combobox-menu-position.js'
import { createHeadlessComboboxOpenCloseMixin } from './headless-combobox-open-close.js'
import { createHeadlessComboboxSelectionMixin } from './headless-combobox-selection.js'
import { createPendingPickerOpenMixin } from '../../overlay/pending-open-intent.js'

/** Compatibility entry point: configuration, engine and effects have separate owners. */
export default function headlessComboboxAlpine(userConfig = {}) {
    const configuration = createSelectConfiguration(prepareCatalogSelectConfiguration(userConfig))
    const selectMenu = createSelectMenu()
    return {
        ...selectMenu,
        ...optionalMixinStubs,
        ...createPendingPickerOpenMixin(),
        ...createHeadlessComboboxKeyboardMixin(),
        ...createHeadlessComboboxScrollVirtMixin(),
        ...createHeadlessComboboxSsrHandoffMixin(),
        ...createHeadlessComboboxEmptyUiMixin(),
        ...createHeadlessComboboxEngineSyncMixin(),
        ...createHeadlessComboboxMenuPositionMixin({ selectMenu }),
        ...createHeadlessComboboxOpenCloseMixin(),
        ...createHeadlessComboboxSelectionMixin(),
        ...configuration.state,
        ...createSelectCapabilitiesMixin(configuration),
        ...createSelectEngineBindingMixin(configuration),
        ...createSelectReactiveSyncMixin(),
        ...createSelectLifecycleMixin(configuration),
    }
}
