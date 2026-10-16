import { createClockMenu } from './menu.js'
import { createResourceScope } from '../../runtime/lifecycle/resource-scope.js'
import { showClock } from '../domain/local-date-time.js'

/** Compact clock rows compose the real Text Input shell and Select menu owner. */
export function createClockControls(root, panel, config, { get, set, valid }) {
    const scope = createResourceScope(), rows = [...panel.querySelectorAll('[data-clock-row]')], menus = new Map()
    let disposed = false
    function render() {
        if (disposed) return
        rows.forEach((row, index) => {
            const time = get(index), trigger = row.querySelector('[data-clock-trigger]'), second = row.querySelector('[data-clock-seconds]')
            trigger.textContent = showClock(time, config.hourCycle === 12 ? (config.forceLeadingZeros ? 'h:i A' : 'g:i A') : 'H:i'); trigger.disabled = config.disabled || config.readOnly
            if (second) { second.textContent = time.slice(6); second.disabled = trigger.disabled }
        })
    }
    scope.listen(panel.querySelector('[data-calendar-time]'), 'click', event => {
        const trigger = event.target.closest('[data-clock-trigger], [data-clock-seconds]') || event.target.closest('.fff-flex-text-input__shell')?.querySelector('[data-clock-trigger]')
        if (!trigger || trigger.disabled) return
        event.stopPropagation()
        const index = Number(trigger.closest('[data-clock-row]').dataset.clockRow)
        if (!menus.has(trigger)) menus.set(trigger, createClockMenu({ root, trigger, config, parent: panel, get: () => get(index), set: time => { set(index, time); render() }, valid: time => valid(index, time) }))
        menus.get(trigger).toggle(trigger.hasAttribute('data-clock-seconds'))
    })
    render()
    return {
        render,
        contains(target) { return [...menus.values()].some(menu => menu.contains(target)) },
        close() { for (const menu of menus.values()) menu.close(); render() },
        dispose() { if (disposed) return; disposed = true; scope.dispose(); for (const menu of menus.values()) menu.dispose(); menus.clear() },
    }
}
