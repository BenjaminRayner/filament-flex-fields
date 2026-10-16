import { fitTime } from '../domain/local-date-time.js'
import { showSelection } from '../domain/selection.js'
import { startOfMonth, endOfMonth } from '@internationalized/date'
import { createImperativeMenuSurface } from '../../overlay/menu/imperative-surface.js'
import { createResourceScope } from '../../runtime/lifecycle/resource-scope.js'
import { dateValidation, formatCivilDate } from '../domain/civil-date.js'
import { canonicalDate, createCalendarModel } from './model.js'
import { createCalendarMotion } from './motion.js'
import { createCalendarAvailability } from './availability.js'

function element(tag, className, text) {
    const node = document.createElement(tag)
    if (className) node.className = className
    if (text !== undefined) node.textContent = text
    return node
}

export function createCivilDateCalendar({ root, anchor, config, value, temporalValue, clockFactory, request, select, closed }) {
    const scope = createResourceScope(), model = createCalendarModel(config, value())
    const panel = root.querySelector('[data-fff-calendar-template]').content.firstElementChild.cloneNode(true)
    const viewport = panel.querySelector('[data-calendar-viewport]'), content = panel.querySelector('[data-calendar-content]')
    const marker = panel.querySelector('[data-calendar-selection]'), motion = createCalendarMotion(viewport, marker)
    const monthTitle = panel.querySelector('[data-command="months"]'), yearTitle = panel.querySelector('[data-command="years"]')
    const status = panel.querySelector('[data-calendar-status]'), retry = panel.querySelector('[data-command="retry"]')
    const announcement = panel.querySelector('[data-calendar-announcement]')
    let pickGeneration = 0, selecting = false, releasePick = () => {}
    const weekdays = panel.querySelector('[data-calendar-weekdays]'), summary = panel.querySelector('[data-calendar-summary]')
    panel.querySelector('[data-calendar-footer]').hidden = config.inline
    const periodMode = config.mode === 'month' || config.mode === 'year'
    const initialMode = config.mode === 'month' ? 'months' : config.mode === 'year' ? 'years' : 'days'
    let month = model.initialMonth, mode = initialMode, open = false, disposed = false, focusValue = value() || model.today, window = null
    const availability = createCalendarAvailability(request, config.hasDateUnavailable && !config.readOnly && !config.disabled, () => { if (open && (mode === 'days' || periodMode)) updateAvailability() })
    let clock = null, clockTime = temporalValue?.()?.slice(11) || config.defaultTime
    const surface = config.inline ? null : createImperativeMenuSurface({ anchor, panel, presentation: 'panel', stablePlacement: true, width: 320, align: config.direction === 'rtl' ? 'end' : 'start', ownerIdPrefix: 'fff-date', ownershipAnchor: root, containsMenuInteraction: event => clock?.contains(event.target),
        onClose: () => { open = false; pickGeneration++; releasePick(); availability.suspend(); clock?.close(); closed(panel.contains(document.activeElement)) } })
    if (surface) scope.defer(surface.$watch('menuReady', ready => { if (ready) { markSelection(true); content.querySelector('[tabindex="0"]')?.focus({ preventScroll: true }) } }))
    if (config.inline) { panel.classList.remove('fff-teleported-menu'); panel.classList.add('fff-dt-calendar--inline'); root.querySelector('[data-fff-calendar-inline]').append(panel) }
    scope.defer(() => { clock?.dispose(); availability.dispose(); motion.dispose(); surface?.dispose(); panel.remove() })
    const locked = () => config.disabled || config.readOnly
    const blocked = date => locked() || dateValidation(date, config) !== null || availability.pending || availability.unavailable(date)
    const periodKey = date => config.mode === 'year' ? date.slice(0, 4) : date.slice(0, 7)
    function markSelection(immediate = false) {
        if (disposed) return
        const selected = value()
        const days = [...content.querySelectorAll('[data-date]')]
        const previous = days.find(day => day.getAttribute('aria-selected') === 'true')
        const next = days.find(day => day.dataset.date === selected)
        const distance = previous && next ? Math.hypot((next.offsetLeft - previous.offsetLeft) / (next.offsetWidth + 4), (next.offsetTop - previous.offsetTop) / (next.offsetHeight + 4)) : 0
        const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches
        for (const day of days) {
            const delay = !immediate && !reduced && distance ? day === next ? Math.min(260, 95 + 85 * Math.log(distance)) : day === previous ? Math.max(20, 130 / distance) : 0 : 0
            day.style.setProperty('--number-delay', `${Math.round(delay)}ms`)
            day.setAttribute('aria-selected', String(day === next))
        }
        summary.textContent = selected ? `${config.labels.selected} ${config.storesTime ? showSelection(temporalValue(), config) : formatCivilDate(selected, config.displayFormat, config.locale)}` : config.labels.choose_day
        panel.querySelector('[data-command="clear"]').disabled = !selected || locked()
        panel.querySelector('[data-command="clear"]').hidden = !config.clearable
        const target = mode === 'days' ? days.find(day => day.dataset.date === selected && !day.hidden) : periodMode && selected ? [...content.querySelectorAll('[data-period]')].find(button => periodKey(button.dataset.period) === periodKey(selected)) : null
        for (const button of content.querySelectorAll('[data-period]')) button.setAttribute('aria-selected', String(button === target))
        marker.classList.toggle('is-period', mode !== 'days')
        return motion.selection(target, immediate)
    }
    function updateAvailability() {
        for (const day of content.querySelectorAll('[data-date]')) day.disabled = blocked(day.dataset.date) || day.dataset.outsideHidden === 'true'
        if (periodMode && mode === initialMode) for (const button of content.querySelectorAll('[data-period]')) button.disabled = blocked(button.dataset.period)
        status.hidden = !availability.pending
        status.textContent = availability.error ? config.labels.availability_error : config.labels.loading
        retry.hidden = !availability.error
        content.setAttribute('aria-busy', String(availability.pending && !availability.error))
        updateTabStop()
    }
    function updateTabStop() {
        const buttons = [...content.querySelectorAll(periodMode && mode !== 'days' ? '[data-period]' : '[data-date]')].filter(day => !day.disabled && !day.hidden)
        const active = buttons.find(day => (day.dataset.date || day.dataset.period) === focusValue) || buttons.find(day => (day.dataset.date || day.dataset.period) === value()) || buttons.find(day => !day.classList.contains('is-outside')) || buttons[0]
        for (const day of content.querySelectorAll('[data-date], [data-period]')) day.tabIndex = day === active ? 0 : -1
    }
    function renderDays() {
        window = model.weeks(month)
        const grid = element('div', 'fff-dt-calendar__grid'); grid.setAttribute('role', 'grid'); grid.setAttribute('aria-label', `${model.monthLabel(month)} ${model.yearLabel(month)}`)
        weekdays.replaceChildren(...window.weekdays.map(name => element('span', '', name.slice(0, 2))))
        for (const week of window.weeks) {
            const row = element('div', 'fff-dt-calendar__week'); row.setAttribute('role', 'row')
            for (const { date, value: key, outside } of week) {
                const day = element('button', 'fff-dt-calendar__day')
                day.append(element('span', 'fff-dt-calendar__number', model.dayLabel(date)))
                day.type = 'button'; day.dataset.date = key; day.setAttribute('role', 'gridcell'); day.setAttribute('aria-label', model.dateLabel(date))
                day.classList.toggle('is-outside', outside); day.disabled = blocked(key)
                // Keep the cell for seven-column geometry when outside days are hidden.
                if (outside && !config.outsideDays) { day.style.visibility = 'hidden'; day.hidden = false; day.dataset.outsideHidden = 'true'; day.disabled = true }
                if (config.highlightToday && key === model.today) day.setAttribute('aria-current', 'date')
                row.append(day)
            }
            grid.append(row)
        }
        return grid
    }
    function renderPeriods() {
        const grid = element('div', 'fff-dt-calendar__periods'); grid.setAttribute('role', 'group')
        const periods = mode === 'months'
            ? Array.from({ length: month.calendar.getMonthsInYear(month) }, (_, index) => month.set({ month: index + 1, day: 1 }))
            : Array.from({ length: 12 }, (_, index) => startOfMonth(month.add({ years: index - 5 })))
        const seen = new Set()
        for (const date of periods) {
            const key = canonicalDate(date)
            if (date.era === 'BC' || date.year > 9999 || seen.has(key)) continue
            seen.add(key)
            const button = element('button', 'fff-dt-calendar__period', mode === 'months' ? model.monthLabel(date) : model.yearLabel(date))
            button.type = 'button'; button.dataset.period = key
            const first = mode === 'months' ? date : startOfMonth(date.set({ month: 1, day: 1 }))
            const last = mode === 'months' ? endOfMonth(date) : endOfMonth(date.set({ month: date.calendar.getMonthsInYear(date), day: 1 }))
            button.disabled = (config.minValue && canonicalDate(last) < config.minValue) || (config.maxValue && canonicalDate(first) > config.maxValue) || locked()
            if (periodMode && mode === initialMode) button.disabled = blocked(key)
            button.setAttribute('aria-selected', String(periodMode ? value() && periodKey(value()) === periodKey(key) : mode === 'months' ? date.month === month.month : date.year === month.year && date.era === month.era))
            grid.append(button)
        }
        return grid
    }
    function navigationTarget(direction) { return month.add(mode === 'days' ? { months: direction } : { years: direction * (mode === 'years' ? 12 : 1) }) }
    function navigationAllowed(next) {
        if (next.era === 'BC' || next.year > 9999) return false
        if (!periodMode || mode === 'days') return model.monthAllowed(next)
        const first = canonicalDate(next.set({ year: Math.max(1, next.year - (mode === 'years' ? 5 : 0)), month: 1, day: 1 }))
        const last = canonicalDate(endOfMonth(next.set({ year: Math.min(9999, next.year + (mode === 'years' ? 6 : 0)), month: 12, day: 1 })))
        return (!config.minValue || last >= config.minValue) && (!config.maxValue || first <= config.maxValue)
    }
    function render(direction = 0, requestFocus = false) {
        monthTitle.hidden = periodMode
        const todayButton = panel.querySelector('[data-command="today"]'); if (todayButton) todayButton.hidden = periodMode
        motion.titles([[monthTitle, model.monthLabel(month)], [yearTitle, model.yearLabel(month)]], direction)
        monthTitle.setAttribute('aria-pressed', String(mode === 'months')); yearTitle.setAttribute('aria-pressed', String(mode === 'years'))
        motion.replace(content, mode === 'days' ? renderDays() : renderPeriods(), config.direction === 'rtl' ? -direction : direction)
        markSelection(true); updateTabStop()
        for (const [command, sign] of [['previous', -1], ['next', 1]]) panel.querySelector(`[data-command="${command}"]`).disabled = !navigationAllowed(navigationTarget(sign))
        announcement.textContent = `${model.monthLabel(month)} ${model.yearLabel(month)}`
        weekdays.hidden = mode !== 'days'
        status.hidden = true; retry.hidden = true
        if (mode === 'days') { availability.load(window.start, window.end); updateAvailability() }
        else if (periodMode && mode === initialMode) { const dates = [...content.querySelectorAll('[data-period]')].map(button => button.dataset.period).sort(); if (dates.length) { window = { start: dates[0], end: dates.at(-1), dates }; availability.load(window.start, window.end, false, dates); updateAvailability() } }
        else availability.suspend()
        if (requestFocus) content.querySelector('[tabindex="0"], [data-period]:not(:disabled)')?.focus({ preventScroll: true })
        if (surface?.menuReady) surface.updateMenuPosition({ reveal: false })
    }
    function move(next, requestFocus = false) {
        const direction = next.compare(month) > 0 ? 1 : next.compare(month) < 0 ? -1 : 0
        if (!navigationAllowed(next)) return
        month = startOfMonth(next); render(direction, requestFocus)
    }
    function pick(date) {
        if (blocked(date)) return
        focusValue = date
        // Paint the destination before updating the controlled state, which may
        // itself notify setValue(). Both use the same interruptible disc.
        const ticket = ++pickGeneration
        selecting = true
        try { clockTime = fitTime(date, clockTime, config); select(config.storesTime ? `${date}T${clockTime}` : date); clock?.render() } finally { selecting = false }
        markSelection()
        releasePick()
        if (config.closeOnSelect && !config.inline) {
            if (matchMedia('(prefers-reduced-motion: reduce)').matches) surface.closeTeleportedMenu()
            else {
                // The reference keeps the selection feedback visible for 300ms;
                // this is presentation timing, never an asset readiness delay.
                const timer = setTimeout(() => { releasePick(); if (open && ticket === pickGeneration && !disposed) surface.closeTeleportedMenu() }, 300)
                releasePick = scope.defer(() => clearTimeout(timer))
            }
        }
        return
    }
    scope.listen(panel, 'click', event => {
        const button = event.target.closest('button')
        if (!button || button.disabled) return
        if (button.dataset.date) return pick(button.dataset.date)
        if (button.dataset.period && periodMode && mode === initialMode) return pick(button.dataset.period)
        if (button.dataset.period) { month = startOfMonth(model.convert(button.dataset.period)); mode = periodMode ? initialMode : mode === 'years' ? 'months' : 'days'; render(0, true); return }
        const command = button.dataset.command
        if (command === 'clear') { pickGeneration++; select(null); markSelection(true); if (!config.inline) surface.closeTeleportedMenu() }
        else if (command === 'previous' || command === 'next') move(navigationTarget(command === 'previous' ? -1 : 1))
        else if (command === 'months' || command === 'years') { if (config.mode === 'year') return; mode = mode === command ? initialMode : command; render() }
        else if (command === 'today') { mode = 'days'; focusValue = model.today; move(model.convert(model.today), true); if (!blocked(model.today) && value() !== model.today) { selecting = true; try { clockTime = fitTime(model.today, clockTime, config); select(config.storesTime ? `${model.today}T${clockTime}` : model.today); clock?.render() } finally { selecting = false } markSelection() } }
        else if (command === 'retry' && window) availability.load(window.start, window.end, true, window.dates || null)
    })
    scope.listen(content, 'keydown', event => {
        const button = event.target.closest('[data-date], [data-period]')
        if (!button || event.ctrlKey || event.metaKey || event.altKey) return
        if (button.dataset.period) {
            const buttons = [...content.querySelectorAll('[data-period]:not(:disabled)')], index = buttons.indexOf(button), step = { ArrowLeft: config.direction === 'rtl' ? 1 : -1, ArrowRight: config.direction === 'rtl' ? -1 : 1, ArrowUp: -3, ArrowDown: 3 }[event.key]
            if (step !== undefined || event.key === 'Home' || event.key === 'End') { event.preventDefault(); const target = buttons[Math.max(0, Math.min(buttons.length - 1, event.key === 'Home' ? 0 : event.key === 'End' ? buttons.length - 1 : index + step))]; if (target) { focusValue = target.dataset.period; updateTabStop(); target.focus({ preventScroll: true }) } }
            else if (event.key === 'PageUp' || event.key === 'PageDown') { event.preventDefault(); move(navigationTarget(event.key === 'PageDown' ? 1 : -1), true) }
            return
        }
        const date = model.convert(button.dataset.date), rtl = config.direction === 'rtl'
        let next
        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') next = date.add({ days: (event.key === 'ArrowRight' ? 1 : -1) * (rtl ? -1 : 1) })
        else if (event.key === 'ArrowUp' || event.key === 'ArrowDown') next = date.add({ days: event.key === 'ArrowDown' ? 7 : -7 })
        else if (event.key === 'PageUp' || event.key === 'PageDown') next = date.add({ [event.shiftKey ? 'years' : 'months']: event.key === 'PageDown' ? 1 : -1 })
        else if (event.key === 'Home' || event.key === 'End') { const days = [...button.parentElement.children]; next = model.convert(days[event.key === 'Home' ? 0 : 6].dataset.date) }
        else return
        event.preventDefault()
        const key = canonicalDate(next)
        if (dateValidation(key, config)) return
        focusValue = key
        if (next.month !== month.month || next.year !== month.year || next.era !== month.era) move(next, true)
        else { updateTabStop(); content.querySelector(`[data-date="${key}"]:not(:disabled)`)?.focus({ preventScroll: true }) }
    })
    scope.listen(content, 'focusin', event => { if (event.target.dataset.date || event.target.dataset.period) { focusValue = event.target.dataset.date || event.target.dataset.period; updateTabStop() } })
    if (config.withTime && clockFactory) clock = clockFactory(root, panel, config, {
        get: () => clockTime,
        set: (_, time) => { clockTime = time; if (value()) select(`${value()}T${time}`); markSelection(true) },
        valid: (_, time) => !value() || fitTime(value(), time, config) === time,
    })
    if (typeof ResizeObserver === 'function') { const observer = new ResizeObserver(() => { if (!disposed && (config.inline || open)) markSelection(true) }); observer.observe(viewport); scope.defer(() => observer.disconnect()) }
    return {
        open() {
            if (disposed) return
            pickGeneration++; releasePick(); clockTime = temporalValue?.()?.slice(11) || config.defaultTime; clock?.render(); open = true; panel.classList.toggle('is-dark', root.closest('.dark') !== null)
            mode = initialMode
            const selected = value(); if (selected) { month = startOfMonth(model.convert(selected)); focusValue = selected }
            render()
            if (surface) { surface.cancelClosing(); surface.menuOpen = true }
        },
        get closing() { return Boolean(surface?.__fffPanelClosing || surface?.__fffSheetClosing) },
        close() { if (!config.inline) surface?.closeTeleportedMenu() },
        setValue(selected) { if (!disposed && open) { if (selected) focusValue = selected; if (!selecting) { clock?.close(); clockTime = temporalValue?.()?.slice(11) || config.defaultTime; clock?.render(); markSelection() }; updateTabStop() } },
        dispose() { if (disposed) return; disposed = true; open = false; scope.dispose() },
    }
}
