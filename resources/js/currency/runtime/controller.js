import { createResourceScope } from '../../runtime/lifecycle/resource-scope.js'
import { editStateFromMinor, minorFromEditState, normalizeEditForBlur } from '../domain/state.js'
import { getLogicalLength, snapCursor, moveCursorByDelta, cursorPosFromClientX } from '../domain/cursor.js'
import { applyTypeDigitAtCursor, deleteBeforeCursor, deleteAtCursor } from '../domain/editing.js'
import { buildDisplaySegments, buildDisplayItems } from '../domain/digits.js'
import { getLocaleSeparators } from '../domain/format.js'
import { normalizeLocale } from '../../core/number-format.js'

/** Minor units are canonical; this owner edits amounts and never operates a menu. */
export default function createCurrencyController(config = {}) {
    const scope = createResourceScope()
    const records = new Map((config.currencies ?? []).map(record => [record.code, record]))
    let initialized = false
    let disposed = false
    let releaseAnimation = () => {}
    scope.defer(() => records.clear())
    return {
        currencyState: config.state, statePath: config.statePath,
        defaultCurrency: config.defaultCurrency, hasCurrencySelect: Boolean(config.hasCurrencySelect),
        disabled: Boolean(config.disabled), readOnly: Boolean(config.readOnly),
        locale: config.locale, placeholder: config.placeholder ?? '',
        animated: config.animated !== false, commitDecimalsOnBlur: config.commitDecimalsOnBlur !== false,
        allowNegative: Boolean(config.allowNegative), minMinor: config.minMinor ?? null, maxMinor: config.maxMinor ?? null,
        isFocused: false, isEditing: false, displayReady: false, reducedMotion: false,
        cursorPos: 0, edit: editStateFromMinor(null, 2), displayItems: [], displaySegments: [],
        enteringDigitKeys: new Set(), pendingPointerClientX: null,
        get isLocked() { return this.disabled || this.readOnly },
        get activeCurrency() {
            const code = this.hasCurrencySelect ? this.currencyState?.currency ?? this.defaultCurrency : this.defaultCurrency
            return records.get(code) ?? { code, symbol: '', decimals: 2, locale: this.locale }
        },
        get amount() { return this.hasCurrencySelect ? this.currencyState?.amount ?? null : this.currencyState ?? null },
        get isEmpty() { return !this.edit.wholeDigits && !this.edit.fracDigits && !this.edit.inDecimal },
        get ghostSegments() { return this.displaySegments.filter(segment => segment.ghost) },
        init() {
            if (initialized || disposed) return
            initialized = true
            this.reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
            if (this.hasCurrencySelect && (!this.currencyState || typeof this.currencyState !== 'object' || Array.isArray(this.currencyState))) {
                this.currencyState = { amount: Number.isSafeInteger(this.currencyState) ? this.currencyState : null, currency: this.defaultCurrency }
            }
            this.syncEditFromAmount()
            this.$watch('currencyState', () => { if (!disposed && !this.isEditing) this.syncEditFromAmount() })
        },
        syncEditFromAmount() {
            this.edit = editStateFromMinor(this.amount, this.activeCurrency.decimals)
            this.cursorPos = getLogicalLength(this.edit, this.activeCurrency.decimals)
            this.refreshDisplay(false)
        },
        refreshDisplay(animate = false, { animateDigitKey = null } = {}) {
            if (disposed) return
            const decimals = this.activeCurrency.decimals
            this.cursorPos = snapCursor(this.cursorPos, this.edit, decimals)
            this.displaySegments = buildDisplaySegments(this.edit, {
                decimals, locale: normalizeLocale(this.locale || this.activeCurrency.locale),
            }, { showGhost: this.isFocused && this.commitDecimalsOnBlur && (this.edit.inDecimal || Boolean(this.edit.fracDigits)) })
            this.displayItems = buildDisplayItems(this.displaySegments, this.cursorPos, decimals, this.edit)
            releaseAnimation()
            this.enteringDigitKeys = new Set(animate && this.animated && !this.reducedMotion && animateDigitKey !== null ? [animateDigitKey] : [])
            if (this.enteringDigitKeys.size) {
                const timer = setTimeout(() => { if (!disposed) this.enteringDigitKeys = new Set(); releaseAnimation() }, 200)
                releaseAnimation = scope.defer(() => clearTimeout(timer))
            }
            this.displayReady = true
        },
        shouldAnimateDigit(item) { return this.enteringDigitKeys.has(item.key) },
        isValidMinorAmount(amount) {
            const min = this.activeCurrency.minMinor ?? this.minMinor
            const max = this.activeCurrency.maxMinor ?? this.maxMinor
            return amount === null || (Number.isSafeInteger(amount) && (this.allowNegative || amount >= 0)
                && (min === null || amount >= min) && (max === null || amount <= max))
        },
        syncAmountFromEdit() {
            if (disposed || this.isLocked) return false
            const amount = minorFromEditState({ ...this.edit, decimals: this.activeCurrency.decimals })
            if (amount !== null && !Number.isSafeInteger(amount)) return false
            // Entangle owns transport and its configured live/defer modifiers.
            this.currencyState = this.hasCurrencySelect ? { amount, currency: this.activeCurrency.code } : amount
            return true
        },
        selectCurrency(code) {
            if (disposed || this.isLocked || !this.hasCurrencySelect || !records.has(code) || code === this.activeCurrency.code) return
            this.syncAmountFromEdit()
            this.currencyState = { amount: this.amount, currency: code }
            this.syncEditFromAmount()
            this.$nextTick(() => { if (!disposed) this.focusInput() })
        },
        typeDigit(digit) {
            const result = applyTypeDigitAtCursor(this.edit, digit, this.cursorPos, this.activeCurrency.decimals)
            const candidate = minorFromEditState({ ...result.edit, decimals: this.activeCurrency.decimals })
            if (!Number.isSafeInteger(candidate)) return
            this.edit = result.edit; this.cursorPos = result.cursorPos
            this.refreshDisplay(true, { animateDigitKey: result.animatedDigitKey ?? null })
        },
        moveCursor(delta) {
            this.cursorPos = moveCursorByDelta(this.cursorPos, delta, this.edit, this.activeCurrency.decimals)
            this.refreshDisplay()
        },
        deleteDigit(forward = false) {
            const operation = forward ? deleteAtCursor : deleteBeforeCursor
            const result = operation(this.edit, this.cursorPos, this.activeCurrency.decimals)
            this.edit = result.edit; this.cursorPos = result.cursorPos; this.refreshDisplay()
        },
        startDecimal() {
            if (!this.activeCurrency.decimals) return
            this.edit.inDecimal = true
            this.cursorPos = snapCursor(this.edit.wholeDigits.length + 1, this.edit, this.activeCurrency.decimals)
            this.refreshDisplay()
        },
        onHiddenKeydown(event) {
            if (this.isLocked || disposed || event.ctrlKey || event.metaKey || event.altKey || event.isComposing) return
            const key = event.key
            if (/^[0-9]$/.test(key)) { event.preventDefault(); this.typeDigit(key); return }
            if (key === 'ArrowLeft' || key === 'ArrowRight') { event.preventDefault(); this.moveCursor(key === 'ArrowLeft' ? -1 : 1); return }
            if (key === 'Home' || key === 'End') {
                event.preventDefault(); this.cursorPos = key === 'Home' ? 0 : getLogicalLength(this.edit, this.activeCurrency.decimals); this.refreshDisplay(); return
            }
            if (key === 'Delete' || key === 'Backspace') { event.preventDefault(); this.deleteDigit(key === 'Delete'); return }
            if (key === '.' || key === ',') { event.preventDefault(); this.startDecimal(); return }
            if (key === '-' && this.allowNegative) { event.preventDefault(); this.edit.negative = !this.edit.negative; this.refreshDisplay() }
        },
        onPaste(event) {
            if (this.isLocked || disposed) return
            event.preventDefault()
            const text = event.clipboardData?.getData('text/plain')?.trim() ?? ''
            const { decimal, group } = getLocaleSeparators(normalizeLocale(this.locale || this.activeCurrency.locale))
            const normalized = text.split(group).join('').replace(/[\s\u00a0]/g, '').replace(decimal, '.')
            if (!/^-?\d+(?:\.\d+)?$/.test(normalized) || (normalized.startsWith('-') && !this.allowNegative)) return
            const [wholeDigits, fracDigits = ''] = normalized.replace('-', '').split('.')
            const next = { wholeDigits, fracDigits: fracDigits.slice(0, this.activeCurrency.decimals), inDecimal: false, negative: normalized.startsWith('-') }
            if (!this.isValidMinorAmount(minorFromEditState({ ...next, decimals: this.activeCurrency.decimals }))) return
            this.edit = next; this.cursorPos = getLogicalLength(next, this.activeCurrency.decimals); this.refreshDisplay()
        },
        onHiddenPointerDown(event) { if (!this.isLocked) { this.pendingPointerClientX = event.clientX; if (this.isFocused) this.setCursorFromClientX(event.clientX) } },
        setCursorFromClientX(clientX) {
            const display = this.$el.querySelector('.fff-currency-field__live-display.is-ready')
            this.cursorPos = cursorPosFromClientX(clientX, this.edit, this.activeCurrency.decimals, display) ?? getLogicalLength(this.edit, this.activeCurrency.decimals)
            this.refreshDisplay()
        },
        onFocus() {
            if (this.isLocked || disposed) return
            this.isFocused = true; this.isEditing = true
            const pointer = this.pendingPointerClientX; this.pendingPointerClientX = null
            if (pointer !== null) this.setCursorFromClientX(pointer)
            else { this.cursorPos = getLogicalLength(this.edit, this.activeCurrency.decimals); this.refreshDisplay() }
        },
        onBlur() {
            this.isFocused = false; this.edit = normalizeEditForBlur(this.edit)
            this.syncAmountFromEdit(); this.isEditing = false; this.refreshDisplay()
        },
        focusInput() { if (!disposed && !this.isLocked) this.$refs.hiddenInput?.focus({ preventScroll: true }) },
        destroy() { if (disposed) return; disposed = true; scope.dispose() },
    }
}
