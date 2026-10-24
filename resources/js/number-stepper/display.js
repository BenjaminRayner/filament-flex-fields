import { createDecimalFormatter, normalizeLocale } from '../core/number-format.js'
import { numericValue } from './value.js'

const widest = texts => texts.reduce((result, text) => Array.from(String(text)).length > Array.from(String(result)).length ? text : result, '')

export function createStepperDisplayMixin() {
    let pluralRules = null
    let format = null
    let mainFormatter, bucketFormatter, formatterLocale, formatterPlaces
    const decimal = (owner, value, bucket = false) => {
        // Reactive getters run several times per edit. Keep only this owner's
        // current typography policy; never cache numeric values or other owners.
        if (!mainFormatter || owner.locale !== formatterLocale || owner.decimalPlaces !== formatterPlaces) {
            formatterLocale = owner.locale
            formatterPlaces = owner.decimalPlaces
            mainFormatter = createDecimalFormatter({ locale: formatterLocale, decimalPlaces: formatterPlaces })
            bucketFormatter = createDecimalFormatter({ locale: formatterLocale, decimalPlaces: formatterPlaces ?? 0 })
        }
        return (bucket ? bucketFormatter : mainFormatter)(value)
    }
    return {
        get numericState() { return numericValue(this.state, this.integer) },
        get hasValue() { return this.numericState !== null },
        get displaySuffix() {
            if (!Object.keys(this.suffixForms).length || !this.hasValue) return this.suffix ?? ''
            try { pluralRules ??= new Intl.PluralRules(normalizeLocale(this.locale)); }
            catch { pluralRules ??= new Intl.PluralRules('en'); }
            return this.suffixForms[pluralRules.select(this.numericState)] ?? this.suffixForms.other
        },
        formatMain(value) {
            const number = numericValue(value, this.integer)
            return number === null ? this.nullLabel : (this.prefix ?? '') + decimal(this, number)
        },
        formatBucketedDisplay(value) {
            const number = numericValue(value) ?? 88
            const digits = Math.max(2, String(Math.floor(Math.abs(number))).length)
            const integer = '8'.repeat(digits)
            const fraction = this.decimalPlaces !== null && this.decimalPlaces > 0 ? '.' + '8'.repeat(this.decimalPlaces) : ''
            return (this.prefix ?? '') + (this.locale
                ? decimal(this, Number(integer + fraction), true)
                : integer + fraction)
        },
        get valueSizerText() {
            if (!this.hasValue) return widest([this.widthAnchor, this.nullLabel])
            const suffix = this.displaySuffix ? '\u00a0' + this.displaySuffix : ''
            return widest([this.widthAnchor, this.formatMain(this.numericState) + suffix, this.formatBucketedDisplay(this.numericState) + suffix])
        },
        get spokenValue() {
            return this.hasValue ? this.formatMain(this.numericState) + (this.displaySuffix ? '\u00a0' + this.displaySuffix : '') : this.nullLabel
        },
        buildFlowFormat() {
            return this.decimalPlaces !== null
                ? { minimumFractionDigits: this.decimalPlaces, maximumFractionDigits: this.decimalPlaces }
                : { maximumFractionDigits: this.integer ? 0 : 20 }
        },
        configureFlow() {
            if (!this.flow) return
            format ??= this.buildFlowFormat()
            this.flow.format = format
            this.flow.locales = this.locale ? normalizeLocale(this.locale) : 'en-US'
            // Preserve the historical ungrouped value when locale was not configured.
            if (!this.locale) format.useGrouping = false
            this.flow.numberPrefix = this.prefix ?? ''
            // Units use the same centered renderer on the server and client.
            this.flow.numberSuffix = ''
            this.flow.animated = this.wheelAnimated && !this.reducedMotion
            this.flow.transformTiming = { duration: 380, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' }
            this.flow.spinTiming = { duration: 420, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' }
            this.flow.opacityTiming = { duration: 220, easing: 'ease-out' }
        },
        updateFlow(animate = true) {
            if (this._stepperDisposed || !this.flow) return
            const next = this.numericState
            if (animate && Object.is(next, this.lastFlowValue)) return
            const previous = this.lastFlowValue
            this.configureFlow()
            if (next !== null) {
                this.flow.animated = animate && this.wheelAnimated && !this.reducedMotion
                this.flow.update(next)
            }
            this.lastFlowValue = next
            this.updateSuffix(this.displaySuffix, next !== null && previous != null && next < previous ? -1 : 1, animate)
        },
    }
}
