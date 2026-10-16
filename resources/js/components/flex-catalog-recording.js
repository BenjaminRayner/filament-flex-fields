import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'

export function recordingCanvas(width, height) {
    const padding = width < 640 ? 24 : 90
    const w = Math.max(1, Math.min(width - padding * 2, (height - padding * 2) * 16 / 9))
    const h = w * 9 / 16
    return { width: w, height: h, left: (width - w) / 2, top: (height - h) / 2 }
}

/** Position mounted examples in place: no cloning, teleporting or remounting. */
export default function flexCatalogRecordingComponent() {
    const chosen = new Set(), candidates = new Set(), ancestors = new Set()
    let scope, previewScope, frame = null, opener = null
    const portals = new Map()
    const listStyles = new Map()
    let openPortalCount = 0
    const clearCandidateMarks = () => {
        for (const node of candidates) node.classList.remove('fff-recording-candidate', 'fff-recording-chosen')
        candidates.clear()
    }
    const portalProperties = ['zoom','left','top','right','width','min-width','max-width','max-height','clip-path']
    const restorePortals = () => {
        for (const [panel, { properties }] of portals) {
            for (const [name, value, priority] of properties) {
                if (value) panel.style.setProperty(name, value, priority)
                else panel.style.removeProperty(name)
            }
        }
        for (const [list, value, priority] of listStyles.values()) {
            if (value) list.style.setProperty('max-height', value, priority)
            else list.style.removeProperty('max-height')
        }
        listStyles.clear(); portals.clear(); openPortalCount = 0
    }
    const syncPortals = (canvas, scale, verticalOffset = 0) => {
        const px = value => `${Math.round(value * 1000) / 1000}px`
        let count = 0
        for (const panel of document.querySelectorAll('.fff-teleported-menu[aria-hidden="false"]')) {
            if (panel.matches('.fff-overlay-sheet, .fff-teleported-menu--sheet') || !panel.classList.contains('is-positioned') || panel.style.position !== 'fixed' || !panel.getClientRects().length) continue
            let anchor = null, owner = null
            for (const node of chosen) {
                anchor = [...node.querySelectorAll('[aria-controls]')].find(control =>
                    (control.getAttribute('aria-controls') ?? '').split(/\s+/).some(id => {
                        const owned = document.getElementById(id)
                        return owned && (owned === panel || panel.contains(owned))
                    }))
                if (!anchor) {
                    const template = [...node.querySelectorAll('template[x-teleport]')].find(element => {
                        const source = element.content.querySelector('.fff-teleported-menu')
                        return source && ((source.id && source.id === panel.id)
                            || (source.getAttribute('data-fff-select-menu-owner') && source.getAttribute('data-fff-select-menu-owner') === panel.getAttribute('data-fff-select-menu-owner')))
                    })
                    anchor = template?.parentElement.closest('[x-data]')?.querySelector('[data-fff-open-trigger]') ?? null
                    if (anchor?.matches('.fff-phone-field__country-trigger')) anchor = anchor.closest('.fff-phone-field__shell')
                }
                if (anchor) { owner = node; break }
            }
            if (!anchor) continue
            anchor = anchor.closest('.fff-phone-field__shell, .fff-select-control, .fi-input-wrp.fff-select-field, .fff-select-field__shell') ?? anchor
            count++
            const list = panel.querySelector('[role="listbox"]')
            if (!portals.has(panel)) portals.set(panel, {
                properties: portalProperties.map(name => [name, panel.style.getPropertyValue(name), panel.style.getPropertyPriority(name)]),
                height: Math.max(1, panel.offsetHeight), width: Math.max(1, panel.offsetWidth),
                matchesTrigger: Math.abs(panel.offsetWidth - anchor.getBoundingClientRect().width) < 3,
                alignEnd: Math.abs((parseFloat(panel.style.left) || 0) + panel.offsetWidth - anchor.getBoundingClientRect().right) < 2,
                listMaxHeight: list ? (parseFloat(getComputedStyle(list).maxHeight) || Math.max(list.clientHeight, 240)) : 0,
            })
            const original = portals.get(panel)
            const gap = 6 * scale
            const ownerHeight = owner.offsetHeight * scale
            const naturalHeight = original.height * scale
            const ownerTop = Math.max(canvas.top + 32, canvas.top + (canvas.height - ownerHeight - gap - naturalHeight) / 2 + verticalOffset)
            owner.style.setProperty('--fff-recording-top', px(ownerTop))
            const ownerRect = owner.getBoundingClientRect()
            owner.style.setProperty('--fff-recording-clip', [
                (canvas.top - ownerTop) / scale,
                (ownerRect.right - canvas.left - canvas.width) / scale,
                (ownerTop + ownerHeight - canvas.top - canvas.height) / scale,
                (canvas.left - ownerRect.left) / scale,
            ].map(px).join(' '))
            const rect = anchor.getBoundingClientRect()
            const width = original.matchesTrigger ? rect.width : original.width * scale
            const write = (name, value) => {
                if (panel.style.getPropertyValue(name) !== value || panel.style.getPropertyPriority(name) !== 'important') panel.style.setProperty(name, value, 'important')
            }
            write('zoom', String(scale))
            write('width', px(width / scale))
            write('min-width', '0px'); write('max-width', 'none')
            write('max-height', 'none')
            if (list) {
                if (!listStyles.has(list)) listStyles.set(list, [list, list.style.getPropertyValue('max-height'), list.style.getPropertyPriority('max-height')])
                const height = px(original.listMaxHeight)
                if (list.style.getPropertyValue('max-height') !== height) list.style.setProperty('max-height', height, 'important')
            }
            const top = Math.max(rect.bottom, ownerRect.bottom) + gap
            const left = original.alignEnd ? rect.right - width : rect.left
            write('left', px(left / scale)); write('top', px(top / scale)); write('right', 'auto')
            write('clip-path', `inset(${[
                (canvas.top - top) / scale,
                (left + width - canvas.left - canvas.width) / scale,
                (top + panel.offsetHeight * scale - canvas.top - canvas.height) / scale,
                (canvas.left - left) / scale,
            ].map(px).join(' ')})`)
        }
        const closed = openPortalCount > 0 && count === 0
        openPortalCount = count
        if (closed) restorePortals()
        return closed
    }
    const clearPresentation = () => {
        restorePortals()
        for (const node of chosen) {
            node.classList.remove('fff-recording-target', 'fff-recording-hide-helpers')
            for (const name of ['width','left','top','scale','clip']) node.style.removeProperty(`--fff-recording-${name}`)
        }
        for (const node of ancestors) node.classList.remove('fff-recording-ancestor')
        ancestors.clear()
        document.body.classList.remove('fff-recording-active')
    }
    return {
        selecting: false, recording: false, count: 0, zoom: 100, width: 480, controls: true, verticalOffset: 0, showHelpers: true,
        init() {
            scope = createResourceScope()
            scope.listen(this.$el.closest('.fff-flex-detail'), 'click', event => {
                if (!this.selecting) return
                const target = [...candidates].find(node => node.contains(event.target))
                if (!target) return
                event.preventDefault(); event.stopImmediatePropagation()
                if (event.shiftKey) this.toggleChoice(target)
                else { chosen.clear(); chosen.add(target); this.count = 1; this.open() }
            }, { capture: true })
            scope.listen(document, 'fff-recording-choose', event => { if (event.target.closest('.fff-flex-detail') === this.$el.closest('.fff-flex-detail')) this.choose() })
            scope.listen(window, 'keydown', event => {
                if (!this.recording && !this.selecting) return
                if (event.altKey && event.code === 'KeyH') { event.preventDefault(); this.controls = !this.controls; return }
                if (event.altKey && event.code === 'KeyR') { event.preventDefault(); this.exit(); return }
                if (event.key === 'Escape' && !event.defaultPrevented && !document.querySelector('.fi-modal-open, .fff-teleported-menu[aria-hidden="false"]')) this.exit()
            })
            scope.listen(document, 'livewire:navigating', () => this.exit(false))
            scope.defer(() => { previewScope?.dispose(); clearCandidateMarks(); clearPresentation(); chosen.clear() })
            this.$nextTick(() => { if (this.$el.closest('.fff-flex-detail').hasAttribute('data-fff-recording-requested')) this.choose() })
        },
        choose() {
            this.$el.closest('.fff-flex-detail').removeAttribute('data-fff-recording-requested')
            this.exit(false)
            opener = document.activeElement
            const form = this.$el.closest('.fff-flex-detail').querySelector('form')
            const nodes = [...form.querySelectorAll('.fi-fo-field, .fi-fo-field-wrp, [data-slot]')].filter(node => {
                if (!node.getClientRects().length || node.closest('[role="tabpanel"]')?.getAttribute('aria-hidden') === 'true') return false
                // Container slots must remain intact; the outermost candidate owns its children.
                return !node.closest('.fff-recording-backdrop')
            })
            for (const node of nodes) {
                if (nodes.some(parent => parent !== node && parent.contains(node))) continue
                candidates.add(node); node.classList.add('fff-recording-candidate')
            }
            this.selecting = true
        },
        toggleChoice(node) {
            if (chosen.has(node)) chosen.delete(node)
            else if (chosen.size < 3) chosen.add(node)
            node.classList.toggle('fff-recording-chosen', chosen.has(node))
            this.count = chosen.size
        },
        open() {
            if (!chosen.size || [...chosen].some(node => !node.isConnected)) return
            this.selecting = false; this.recording = true; this.controls = true; this.zoom = 100; this.verticalOffset = 0; this.showHelpers = true
            clearCandidateMarks()
            for (const node of chosen) {
                node.classList.add('fff-recording-target')
                for (let parent = node.parentElement; parent && parent !== document.body; parent = parent.parentElement) ancestors.add(parent)
            }
            for (const node of ancestors) node.classList.add('fff-recording-ancestor')
            document.body.classList.add('fff-recording-active')
            previewScope = createResourceScope()
            const schedule = () => {
                if (frame !== null || !this.recording) return
                frame = requestAnimationFrame(() => { frame = null; this.layout() })
            }
            previewScope.defer(() => { if (frame !== null) cancelAnimationFrame(frame); frame = null })
            previewScope.listen(window, 'resize', schedule)
            if (typeof ResizeObserver !== 'undefined') {
                const observer = new ResizeObserver(schedule)
                previewScope.trackObserver(observer)
                for (const node of chosen) observer.observe(node)
            }
            const observer = new MutationObserver(records => {
                if ([...chosen].some(node => !node.isConnected)) { this.exit(false); return }
                for (const record of records) {
                    if (record.type !== 'attributes') continue
                    if (chosen.has(record.target)) {
                        if (!record.target.classList.contains('fff-recording-target')) record.target.classList.add('fff-recording-target')
                        if (!record.target.style.getPropertyValue('--fff-recording-width') || record.target.classList.contains('fff-recording-hide-helpers') === this.showHelpers) schedule()
                    } else if (ancestors.has(record.target) && !record.target.classList.contains('fff-recording-ancestor')) record.target.classList.add('fff-recording-ancestor')
                }
            })
            observer.observe(this.$el.closest('.fff-flex-detail'), { childList: true, subtree: true, attributes: true, attributeFilter: ['class','style'] })
            previewScope.trackObserver(observer)
            const portalObserver = new MutationObserver(records => {
                if (!this.recording) return
                const relevant = records.some(record => record.target.matches?.('.fff-teleported-menu')
                    || [...record.addedNodes].some(node => node.matches?.('.fff-teleported-menu')))
                if (relevant && syncPortals(recordingCanvas(window.innerWidth, window.innerHeight), Math.max(.25, Math.min(3, Number(this.zoom) / 100)), Number(this.verticalOffset))) this.layout()
            })
            portalObserver.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['style','class','aria-hidden'] })
            previewScope.trackObserver(portalObserver)
            this.$nextTick(() => { if (this.recording) { this.layout(); this.$refs.closeRecording.focus({ preventScroll: true }) } })
            window.dispatchEvent(new Event('resize'))
        },
        layout() {
            if (!this.recording) return
            const canvas = recordingCanvas(window.innerWidth, window.innerHeight)
            const stage = this.$refs.recordingCanvas
            for (const [key,value] of Object.entries(canvas)) stage.style.setProperty(`--fff-recording-canvas-${key}`, `${value}px`)
            const gap = 32, padding = 32, columns = chosen.size
            const cell = Math.max(40, (canvas.width - padding * 2 - gap * (columns - 1)) / columns)
            const width = Math.min(Number(this.width), cell)
            const requestedScale = Math.max(.25, Math.min(3, Number(this.zoom) / 100))
            const nodes = [...chosen]
            for (const node of nodes) {
                node.classList.toggle('fff-recording-hide-helpers', !this.showHelpers)
                node.style.setProperty('--fff-recording-width', `${width}px`)
            }
            const scale = requestedScale
            nodes.forEach((node,index) => {
                const cx = canvas.left + padding + cell / 2 + index * (cell + gap)
                const left = cx - width * scale / 2
                const top = Math.max(canvas.top + padding, canvas.top + (canvas.height - node.offsetHeight * scale) / 2 + Number(this.verticalOffset))
                node.style.setProperty('--fff-recording-left', `${left}px`)
                node.style.setProperty('--fff-recording-top', `${top}px`)
                node.style.setProperty('--fff-recording-scale', String(scale))
                const clip = [
                    (canvas.top - top) / scale,
                    (left + width * scale - canvas.left - canvas.width) / scale,
                    (top + node.offsetHeight * scale - canvas.top - canvas.height) / scale,
                    (canvas.left - left) / scale,
                ]
                node.style.setProperty('--fff-recording-clip', clip.map(value => `${value}px`).join(' '))
            })
            syncPortals(canvas, scale, Number(this.verticalOffset))
        },
        exit(restoreFocus = true) {
            previewScope?.dispose(); previewScope = null
            clearCandidateMarks(); clearPresentation(); chosen.clear()
            this.recording = this.selecting = false; this.count = 0
            if (restoreFocus) window.dispatchEvent(new Event('resize'))
            if (restoreFocus && opener?.isConnected) opener.focus({ preventScroll: true })
        },
        destroy() { this.exit(false); scope?.dispose() },
    }
}
