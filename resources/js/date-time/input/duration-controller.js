import { parseDuration, formatDuration } from '../domain/duration.js'
import { createResourceScope } from '../../runtime/lifecycle/resource-scope.js'
import { watchOwnedAlpineValue } from '../../runtime/alpine/owned-watch.js'

const sessions = new WeakMap()
export default function flexDurationFormComponent({ state }) {
    return {
        state, inputError: '', canDecrease: false, canIncrease: false,
        init() {
            const root = this.$el.closest('[data-fff-duration-field]'); if (sessions.has(root)) return
            const config = JSON.parse(root.dataset.fffDurationConfig), input = root.querySelector('[data-duration-input]'), scope = createResourceScope()
            const session = { root, config, input, scope, ownState: this.state, disposed: false }; sessions.set(root, session)
            session.render = () => { const value = parseDuration(this.state); input.value = value === null ? String(this.state || '') : formatDuration(value, config.showSeconds); this.validateDuration() }
            let composing = false
            scope.listen(input, 'compositionstart', () => { composing = true })
            scope.listen(input, 'compositionend', () => { composing = false; this.acceptDuration() })
            scope.listen(input, 'input', () => { if (!composing) this.acceptDuration() })
            scope.listen(input, 'blur', () => { this.acceptDuration(); session.render() })
            scope.listen(input, 'keydown', event => { if (['ArrowUp', 'ArrowDown'].includes(event.key)) { event.preventDefault(); this.stepDuration(event.key === 'ArrowUp' ? 1 : -1) } })
            watchOwnedAlpineValue(scope, () => this.state, value => { if (value === session.ownState) return; session.ownState = value; session.render() })
            session.render()
        },
        validateDuration() {
            const session = sessions.get(this.$el.closest('[data-fff-duration-field]')); if (!session) return
            const { config, input } = session, value = parseDuration(this.state), empty = this.state === null || this.state === ''
            this.inputError = empty ? '' : value === null ? config.validationMessages.invalid : value < config.min ? config.validationMessages.before_min : value > config.max ? config.validationMessages.after_max : ''
            input.setAttribute('aria-invalid', String(Boolean(this.inputError || config.isInvalid)))
            this.canDecrease = !config.disabled && !config.readOnly && value !== null && value > config.min
            this.canIncrease = !config.disabled && !config.readOnly && (empty || value !== null && value < config.max)
        },
        acceptDuration() {
            const session = sessions.get(this.$el.closest('[data-fff-duration-field]')); if (!session || session.config.disabled || session.config.readOnly) return
            const raw = session.input.value.trim(), seconds = parseDuration(raw)
            const previous = parseDuration(this.state)
            const value = !raw ? null : previous !== null && raw === formatDuration(previous, session.config.showSeconds) ? formatDuration(previous, session.config.storeSeconds) : seconds === null ? raw : formatDuration(seconds, session.config.storeSeconds)
            const changed = value !== session.ownState
            session.ownState = value; this.state = value; this.validateDuration()
            if (changed) {
                session.root.dispatchEvent(new CustomEvent('fff-duration-input', { bubbles: true, detail: { value, seconds, valid: !this.inputError } }))
                if (!this.inputError) session.root.dispatchEvent(new CustomEvent('fff-duration-change', { bubbles: true, detail: { value, seconds } }))
            }
        },
        stepDuration(direction) {
            const session = sessions.get(this.$el.closest('[data-fff-duration-field]')); if (!session || session.config.disabled || session.config.readOnly) return
            const { config } = session, current = parseDuration(this.state)
            if (current === null && this.state) return
            const seconds = Math.max(config.min, Math.min(config.max, current === null ? config.min : current + direction * config.step))
            const value = formatDuration(seconds, config.storeSeconds); session.ownState = value; this.state = value; session.render()
            session.root.dispatchEvent(new CustomEvent('fff-duration-change', { bubbles: true, detail: { value, seconds } }))
        },
        destroy() { const root = this.$el.closest('[data-fff-duration-field]'), session = sessions.get(root); if (!session || session.disposed) return; session.disposed = true; session.scope.dispose(); sessions.delete(root) },
    }
}
