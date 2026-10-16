import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { createRequestCoordinator } from '../select/async/request-coordinator.js'
import { loadLibPhoneNumber, formatNationalDisplay, syncPhoneState } from '../select/adapters/phone/phone-number-service.js'

export { formatNationalDisplay, formatPhoneInput, syncPhoneState } from '../select/adapters/phone/phone-number-service.js'

/** Phone owns formatting only. Its nested country control is the standard Select factory. */
export default function phoneFieldFormComponent(config = {}) {
    const records = new Map((config.countries ?? []).map(country => [country.code, country]))
    const scope = createResourceScope()
    const requests = createRequestCoordinator()
    let initialized = false
    let disposed = false
    let published = null
    let hintTimer = null
    const signature = state => JSON.stringify([state?.country, state?.national])
    scope.defer(() => { requests.dispose(); clearTimeout(hintTimer); records.clear() })
    return {
        phoneState: config.phoneState,
        disabled: Boolean(config.disabled), readOnly: Boolean(config.readOnly),
        inputValue: config.initialInputValue ?? '',
        possibleInvalid: false, phoneFormatError: null,
        get isLocked() { return this.disabled || this.readOnly },
        get selectedCountry() { return records.get(this.phoneState?.country) ?? config.selectedCountrySeed ?? { name: '', flag_url: '', dial_code: '' } },
        get dialPrefix() { return this.selectedCountry.dial_code ?? '' },
        init() {
            if (initialized || disposed) return
            initialized = true
            if (!this.phoneState || typeof this.phoneState !== 'object' || Array.isArray(this.phoneState)) {
                this.phoneState = { country: config.defaultCountry, national: '', e164: '' }
            }
            published = signature(this.phoneState)
            this.$watch('phoneState', () => {
                if (!disposed && signature(this.phoneState) !== published) void this.restoreDisplay()
            })
            // The server already formatted this exact initial state. The number
            // library belongs to editing, not to readiness of the country Select.
            // External state changes still go through the revision-checked watcher.
            if (config.initialInputValue === undefined) return this.restoreDisplay()
        },
        async restoreDisplay() {
            if (disposed) return
            const lease = requests.begin('format')
            if (!lease) return
            const revision = signature(this.phoneState)
            const { country, national } = this.phoneState
            try {
                const formatted = national ? await formatNationalDisplay(national, country) : ''
                if (lease.current() && revision === signature(this.phoneState)) {
                    this.inputValue = formatted
                    published = revision
                    this.phoneFormatError = null
                }
            } catch { if (lease.current()) this.phoneFormatError = 'formatter-unavailable' }
            finally { lease.finish() }
        },
        async formatInput(value) {
            if (disposed || this.isLocked) return
            const lease = requests.begin('format')
            if (!lease) return
            const country = this.phoneState.country
            const owner = this.phoneState
            try {
                const next = await syncPhoneState(value, country)
                if (!lease.current() || disposed || this.isLocked || country !== this.phoneState.country || owner !== this.phoneState) return
                published = JSON.stringify([country, next.national])
                this.phoneState.national = next.national
                this.phoneState.e164 = next.e164
                this.inputValue = next.formatted
                this.phoneFormatError = null
                clearTimeout(hintTimer)
                hintTimer = setTimeout(() => void this.checkPossible(), 180)
            } catch { if (lease.current()) this.phoneFormatError = 'formatter-unavailable' }
            finally { lease.finish() }
        },
        async checkPossible() {
            if (disposed) return
            const lease = requests.begin('hint')
            if (!lease) return
            const revision = signature(this.phoneState)
            try {
                const { isPossiblePhoneNumber } = await loadLibPhoneNumber()
                if (lease.current() && revision === signature(this.phoneState)) {
                    this.possibleInvalid = Boolean(this.phoneState.national) && !isPossiblePhoneNumber(String(this.phoneState.national), this.phoneState.country)
                }
            } catch { if (lease.current()) this.possibleInvalid = false }
            finally { lease.finish() }
        },
        onInput(event) {
            if (this.isLocked || disposed) return
            this.inputValue = String(event.target.value ?? '')
            return this.formatInput(this.inputValue)
        },
        onPhoneFocus() { void loadLibPhoneNumber().catch(() => {}) },
        async selectCountry(code) {
            if (disposed || this.isLocked || !records.has(code) || code === this.phoneState.country) return
            requests.invalidate()
            this.phoneState.country = code
            await this.formatInput(this.inputValue)
            if (!disposed && !this.isLocked && this.phoneState.country === code) this.$refs.phoneInput?.focus({ preventScroll: true })
        },
        destroy() { if (!disposed) { disposed = true; scope.dispose() } },
    }
}
