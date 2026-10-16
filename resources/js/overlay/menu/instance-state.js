/** Every mutable slot and optional hook masks an ancestor Alpine scope. */
export function createMenuInstanceState() {
    return {
        _surfaceOpenSession: null,
        _surfaceOpenGeneration: 0,
        __fffMenuOpenStartedAt: null,
        __fffOverlayManaged: false,
        __fffOverlayMode: null,
        __fffPanelClosing: false,
        __fffSheetClosing: false,
        __fffSheetDismissCleanup: null,
        __fffSheetScrollLocked: false,
        __fffSheetScrollRelease: null,
        __fffSheetVisualViewportCleanup: null,
        position: null,
        resolveMenuTriggerElement: null,
        resolveMatchTriggerWidth: null,
        resolveMenuMinWidth: null,
        containsMenuInteraction: null,
        afterOverlayPanelOpened: null,
        resetVirtualListScroll: null,
        measureVirtualListViewport: null,
        onOverlaySheetGeometry: null,
    }
}
