import { createSearchableSelectMenuMixin } from '../../../core/searchable-select-menu.js'

export function createSelectMenu() { return createSearchableSelectMenuMixin({
    openKey: 'comboboxOpen',
    readyKey: 'menuReady',
    scrollHandlerKey: 'menuScrollHandler',
    resizeHandlerKey: 'menuResizeHandler',
    scrollParentsKey: 'menuScrollParents',
    triggerRef: 'headlessTrigger',
    menuRef: 'headlessMenu',
    closeMethod: 'comboboxCloseMenu',
    ownerIdPrefix: 'fff-headless-select',
    minMenuWidth: 288,
    onMenuClose() {
        this._engine?.close?.()
        this.comboboxHighlightedIndex = -1
        this._highlightedDisplayRowKey = null
        this.inlineSearchFocused = false
        this._engine?.setQuery?.('')
        this.cancelRelationshipSearch?.()
        this._syncFromEngine()

        if (typeof this.usesInlineSearchTriggerInput === 'function' && this.usesInlineSearchTriggerInput()) {
            this.syncInlineSearchInputAfterClose()
        } else {
            this.comboboxQuery = ''
        }
    },
}) }
