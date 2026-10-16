import { createSliderNumbers } from './numbers.js'
import { createSliderButton } from './button.js'
import { collectSliderNodes, createSliderPresentation } from './presentation.js'
import { configureSlider } from './configuration.js'
import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { waitForAssetActivation } from '../runtime/lifecycle/asset-activation.js'
import { formatValue, number, snap, ratio } from './value.js'

export function createSliderRuntime(input) {
    const config = configureSlider(input)
    const button = config.iconButton ? createSliderButton(config) : null
    let scope, driver, motion, numbers, presentation, ready, cancelFrame, observed, paintNodes
    let glide = false
    let disposed = false, initialized = false, values = [...config.initial]
    const runtime = {
        state: input.state, disabled: !!input.isDisabled, isDragging: false, isButtonActive: config.iconButton != null && config.initial[0] === config.iconButton.value,
        isSliderReady: false, liveDisplayValue: config.initial.map(v => formatValue(v, config)).join(' – '),
        config,
        init() {
            if (initialized) return ready
            initialized = true
            scope = createResourceScope({ onEffectError: error => this.$dispatch?.('fff-slider-error', { error }) })
            ready = (async () => {
                if (!await waitForAssetActivation(this.$el, { signal: scope.signal }) || disposed) return
                if (this.$el.querySelector('number-flow')) {
                    await import('../core/number-flow.js')
                    await customElements.whenDefined('number-flow')
                }
                if (disposed) return
                const advanced = config.initial.length > 1 || config.isVertical || config.nonLinearPoints || config.rangePadding != null
                    || (config.pipsMode && !config.serverRenderedPips)
                const [engine, elastic] = await Promise.all([
                    advanced ? import('./engines/nouislider.js') : import('./engines/pointer.js'),
                    config.elastic ? import('./motion/elastic.js') : Promise.resolve(null),
                ])
                if (!await waitForAssetActivation(this.$el, { signal: scope.signal }) || disposed) return
                paintNodes = collectSliderNodes(this.$el, this.$refs.band)
                const media = this.$el.ownerDocument.defaultView.matchMedia('(prefers-reduced-motion: reduce)')
                const reduced = () => media.matches
                const context = { root: this.$el, refs: this.$refs, scope, config, reduced,
                    disabled: () => this.disabled || disposed, hasSelection: () => Array.isArray(this.state) ? this.state.length > 0 && this.state.every(v => number(v) !== null) : number(this.state) !== null, current: () => [...values],
                    input: (next, positions = null, gliding = false) => this.receive(next, positions, gliding),
                    handle: index => { paintNodes.thumbs.forEach((node, at) => node.classList.toggle('is-active', at === index)); paintNodes.bubbles.forEach((node, at) => node?.toggleAttribute('data-slider-active', at === index)); motion?.handle(index) },
                    tag: active => { this.$el.toggleAttribute('data-slider-tag', active); motion?.tag(active) },
                    bump: direction => motion?.bump(direction),
                    commit: source => this.commit(source),
                    visualPosition: index => motion?.current(index) ?? null,
                    geometry: span => motion?.geometry(span),
                    settle: velocity => motion?.settle(velocity),
                    drag: (active, event) => { this.isDragging = active; this.$el.classList.toggle('is-dragging', active); motion?.drag(active, event) },
                    stretch: (px, width) => motion?.stretch(px, width),
                    pulse: (mark, touch) => {
                        if (motion) motion.pulse(mark, touch)
                        else if (touch && config.haptics && !reduced()) { try { this.$el.ownerDocument.defaultView.navigator.vibrate?.(6) } catch {} }
                    },
                }
                numbers = createSliderNumbers(this.$el, config, reduced)
                presentation = createSliderPresentation(paintNodes, config, numbers)
                motion = elastic?.createElasticMotion(context, paintNodes)
                driver = engine.createSliderEngine(context)
                button?.remember(values[0])
                const observer = new this.$el.ownerDocument.defaultView.ResizeObserver(() => {
                    if (disposed) return
                    if (cancelFrame) return
                    cancelFrame = scope.frame(() => { cancelFrame = null; driver?.resize(); this.paint(); })
                })
                scope.trackObserver(observer); observer.observe(this.$refs.track)
                const watch = this.$watch('state', state => {
                    if (disposed || this.isDragging) return
                    const raw = Array.isArray(state) ? state : [state]
                    if (raw.length !== values.length || raw.some(v => number(v) === null)) return
                    const next = raw.map(v => snap(v, config))
                    if (!next.every((v, i) => v === values[i])) driver?.set(next)
                })
                if (typeof watch === 'function') scope.defer(watch)
                const disableWatch = this.$watch('disabled', () => { driver?.disable(); if (this.disabled) motion?.reset() })
                if (typeof disableWatch === 'function') scope.defer(disableWatch)
                scope.listen(media, 'change', () => { motion?.reset(); this.paint() })
                this.paint()
                this.isSliderReady = true
                this.$el.setAttribute('data-slider-ready', '')
            })().catch(error => {
                driver?.destroy(); motion?.destroy(); presentation?.destroy(); numbers?.destroy(); scope?.dispose()
                driver = motion = numbers = presentation = paintNodes = null
                initialized = false
                throw error
            })
            return ready
        },
        receive(next, positions = null, gliding = false) {
            if (disposed) return
            const changed = !next.every((v, i) => v === values[i])
            values = next.map(v => snap(v, config))
            this.isButtonActive = config.iconButton != null && values[0] === config.iconButton.value
            button?.remember(values[0])
            observed = positions
            glide = gliding
            this.paint()
            if (changed) {
                input.onInput?.(values.length === 1 ? values[0] : [...values])
                this.$dispatch?.('fff-slider-input', { value: values.length === 1 ? values[0] : [...values] })
            }
        },
        paint() {
            if (disposed || !paintNodes) return
            const positions = observed ?? values.map(v => ratio(v, config))
            const text = presentation.paint(values, positions, !!motion)
            if (this.liveDisplayValue !== text) this.liveDisplayValue = text
            motion?.position(positions.map(at => config.isRtl ? 1 - at : at), glide)
        },
        commit(source = 'pointer') {
            if (disposed || this.disabled) return
            const value = values.length === 1 ? values[0] : [...values]
            if (JSON.stringify(value) !== JSON.stringify(this.state)) this.state = value
            input.onCommit?.(value)
            this.$dispatch?.('fff-slider-commit', { value, source })
        },
        toggleIconButton() {
            if (disposed || this.disabled || this.isDragging || !button || !driver) return
            const next = button.toggle(values[0])
            if (next === null) return
            driver.set([next])
            this.commit(next === config.iconButton.value ? 'button-activate' : 'button-restore')
        },
        selectMark(value) {
            if (disposed || this.disabled || !driver) return
            driver.selectMark(value)
            this.commit('mark')
            motion?.pulse(value, false)
        },
        destroy() {
            if (disposed) return
            disposed = true
            driver?.destroy(); motion?.destroy(); presentation?.destroy(); numbers?.destroy(); scope?.dispose()
            driver = motion = presentation = numbers = paintNodes = null
        },
    }
    return runtime
}
