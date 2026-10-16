import { datePart } from '../domain/local-date-time.js'
import { readSegmentSelection } from './segment-model.js'
import { createDateSegments } from './segments.js'
import { createDateValueMotion } from './value-motion.js'
import { createResourceScope } from '../../runtime/lifecycle/resource-scope.js'
import { watchOwnedAlpineValue } from '../../runtime/alpine/owned-watch.js'
import { isAssetElementActive } from '../../runtime/lifecycle/asset-activation.js'
import { readSelection, showSelection, storeSelection, selectionValidation } from '../domain/selection.js'

const sessions = new WeakMap()
const stateIdentity = value => JSON.stringify(value && typeof value === 'object' ? { start: value.start, end: value.end } : value)
const ownerElement = component => component.$el.closest('[data-fff-civil-date]')
const calendarImports = new Map()
function loadCalendar(range) {
    const key = range ? 'range' : 'date'
    if (!calendarImports.has(key)) calendarImports.set(key, (range ? import('../calendar/range/controller.js') : import('../calendar/controller.js')).catch(error => { calendarImports.delete(key); throw error }))
    return calendarImports.get(key)
}

export default function flexDatePickerFormComponent({ state }) {
    return {
        state, calendarOpen: false, inputError: '',
        init() {
            const root = ownerElement(this)
            if (sessions.has(root)) return
            const config = JSON.parse(root.dataset.fffDateConfig)
            const input = root.querySelector('[x-ref="input"]')
            const scope = createResourceScope()
            const session = { scope, config, input, panel: null, opening: null, generation: 0, validationGeneration: 0, ownState: this.state, canonical: readSelection(this.state, config), disposed: false }
            sessions.set(root, session)
            session.presentation = createDateValueMotion(root, input)
            session.segments = config.editable ? createDateSegments({ root, config, scope, canonical: readSegmentSelection(this.state, config),
                change: (canonical, draft) => this.acceptSegments(canonical, draft),
                open: () => this.toggleCalendar(), validate: () => this.validateSegments(),
            }) : null
            session.presentation.set(input.value, session.canonical?.start || session.canonical, true)
            scope.defer(() => session.presentation.dispose())
            const prime = () => {
                if (!config.showCalendar || config.inline || config.disabled || config.readOnly) return
                loadCalendar(config.mode === 'dateRange').catch(() => {})
                if (config.withTime) import('../time/controls.js').catch(() => {})
            }
            // Warm code only for an activated, configured owner. Hidden tabs
            // never initialize this factory. Panel DOM remains intent-driven.
            prime()
            scope.listen(root.querySelector('[x-ref="fieldShell"]'), 'pointerenter', prime, { passive: true })
            scope.listen(input, 'focus', () => { prime(); session.segments?.focus() })
            scope.listen(root, 'keydown', event => {
                if ((!config.editable && event.key === 'ArrowDown') || (!config.editable && event.key === 'F4') || (!config.editable && (event.key === 'Enter' || event.key === ' '))) { event.preventDefault(); this.toggleCalendar() }
                if (event.key === 'Escape') this.closeCalendar()
            })
            watchOwnedAlpineValue(scope, () => this.state, value => {
                if (stateIdentity(value) === stateIdentity(session.ownState)) return
                session.ownState = value
                session.validationGeneration++
                session.canonical = readSelection(value, config)
                input.value = session.canonical ? showSelection(session.canonical, config) : typeof value === 'string' ? value : ''
                session.presentation.set(input.value, session.canonical?.start || session.canonical)
                session.panel?.setValue(datePart(session.canonical, config.mode === 'dateRange'))
                session.segments?.set(readSegmentSelection(value, config))
                this.inputError = ''
            })
            if (config.inline) return this.openCalendar()
        },
        acceptSegments(canonical, { empty, raw, editing = false, origin = 'input' }) {
            const session = sessions.get(ownerElement(this))
            if (!session || session.disposed || session.config.disabled || session.config.readOnly) return
            const { config, input } = session
            const previous = stateIdentity(session.ownState)
            session.validationGeneration = (session.validationGeneration || 0) + 1
            const error = empty ? null : canonical ? selectionValidation(canonical, config) : 'invalid'
            this.inputError = error && !(editing && error === 'invalid') ? (config.segmentInvalidMessage || config.validationMessages[error]) : ''
            input.setAttribute('aria-invalid', String(Boolean(error || config.isInvalid)))
            session.canonical = canonical
            // Incomplete input must reach server validation as invalid state;
            // retaining an old value or coercing it to null would silently save it.
            session.ownState = empty ? null : canonical ? storeSelection(canonical, config) : `incomplete:${raw}`
            this.state = session.ownState
            input.value = canonical ? showSelection(canonical, config) : empty ? '' : raw
            session.panel?.setValue(datePart(canonical, config.mode === 'dateRange'))
            if (!session.segments) session.presentation.set(input.value, canonical?.start || canonical)
            const detail = { value: session.ownState, canonical, origin, valid: !error, error: error ? config.validationMessages[error] : null }
            ownerElement(this).dispatchEvent(new CustomEvent('fff-date-input', { bubbles: true, detail }))
            if ((canonical || empty) && stateIdentity(session.ownState) !== previous)
                ownerElement(this).dispatchEvent(new CustomEvent('fff-date-change', { bubbles: true, detail }))
        },
        async validateSegments() {
            const session = sessions.get(ownerElement(this))
            if (!session || session.disposed || session.config.disabled || session.config.readOnly || !session.config.hasDateUnavailable || !session.canonical || this.inputError) return
            const generation = ++session.validationGeneration
            try {
                const error = await this.$wire.callSchemaComponentMethod(session.config.componentKey, 'validateCivilDateSelection', [session.ownState])
                if (session.disposed || generation !== session.validationGeneration) return
                this.inputError = error || ''
                session.input.setAttribute('aria-invalid', String(Boolean(error || session.config.isInvalid)))
            } catch {
                if (session.disposed || generation !== session.validationGeneration) return
                this.inputError = session.config.labels.availability_error
                session.input.setAttribute('aria-invalid', 'true')
            }
        },
        clearDate() {
            const session = sessions.get(ownerElement(this))
            if (!session || session.config.disabled || session.config.readOnly) return
            if (session.segments) { session.segments.clear(); return }
            this.acceptSegments(null, { empty: true, raw: '', origin: 'clear' })
        },
        toggleCalendar() {
            const session = sessions.get(ownerElement(this))
            if (!session || !session.config.showCalendar || session.config.inline || session.config.disabled || session.config.readOnly) return
            if (this.calendarOpen && !session.panel?.closing) { this.closeCalendar(); return }
            return this.openCalendar()
        },
        closeCalendar() {
            const session = sessions.get(ownerElement(this))
            if (!session || session.disposed) return
            session.generation++
            session.input.removeAttribute('aria-busy')
            // The surface owns exit completion. Keep the input's expanded state
            // and presentation until the same exit used by outside dismissal ends.
            if (session.panel) session.panel.close()
            else this.calendarOpen = false
        },
        async openCalendar() {
            const root = ownerElement(this), session = sessions.get(root)
            if (!session || session.disposed || (session.config.disabled && !session.config.inline)) return
            const generation = ++session.generation
            this.calendarOpen = true
            const trigger = session.input
            trigger?.setAttribute('aria-busy', 'true')
            session.opening = (async () => {
                try {
                    const { createCivilDateCalendar, createDateRangeCalendar } = await loadCalendar(session.config.mode === 'dateRange')
                    const clockFactory = session.config.withTime ? (await import('../time/controls.js')).createClockControls : null
                    if (session.disposed || generation !== session.generation || !isAssetElementActive(root)) return
                    session.panel ??= (session.config.mode === 'dateRange' ? createDateRangeCalendar : createCivilDateCalendar)({
                        root, anchor: root.querySelector('[x-ref="fieldShell"]'), config: session.config, clockFactory,
                        value: () => datePart(session.canonical, session.config.mode === 'dateRange'),
                        temporalValue: () => session.canonical,
                        request: (start, end, dates) => this.$wire.callSchemaComponentMethod(session.config.componentKey, dates ? 'getCivilPeriodAvailability' : 'getCivilDateAvailability', dates ? [dates] : [start, end]),
                        select: value => {
                            // Presentation is not a transport parser: commit the
                            // canonical selection directly, including null/clear.
                            session.canonical = value
                            session.ownState = storeSelection(value, session.config)
                            this.state = session.ownState
                            session.input.value = showSelection(value, session.config)
                            session.input.setAttribute('aria-invalid', String(Boolean(session.config.isInvalid)))
                            this.inputError = ''
                            session.validationGeneration = (session.validationGeneration || 0) + 1
                            session.segments?.set(value)
                            session.presentation.set(session.input.value, value?.start || value)
                            root.dispatchEvent(new CustomEvent('fff-date-change', { bubbles: true, detail: { value: this.state, canonical: value, origin: value === null ? 'clear' : 'calendar' } }))
                        },
                        closed: restoreFocus => { this.calendarOpen = false; root.dispatchEvent(new CustomEvent('fff-date-close', { bubbles: true, detail: { value: session.ownState } })); if (restoreFocus) { if (session.segments) session.segments.focus(); else trigger?.focus({ preventScroll: true }) } },
                    })
                    session.panel.open()
                    root.dispatchEvent(new CustomEvent('fff-date-open', { bubbles: true, detail: { value: session.ownState } }))
                } catch {
                    if (session.disposed || generation !== session.generation) return
                    this.calendarOpen = false
                    this.inputError = session.config.labels.availability_error
                } finally {
                    if (generation === session.generation) { trigger?.removeAttribute('aria-busy'); session.opening = null }
                }
            })()
            return session.opening
        },
        destroy() {
            const root = ownerElement(this), session = sessions.get(root)
            if (!session || session.disposed) return
            session.disposed = true
            session.generation++
            session.scope.dispose()
            session.panel?.dispose()
            sessions.delete(root)
        },
    }
}
