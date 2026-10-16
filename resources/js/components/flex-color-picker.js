import { mergeAlpineComponentData } from '../support/merge-alpine-component-data.js'
import { wireExclusiveFlexDropdown, resolveDropdownOwnerId } from '../core/flex-dropdown-coordinator.js'
import { createColorState } from '../color-picker/state.js'
import { createColorPopover } from '../color-picker/popover.js'
import { createColorChannels } from '../color-picker/channels.js'
import { colorRuntime } from '../color-picker/lifetime.js'
import { resolveColorPalette } from '../color-picker/palette.js'

export default function flexColorPickerFormComponent(configuration) {
    return mergeAlpineComponentData({
        ...configuration,
        __colorOwner: null,
        _pickerHostElement: null,
        __fffDropdownUnbind: null,
        gridPalette: [],
        colorReady: false,
        eyedropperSupported: typeof window !== 'undefined' && typeof window.EyeDropper === 'function',

        init() {
            if (this.__colorOwner === this.$el) return
            this.__colorOwner = this.$el
            this._pickerHostElement = this.$el
            const runtime = colorRuntime(this)
            runtime.disposed = false
            ++runtime.epoch

            this.inputFormat = this.format
            this.syncExternalColor(this.state)
            this.syncInputs()
            this.gridPalette = resolveColorPalette(configuration)
            runtime.unbind = wireExclusiveFlexDropdown(this, {
                openKey: 'panelOpen',
                closeMethod: 'closePanel',
                ownerIdPrefix: 'fff-color',
            })
            this.$nextTick(() => {
                if (runtime.disposed) return
                this.colorReady = true
                if (this.$refs.panel) {
                    this.$refs.panel.dataset.fffDropdownOwner = resolveDropdownOwnerId(this, 'fff-color')
                }
            })
            this.$watch('state', value => this.syncExternalColor(value))
            const outside = event => this.onOutsidePointer(event)
            document.addEventListener('pointerdown', outside, true)
            runtime.outside = () => document.removeEventListener('pointerdown', outside, true)
        },

        destroy() {
            this.closePanel()
            const runtime = colorRuntime(this)
            runtime.disposed = true
            runtime.unbind?.()
            runtime.outside?.()
        },
    }, createColorState(), createColorPopover(), createColorChannels())
}
