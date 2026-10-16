import { cssVarPx, resolveBarCount, resamplePeaks } from '../core/dynamic-bars.js'
import { extractWaveformFromUrl, generateWaveformFromFingerprint } from '../core/audio-waveform.js'

/** One bounded renderer shared by the player and recorder. Analysis never gates playback. */
export function createWaveform(owner, { maxBytes = 32 * 1024 * 1024, custom = false } = {}) {
    let vm, observer = null, request = null, generation = 0, lastCount = 0
    const render = () => {
        const node = vm?.$refs.waveform
        if (!node || owner.disposed || !node.clientWidth) return
        const count = resolveBarCount(node.clientWidth, { idealBarWidthPx: cssVarPx(node, '--fff-audio-field-bar-w', 2.5), gapPx: cssVarPx(node, '--fff-audio-field-bar-gap', 1), minBars: 16, maxBars: 128 })
        const peaks = resamplePeaks(vm.sourceWaveform, count, { minPeak: 8 })
        if (count !== lastCount || peaks.some((peak, index) => peak !== vm.displayWaveform[index])) vm.displayWaveform = peaks
        lastCount = count; node.classList.add('is-ready'); vm.waveformReady = true
    }
    owner.defer(() => { generation++; request?.abort(); observer?.disconnect() })
    return {
        sourceWaveform: [], displayWaveform: [], waveformReady: false,
        mountWaveform() {
            vm = this
            if (observer || owner.disposed) return
            const Observer = this.$el.ownerDocument.defaultView.ResizeObserver
            if (Observer && this.$refs.waveform) { observer = new Observer(render); observer.observe(this.$refs.waveform) }
            render()
        },
        updateWaveformBars: render,
        async loadWaveform(src, supplied = null) {
            request?.abort(); request = new AbortController()
            const token = ++generation
            this.sourceWaveform = supplied?.length ? [...supplied] : generateWaveformFromFingerprint(src)
            render()
            if (custom || supplied?.length || !src || src.startsWith('blob:')) return
            const peaks = await extractWaveformFromUrl(src, 64, { signal: request.signal, maxBytes })
            if (owner.disposed || token !== generation || !peaks?.length) return
            this.sourceWaveform = peaks; render()
        },
    }
}
