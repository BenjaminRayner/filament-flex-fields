/** Per-component bounded voices; timers and fades never outlive their owner. */
export function createAudioPool({ maxSources = 16, maxVoices = 32 } = {}) {
    if (!Number.isInteger(maxSources) || maxSources < 1 || !Number.isInteger(maxVoices) || maxVoices < 1) {
        throw new Error('Invalid TodoList audio pool limits.')
    }
    const sources = new Map()
    const voices = new Set()
    let disposed = false
    const releaseNode = (node) => {
        try { node.pause(); node.removeAttribute('src'); node.load() } catch { /* Browser media release is best effort. */ }
    }

    return {
        play(url, { fadeOutAfterMs = null, fadeMs = 400 } = {}) {
            if (disposed || typeof url !== 'string' || !url || typeof Audio === 'undefined') return null
            let ownedVoice = null
            try {
                let source = sources.get(url)
                if (!source) {
                    source = new Audio(url)
                    source.preload = 'auto'
                    sources.set(url, source)
                    while (sources.size > maxSources) {
                        const oldest = sources.keys().next().value
                        releaseNode(sources.get(oldest))
                        sources.delete(oldest)
                    }
                }
                while (voices.size >= maxVoices) voices.values().next().value.stop()
                const node = source.cloneNode(true)
                node.volume = 0.85
                let timer = null
                let frame = null
                let stopped = false
                const clearEffects = () => {
                    clearTimeout(timer)
                    if (frame !== null) cancelAnimationFrame(frame)
                    timer = frame = null
                }
                const voice = {
                    stop() {
                        if (stopped) return
                        stopped = true
                        clearEffects()
                        node.removeEventListener('ended', voice.stop)
                        node.removeEventListener('error', voice.stop)
                        voices.delete(voice)
                        releaseNode(node)
                    },

                    fadeOut(ms = 400) {
                        if (stopped) return
                        clearEffects()
                        const from = Number(node.volume) || 0
                        const started = performance.now()
                        const duration = Number.isFinite(Number(ms)) ? Math.max(40, Number(ms)) : 400
                        const step = (now) => {
                            if (stopped || disposed) return voice.stop()
                            const progress = Math.min(1, (now - started) / duration)
                            node.volume = Math.max(0, from * (1 - progress))
                            if (progress < 1) frame = requestAnimationFrame(step)
                            else voice.stop()
                        }
                        frame = requestAnimationFrame(step)
                    },

                }
                ownedVoice = voice
                voices.add(voice)
                node.addEventListener('ended', voice.stop, { once: true })
                node.addEventListener('error', voice.stop, { once: true })
                const playing = node.play()
                playing?.catch?.(() => voice.stop())
                if (fadeOutAfterMs !== null && Number.isFinite(Number(fadeOutAfterMs))) {
                    timer = setTimeout(() => voice.fadeOut(fadeMs), Math.max(0, Number(fadeOutAfterMs)))
                }
                return voice
            } catch { ownedVoice?.stop(); return null }
        },

        dispose() {
            if (disposed) return
            disposed = true
            for (const voice of [...voices]) voice.stop()
            for (const source of sources.values()) releaseNode(source)
            sources.clear()
        },
    }
}
