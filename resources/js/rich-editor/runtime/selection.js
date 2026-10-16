import { createRafScheduler } from '../../core/rich-editor-chrome.js'
import { isImageNodeSelected, getSelectedImageAnchor, shouldHideParagraphFloatingToolbar } from '../selection.js'

export function createRichEditorSelection(session, configuration) {
    const { componentKey, canAttachFiles, hasParagraphFloatingToolbar } = configuration
    return {
        scheduleImageOverlaySync(editor) {
            if (! canAttachFiles) {
                return
            }

            if (! session.overlaySyncScheduler) {
                session.overlaySyncScheduler = createRafScheduler(() => {
                    this.syncImageOverlay(editor)
                })
            }

            session.overlaySyncScheduler.schedule()
        },

        async setupRichEditorToolbarA11y() {
            if (! this.$refs.toolbar) {
                return
            }

            const { setupToolbarKeyboardNavigation } = await import('../../core/rich-editor-toolbar-a11y.js')

            if (session.owner.disposed) return
            session.teardownToolbarA11y?.()
            session.teardownToolbarA11y = setupToolbarKeyboardNavigation(this.$refs.toolbar)
        },

        patchFloatingToolbarPlugins() {
            const editor = typeof this.$getEditor === 'function' ? this.$getEditor() : null

            if (! editor || this.floatingToolbarPluginsPatched) {
                return
            }

            this.floatingToolbarPluginsPatched = true
            session.boundEditor = editor

            this.setupEditorChromeSync(editor)

            const syncSelectionChrome = () => {
                if (hasParagraphFloatingToolbar) {
                    this.syncParagraphFloatingToolbar(editor)
                }

                if (canAttachFiles) {
                    this.scheduleImageOverlaySync(editor)
                }
            }

            if (hasParagraphFloatingToolbar || canAttachFiles) {
                editor.on('selectionUpdate', syncSelectionChrome)
                editor.on('focus', syncSelectionChrome)
                editor.on('blur', syncSelectionChrome)
                session.owner.defer(() => { for (const event of ['selectionUpdate', 'focus', 'blur']) editor.off(event, syncSelectionChrome) })
            }

            if (canAttachFiles && ! this.imageOverlayOnScroll) {
                const scrollTarget = this.$refs.editor

                if (scrollTarget) {
                    this.imageOverlayScrollTarget = scrollTarget
                    this.imageOverlayOnScroll = () => {
                        this.scheduleImageOverlaySync(editor)
                    }
                    scrollTarget.addEventListener('scroll', this.imageOverlayOnScroll, { passive: true })
                }
            }

            if (canAttachFiles) {
                syncSelectionChrome()
            } else if (hasParagraphFloatingToolbar) {
                this.syncParagraphFloatingToolbar(editor)
            }
        },

        syncParagraphFloatingToolbar(editor) {
            if (! hasParagraphFloatingToolbar) {
                return
            }

            const element = this.$refs['floatingToolbar::paragraph']

            if (! element) {
                return
            }

            const shouldHide = shouldHideParagraphFloatingToolbar(editor)

            if (session.lastParagraphToolbarHidden === shouldHide) {
                return
            }

            session.lastParagraphToolbarHidden = shouldHide

            element.classList.toggle('fff-rich-editor__bubble-menu--hidden', shouldHide)

            if (shouldHide) {
                element.style.setProperty('display', 'none', 'important')
                element.style.visibility = 'hidden'

                return
            }

            element.style.removeProperty('display')
            element.style.removeProperty('visibility')
        },

        observeImageOverlayResize(editor, imageAnchor) {
            if (typeof ResizeObserver === 'undefined' || ! imageAnchor) {
                return
            }

            session.imageOverlayResizeObserver?.disconnect()
            session.imageOverlayResizeObserver = new ResizeObserver(() => {
                this.scheduleImageOverlaySync(editor)
            })
            session.imageOverlayResizeObserver.observe(imageAnchor)
        },

        disconnectImageOverlayResizeObserver() {
            session.imageOverlayResizeObserver?.disconnect()
            session.imageOverlayResizeObserver = null
        },

        syncImageOverlay(editor) {
            if (! canAttachFiles) {
                return
            }

            const overlay = this.$refs.imageOverlay
            const content = this.$refs.editor
            const main = content?.parentElement

            if (! overlay || ! content || ! main) {
                return
            }

            const imageAnchor = getSelectedImageAnchor(editor, content)
            const shouldShow = imageAnchor != null && isImageNodeSelected(editor)

            this.imageOverlayVisible = shouldShow

            if (! shouldShow) {
                overlay.classList.remove('fff-rich-editor__image-overlay--visible')
                overlay.style.display = 'none'
                session.lastOverlayGeometryKey = null
                this.disconnectImageOverlayResizeObserver()

                return
            }

            const scrollTop = this.imageOverlayScrollTarget?.scrollTop ?? 0
            const geometryKey = `${editor.state.selection.from}:${imageAnchor.offsetTop}:${imageAnchor.offsetLeft}:${imageAnchor.offsetWidth}:${imageAnchor.offsetHeight}:${scrollTop}`

            if (geometryKey === session.lastOverlayGeometryKey) {
                return
            }

            session.lastOverlayGeometryKey = geometryKey

            const mainRect = main.getBoundingClientRect()
            const imageRect = imageAnchor.getBoundingClientRect()

            overlay.classList.add('fff-rich-editor__image-overlay--visible')
            overlay.style.display = 'block'
            overlay.style.left = `${imageRect.left - mainRect.left}px`
            overlay.style.top = `${imageRect.top - mainRect.top}px`
            overlay.style.width = `${imageRect.width}px`
            overlay.style.height = `${imageRect.height}px`

            this.observeImageOverlayResize(editor, imageAnchor)
        },

        editSelectedImage() {
            const editor = typeof this.$getEditor === 'function' ? this.$getEditor() : null

            if (! editor) {
                return
            }

            const attributes = editor.getAttributes('image') ?? {}

            this.$wire.mountAction(
                'attachFiles',
                {
                    editorSelection: this.editorSelection,
                    alt: attributes.alt,
                    id: attributes.id,
                    src: attributes.src,
                },
                { schemaComponent: componentKey },
            )
        },

        deleteSelectedImage() {
            const editor = typeof this.$getEditor === 'function' ? this.$getEditor() : null

            if (! editor) {
                return
            }

            editor.chain().focus().deleteSelection().run()
        },


    }
}
