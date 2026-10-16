import { parseColorValue, serializeColor, clamp, hsvToRgb, rgbToHsv } from './value.js'
import { colorRuntime, cancelColorDrag } from './lifetime.js'
export function createColorState() {
    return {
        hsva: { h: 239, s: 59, v: 95, a: 1 }, inputFormat: 'hex', valueInput: '', opacityInput: 100, colorError: null,
        get solidColor() { return serializeColor(this.hsva) },
        get swatchColor() { return serializeColor(this.hsva, 'hex', this.alphaEnabled) },
        get saturationBackground() { return `linear-gradient(to top,#000,transparent),linear-gradient(to right,#fff,hsl(${this.hsva.h},100%,50%))` },
        get saturationHandleStyle() { return { left: `${this.hsva.s}%`, top: `${100 - this.hsva.v}%`, backgroundColor: this.solidColor } },
        get hueGradient() { return 'linear-gradient(to right,#f00,#ff0,#0f0,#0ff,#00f,#f0f,#f00)' },
        get alphaGradient() { return `linear-gradient(to right,transparent,${this.solidColor})` },
        get availableFormats() { return this.allowedFormats ?? (this.alphaEnabled ? ['hex', 'rgb', 'hsl', 'rgba', 'hsla'] : ['hex', 'rgb', 'hsl']) },
        selectInputFormat(format) {
            if (this.readOnly || !this.availableFormats.includes(format)) return
            this.inputFormat = format; this.syncInputs()
        },
        sliderColorChannels() {
            if (this.inputFormat.startsWith('rgb')) return this.displayColorChannels().map((channel, i) => ({
                ...channel, label: this.labels[['red', 'green', 'blue'][i]],
                background: `linear-gradient(to right,${[0, 255].map(endpoint => { const rgb = hsvToRgb(this.hsva).slice(0, 3); rgb[i] = endpoint; return `rgb(${rgb.join(',')})` }).join(',')})`,
            }))
            const { h, s, v } = this.hsva, l = v / 100 * (1 - s / 200), hs = l === 0 || l === 1 ? 0 : (v / 100 - l) / Math.min(l, 1 - l) * 100
            return [
                { key: 'h', label: this.labels.hue, value: h, max: 360, suffix: '°', background: this.hueGradient },
                { key: 's', label: this.labels.saturation, value: hs, max: 100, suffix: '%', background: `linear-gradient(to right,hsl(${h},0%,${l * 100}%),hsl(${h},100%,${l * 100}%))` },
                { key: 'l', label: this.labels.lightness, value: l * 100, max: 100, suffix: '%', background: `linear-gradient(to right,#000,hsl(${h},${hs}%,50%),#fff)` },
            ]
        },
        updateSliderChannel(key, input, commit = false) {
            const number = Number(input)
            if (this.readOnly || !Number.isFinite(number)) return
            if (this.inputFormat.startsWith('rgb')) {
                const rgb = hsvToRgb(this.hsva).slice(0, 3), index = ['r', 'g', 'b'].indexOf(key)
                if (index < 0) return
                rgb[index] = clamp(number, 0, 255); this.hsva = rgbToHsv(...rgb.map(Math.round), this.hsva.a)
            } else {
                const channels = this.sliderColorChannels(), index = ['h', 's', 'l'].indexOf(key)
                if (index < 0) return
                const values = channels.map((channel, i) => i === index ? clamp(number, 0, channel.max) : channel.value)
                this.hsva = parseColorValue(`hsla(${values[0]},${values[1]}%,${values[2]}%,${this.hsva.a})`) ?? this.hsva
            }
            this.syncInputs(); if (commit) this.commitState()
        },
        displayColorChannels() {
            if (this.inputFormat.startsWith('rgb')) {
                return hsvToRgb(this.hsva).slice(0, 3).map((value, i) => ({ key: ['r','g','b'][i], label: ['R','G','B'][i], value, max: 255, suffix: '' }))
            }
            if (this.inputFormat.startsWith('hsl')) {
                const { h, s, v } = this.hsva, lightness = v / 100 * (1 - s / 200)
                const saturation = lightness === 0 || lightness === 1 ? 0 : (v / 100 - lightness) / Math.min(lightness, 1 - lightness)
                return [h, saturation * 100, lightness * 100].map((value, i) => ({ key: ['h','s','l'][i], label: ['H','S','L'][i], value: Math.round(value * 100) / 100, max: i ? 100 : 360, suffix: i ? '%' : '°' }))
            }
            return []
        },
        updateColorChannel(key, input) {
            if (this.readOnly) return
            const channels = this.displayColorChannels(), changed = channels.find(channel => channel.key === key), number = Number(input)
            if (!changed || !Number.isFinite(number) || String(input).trim() === '') { this.syncInputs(); return }
            const values = channels.map(channel => channel.key === key ? clamp(number, 0, channel.max) : channel.value)
            if (this.inputFormat.startsWith('rgb')) this.hsva = rgbToHsv(...values.map(Math.round), this.hsva.a)
            else {
                const parsed = parseColorValue(`hsla(${values[0]}, ${values[1]}%, ${values[2]}%, ${this.hsva.a})`)
                if (!parsed) return
                this.hsva = parsed
            }
            this.syncInputs(); this.commitState()
        },
        syncInputs() { this.valueInput = serializeColor(this.hsva, this.inputFormat, this.alphaEnabled); this.opacityInput = Math.round(this.hsva.a * 100) },
        receiveExternalColor(value) {
            if (this.state !== value) this.state = value
            this.syncExternalColor(value)
        },
        syncExternalColor(value) {
            if (value === this.serializeState()) return
            const r = colorRuntime(this)
            ++r.epoch; r.dropper?.abort(); r.dropper = null; cancelColorDrag(this)
            const parsed = parseColorValue(value)
            if (parsed) { this.hsva = { ...parsed, a: this.alphaEnabled ? parsed.a : 1 }; this.colorError = null; this.syncInputs() }
            else if (value != null && value !== '') this.colorError = this.labels.invalid
        },
        serializeState() { return serializeColor(this.hsva, this.format, this.alphaEnabled) },
        commitState() {
            if (this.readOnly || colorRuntime(this).disposed) return
            this.colorError = null; const value = this.serializeState()
            if (this.state !== value) { this.state = value; this.onChange?.(value) }
        },
        onValueInput() {
            if (this.readOnly) return
            const parsed = parseColorValue(this.valueInput)
            if (!parsed) { this.colorError = this.labels.invalid; return }
            this.hsva = { ...parsed, a: this.alphaEnabled ? parsed.a : 1 }; this.syncInputs(); this.commitState()
        },
        onOpacityInput() {
            const value = Number(this.opacityInput)
            if (!Number.isFinite(value)) { this.syncInputs(); return }
            this.setChannel('a', clamp(value, 0, 100) / 100, true)
        },
        selectGridColor(color) {
            if (this.readOnly) return
            const parsed = parseColorValue(color)
            if (!parsed) return
            this.hsva = { ...parsed, a: this.alphaEnabled ? parsed.a : 1 }; this.syncInputs(); this.commitState()
            if (this.closeOnSelect) this.closePanel(true)
        },
        isGridColorSelected(color) { const parsed = parseColorValue(color); return parsed && serializeColor(parsed, 'hex', this.alphaEnabled) === this.swatchColor },
        async pickFromScreen() {
            if (!this.eyedropperEnabled || !this.eyedropperSupported || this.readOnly) return
            const r = colorRuntime(this), epoch = ++r.epoch
            r.dropper?.abort(); r.dropper = new AbortController()
            try {
                const result = await new window.EyeDropper().open({ signal: r.dropper.signal })
                if (r.disposed || r.epoch !== epoch) return
                const parsed = parseColorValue(result.sRGBHex)
                if (parsed) { this.hsva = { ...parsed, a: this.hsva.a }; this.syncInputs(); this.commitState() }
            } catch (error) { if (error?.name !== 'AbortError' && r.epoch === epoch) this.colorError = this.labels.invalid }
            finally { if (r.epoch === epoch) r.dropper = null }
        },
    }
}
