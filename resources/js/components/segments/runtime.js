import { paintSegmentIndicator } from '../../core/segment-indicator.js'
import { createResourceScope } from '../../runtime/lifecycle/resource-scope.js'
import {
    positionSegmentOverflowScrollElement,
    scrollSegmentItemIntoView,
    updateHorizontalSegmentScrollShadow,
} from '../../core/segment-overflow-position.js'

const keyOf = value => value == null ? null : String(value)

/** One lifecycle for field choices, schema tabs and locale tabs. Adapters own state. */
export function createSegmentRuntime({ property, optionKeys = [], disabled = false, repairSelection = false, commands = false, onCommand = () => {}, onChange = () => {}, setup = () => {} }) {
    const effects = createResourceScope()
    let initialization = null
    let frame = null
    let animationFrame = null
    let pendingScroll = false
    let pendingSmooth = false

    return {
        optionKeys,
        disabled,
        segmentCanScrollBefore: false,
        segmentCanScrollAfter: false,
        segmentOverflowInteractive: false,

        normalize: keyOf,
        isSelected(value) { return !commands && keyOf(this[property]) === keyOf(value) },
        segmentButtons() {
            return [...(this.$refs.track?.querySelectorAll('[data-segment-value]') ?? [])]
        },
        availableSegmentButtons() {
            return this.segmentButtons().filter(button => !button.disabled && !button.hidden && button.style.display !== 'none')
        },
        isSegmentDisabled() {
            const control = this.$refs.track?.closest?.('.fff-segment-control')
            return control ? control.classList.contains('is-disabled') : this.disabled
        },
        canSelect(value) {
            return !this.isSegmentDisabled() && this.availableSegmentButtons().some(button => button.dataset.segmentValue === keyOf(value))
        },
        select(value) {
            if (!this.canSelect(value)) return
            if (commands) return onCommand.call(this, value)
            if (this.isSelected(value)) return
            this.$dispatch?.('fff-segment-before-change')
            this[property] = value
            this.syncSegmentSelection()
            this.scheduleSegmentLayout({ scroll: true, smooth: true })
            this.$dispatch?.('fff-segment-tab-change', { key: value })
        },
        onSegmentKeydown(event) {
            // Nested fields must never navigate an ancestor's tab strip.
            if (!event.target?.matches?.('[data-segment-value]') || !this.$refs.track?.contains(event.target)) return
            const buttons = this.availableSegmentButtons()
            const index = buttons.indexOf(event.target)
            if (index < 0 || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return
            event.preventDefault()
            const rtl = getComputedStyle(this.$refs.track).direction === 'rtl'
            const step = (event.key === 'ArrowRight' ? 1 : -1) * (rtl ? -1 : 1)
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? buttons.length - 1 : (index + step + buttons.length) % buttons.length
            const button = buttons[next]
            if (!commands) this.select(button.dataset.segmentValue)
            button.focus({ preventScroll: true })
        },
        syncSegmentSelection() {
            const buttons = this.segmentButtons()
            const available = this.availableSegmentButtons()
            if (repairSelection && !available.some(button => this.isSelected(button.dataset.segmentValue))) {
                this[property] = available[0]?.dataset.segmentValue ?? null
            }
            const selected = buttons.find(button => this.isSelected(button.dataset.segmentValue))
            const focus = selected && available.includes(selected) ? selected : available[0]
            for (const button of buttons) {
                const active = button === selected
                button.dataset.segmentSelected = active ? 'true' : 'false'
                if (!commands) button.setAttribute(button.getAttribute('role') === 'tab' ? 'aria-selected' : 'aria-checked', active ? 'true' : 'false')
                button.tabIndex = (commands ? available.includes(button) : button === focus) && !this.isSegmentDisabled() ? 0 : -1
            }
            const selectedIndex = buttons.indexOf(selected)
            for (const separator of this.$refs.track?.querySelectorAll('[data-segment-separator]') ?? []) {
                const index = Number(separator.dataset.segmentSeparator)
                const hidden = commands
                    ? !buttons[index]?.offsetWidth || !buttons[index + 1]?.offsetWidth
                    : selectedIndex >= 0 && (index === selectedIndex || index === selectedIndex - 1)
                separator.classList.toggle('is-hidden', hidden)
            }
        },
        scheduleSegmentLayout({ scroll = false, smooth = false } = {}) {
            if (effects.disposed) return
            pendingScroll ||= scroll
            pendingSmooth ||= smooth
            if (frame !== null) return
            frame = requestAnimationFrame(() => {
                frame = null
                if (effects.disposed || !this.$el?.isConnected) return
                const options = { scroll: pendingScroll, smooth: pendingSmooth }
                pendingScroll = pendingSmooth = false
                this.syncSegmentSelection()
                this.layoutSegments(options)
            })
        },
        layoutSegments({ scroll = false, smooth = false } = {}) {
            const track = this.$refs.track
            const indicator = this.$refs.indicator
            const selected = this.segmentButtons().find(button => this.isSelected(button.dataset.segmentValue) && button.style.display !== 'none')
            if (!track || !indicator) return
            // An inactive ancestor has no measurable geometry. Keep the mounted
            // pill and overflow position; reactivation is a resize, not a new init.
            if (!track.offsetWidth) return
            if (commands) {
                indicator.style.opacity = '0'
                this.updateSegmentScrollShadow()
                return
            }
            if (!paintSegmentIndicator(track, indicator, selected)) return
            if (scroll) scrollSegmentItemIntoView(this.$refs.scrollShadow, selected, smooth)
            this.updateSegmentScrollShadow()
        },
        updateSegmentScrollShadow() {
            const element = this.$refs.scrollShadow
            const state = updateHorizontalSegmentScrollShadow(element)
            this.segmentCanScrollBefore = state.canScrollBefore
            this.segmentCanScrollAfter = state.canScrollAfter
            this.segmentOverflowInteractive = true
        },
        scrollSegmentOverflowBy(direction) {
            const element = this.$refs.scrollShadow
            if (!element) return
            const rtl = getComputedStyle(element).direction === 'rtl'
            const max = Math.max(0, element.scrollWidth - element.clientWidth)
            const next = element.scrollLeft + direction * element.clientWidth * .8 * (rtl ? -1 : 1)
            element.scrollTo({ left: Math.max(rtl ? -max : 0, Math.min(rtl ? 0 : max, next)), behavior: 'smooth' })
        },
        init() {
            if (initialization) return initialization
            initialization = new Promise(resolve => {
                setup.call(this, effects)
                if (!commands) this.$watch(property, value => {
                    if (effects.disposed) return
                    onChange.call(this, value)
                    this.syncSegmentSelection()
                    this.scheduleSegmentLayout({ scroll: true, smooth: true })
                })
                this.$nextTick(() => {
                    if (effects.disposed) { resolve(); return }
                    this.syncSegmentSelection()
                    positionSegmentOverflowScrollElement(this.$refs.scrollShadow)
                    this.layoutSegments()
                    effects.listen(this.$refs.scrollShadow, 'scroll', () => this.updateSegmentScrollShadow(), { passive: true })
                    if (typeof ResizeObserver !== 'undefined') {
                        const observer = new ResizeObserver(() => this.scheduleSegmentLayout())
                        for (const element of [this.$refs.track, this.$refs.scrollShadow]) if (element) observer.observe(element)
                        effects.defer(() => observer.disconnect())
                    }
                    if (typeof MutationObserver !== 'undefined' && this.$refs.track) {
                        const observer = new MutationObserver(records => {
                            if (records.some(record => !record.target.matches?.('.fff-segment-indicator,[data-segment-separator]'))) this.scheduleSegmentLayout()
                        })
                        observer.observe(this.$refs.track, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['disabled', 'hidden', 'style', 'class', 'data-segment-value'] })
                        effects.defer(() => observer.disconnect())
                    }
                    animationFrame = requestAnimationFrame(() => {
                        animationFrame = null
                        if (!effects.disposed) {
                            this.$refs.track?.classList.add('is-animated')
                            this.$refs.indicator?.classList.add('is-animated')
                        }
                    })
                    resolve()
                })
            })
            effects.defer(() => {
                if (frame !== null) cancelAnimationFrame(frame)
                if (animationFrame !== null) cancelAnimationFrame(animationFrame)
            })
            return initialization
        },
        destroy() { effects.dispose() },
    }
}
