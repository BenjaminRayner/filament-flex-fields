import { parseClock, showClock } from '../domain/local-date-time.js'
import { createResourceScope } from '../../runtime/lifecycle/resource-scope.js'
import { watchOwnedAlpineValue } from '../../runtime/alpine/owned-watch.js'
import { isAssetElementActive } from '../../runtime/lifecycle/asset-activation.js'
import { createDateValueMotion } from './value-motion.js'

const sessions = new WeakMap()
const stateIdentity = value => JSON.stringify(value && typeof value === 'object' ? { start: value.start, end: value.end } : value)
let menuImport = null
const loadMenu = () => menuImport ??= import('../time/menu.js').catch(error => { menuImport = null; throw error })
function parse(value, config) {
    for (const format of [config.storageFormat, config.displayFormat, 'H:i:s', 'H:i', 'g:i A', 'h:i A', 'g:i:s A', 'h:i:s A']) {
        const clock = parseClock(value, format); if (clock) return clock
    }
    return null
}
const clocks = (value, config) => config.mode === 'timeRange' ? [parse(value?.start, config), parse(value?.end, config)] : [parse(value, config)]
const within = (time, config) => (!config.minValue || time >= config.minValue) && (!config.maxValue || time <= config.maxValue) && (config.allowedTimes == null || config.allowedTimes.includes(time))
const allowed = (session, index, time) => within(time, session.config) && (session.config.mode !== 'timeRange' || (index === 0 ? !session.value[1] || time <= session.value[1] : !session.value[0] || time >= session.value[0]))
export default function flexTimePickerFormComponent({ state }) {
    return {
        state, inputError: '',
        init() {
            const root = this.$el.closest('[data-fff-clock-field]'); if (sessions.has(root)) return
            const config = JSON.parse(root.dataset.fffClockConfig), scope = createResourceScope(), inputs = [...root.querySelectorAll('[data-clock-trigger]')]
            const session = { root, config, scope, inputs, menus: new Map(), busy: new Map(), value: clocks(this.state, config), ownState: this.state, disposed: false, generation: 0, menuError: false }
            sessions.set(root, session)
            session.motions = inputs.map(input => createDateValueMotion(input.closest('[data-clock-input-row]'), input))
            session.render = (immediate = false) => inputs.forEach((input, index) => { const time = session.value[index]; input.value = time ? showClock(time, config.displayFormat) : ''; session.motions[index].set(input.value, time, immediate) })
            scope.defer(() => { for (const menu of session.menus.values()) menu.dispose(); for (const motion of session.motions) motion.dispose() })
            inputs.forEach((input, index) => {
                let composing = false
                scope.listen(input, 'compositionstart', () => { composing = true })
                scope.listen(input, 'compositionend', () => { composing = false; if (config.editable) this.acceptTime(index) })
                scope.listen(input, 'input', () => { if (config.editable && !composing) this.acceptTime(index) })
                scope.listen(input, 'blur', () => { if (config.editable) this.acceptTime(index, true) })
                scope.listen(input, 'keydown', event => {
                    if (event.key === 'ArrowDown' || event.key === 'F4' || (!config.editable && ['Enter', ' '].includes(event.key))) { event.preventDefault(); this.toggleTime(index) }
                    if (event.key === 'Escape') { session.generation++; for (const trigger of session.busy.keys()) trigger.removeAttribute('aria-busy'); session.busy.clear(); for (const menu of session.menus.values()) menu.close() }
                })
            })
            watchOwnedAlpineValue(scope, () => this.state, value => { if (stateIdentity(value) === stateIdentity(session.ownState)) return; session.ownState = value; session.value = clocks(value, config); for (const menu of session.menus.values()) menu.close(); session.render(); this.inputError = '' })
            session.render(true)
        },
        commitTime(index, time) {
            const root = this.$el.closest('[data-fff-clock-field]'), session = sessions.get(root)
            if (!session || session.disposed || session.config.disabled || session.config.readOnly) return
            const { config } = session
            session.value[index] = time
            const store = value => value ? showClock(value, config.storageFormat) : null
            const value = config.mode === 'timeRange' ? { start: store(session.value[0]), end: store(session.value[1]) } : store(time)
            session.ownState = value; this.state = value
            this.inputError = time && !within(time, config) ? config.validationMessages[config.minValue && time < config.minValue ? 'before_min' : config.maxValue && time > config.maxValue ? 'after_max' : 'unavailable'] : config.mode === 'timeRange' && session.value[0] && session.value[1] && session.value[1] < session.value[0] ? config.validationMessages.range_order : ''
            root.dispatchEvent(new CustomEvent('fff-time-change', { bubbles: true, detail: { value } }))
        },
        acceptTime(index, format = false) {
            const session = sessions.get(this.$el.closest('[data-fff-clock-field]')); if (!session || session.disposed || !session.config.editable || session.config.disabled || session.config.readOnly) return
            const input = session.inputs[index], previous = session.value[index]
            const time = previous && input.value.trim() === showClock(previous, session.config.displayFormat) ? previous : parse(input.value, session.config)
            if (input.value.trim() && !time) {
                this.inputError = session.config.segmentInvalidMessage || session.config.validationMessages.invalid; input.setAttribute('aria-invalid', 'true')
                const value = session.config.mode === 'timeRange' ? { ...this.state, [index === 0 ? 'start' : 'end']: input.value } : input.value
                session.ownState = value; this.state = value; return
            }
            input.setAttribute('aria-invalid', String(Boolean(session.config.isInvalid))); this.commitTime(index, time)
            if (format) session.render()
        },
        hasClockValue(index) {
            const session = sessions.get(this.$el.closest('[data-fff-clock-field]'))
            return Boolean(session?.config.mode === 'timeRange' ? this.state?.[index === 0 ? 'start' : 'end'] : this.state)
        },
        clearTime(index) {
            const session = sessions.get(this.$el.closest('[data-fff-clock-field]')); if (!session || !session.config.clearable || session.config.disabled || session.config.readOnly) return
            session.generation++
            for (const trigger of session.busy.keys()) trigger.removeAttribute('aria-busy')
            session.busy.clear(); for (const menu of session.menus.values()) menu.close()
            this.commitTime(index, null); session.render()
        },
        async toggleTime(index, seconds = false) {
            const root = this.$el.closest('[data-fff-clock-field]'), session = sessions.get(root)
            if (!session || session.disposed || session.config.disabled || session.config.readOnly) return
            const generation = ++session.generation, trigger = seconds ? root.querySelector(`[data-clock-seconds][data-clock-index="${index}"]`) : session.inputs[index]
            session.busy.set(trigger, generation); trigger.setAttribute('aria-busy', 'true')
            try {
                const { createClockMenu } = await loadMenu()
                if (session.disposed || generation !== session.generation || !isAssetElementActive(root)) return
                if (session.menuError) { session.menuError = false; this.inputError = '' }
                if (!session.menus.has(trigger)) session.menus.set(trigger, createClockMenu({ root, trigger, config: session.config, get: () => session.value[index], valid: time => allowed(session, index, time), set: time => { if (!allowed(session, index, time)) return; this.commitTime(index, time); session.render() } }))
                session.menus.get(trigger).toggle(seconds)
            } catch { if (!session.disposed && generation === session.generation) { session.menuError = true; this.inputError = session.config.menuError } }
            finally { if (session.busy.get(trigger) === generation) { trigger.removeAttribute('aria-busy'); session.busy.delete(trigger) } }
        },
        destroy() {
            const root = this.$el.closest('[data-fff-clock-field]'), session = sessions.get(root); if (!session || session.disposed) return
            session.disposed = true; session.generation++; session.scope.dispose(); sessions.delete(root)
        },
    }
}
