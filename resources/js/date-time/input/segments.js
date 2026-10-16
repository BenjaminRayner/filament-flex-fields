import { createDateSegmentModel } from './segment-model.js'
import { readSelection } from '../domain/selection.js'
import { decimalText } from './digit-text.js'

/** Owned segmented editing: no free text ever enters the date transport. */
export function createDateSegments({ root, config, scope, canonical, change, open, validate }) {
    const host = root.querySelector('[data-date-segments]')
    if (!host) return null
    const nodes = [...host.querySelectorAll('[data-date-segment]')]
    const model = createDateSegmentModel(config, canonical)
    let buffer = '', bufferNode = null, active = null, composing = false
    const writable = () => !config.disabled && !config.readOnly
    const address = node => [Number(node.dataset.group), Number(node.dataset.segment)]
    function render() {
        for (const node of nodes) {
            const [group, index] = address(node), value = model.get(group, index)
            node.textContent = node === bufferNode && buffer ? buffer : model.text(group, index)
            node.dataset.empty = String(value === null)
            node.setAttribute('aria-valuemax', String(model.limit(group, index)))
            node.setAttribute('aria-valuetext', node.textContent)
            if (value === null) node.removeAttribute('aria-valuenow')
            else node.setAttribute('aria-valuenow', String(value))
        }
    }
    function publish(partial = false, origin = 'input') {
        render()
        change(partial ? null : model.selection(), { empty: model.empty(), raw: model.raw(), editing: Boolean(buffer), origin })
    }
    function focusAt(index) { if (!host.contains(document.activeElement)) return; nodes[Math.max(0, Math.min(nodes.length - 1, index))]?.focus({ preventScroll: true }) }
    function digits(node, text) {
        text = decimalText(text)
        if (!writable() || !/^\d+$/.test(text)) { render(); return }
        const [group, index] = address(node), segment = model.pattern[index]
        if (segment.type === 'period') { model.set(group, index, Number(text.at(-1)) % 2); buffer = ''; publish(); return }
        if (bufferNode !== node) { bufferNode = node; buffer = '' }
        for (const digit of text) {
            let next = buffer + digit
            if (next.length > segment.digits || Number(next) > model.limit(group, index)) next = digit
            if (Number(next) > model.limit(group, index)) continue
            buffer = next
            model.set(group, index, Number(next) >= segment.min ? Number(next) : null, false)
        }
        const finished = buffer.length >= segment.digits || Number(buffer + '0') > model.limit(group, index)
        if (finished && model.get(group, index) !== null) {
            model.set(group, index, model.get(group, index)); buffer = ''; publish(); focusAt(nodes.indexOf(node) + 1)
        } else publish(segment.type === 'year')
    }
    scope.listen(host, 'focusin', event => {
        const node = event.target.closest('[data-date-segment]'); if (!node) return
        active = node; bufferNode = node; buffer = ''
    })
    scope.listen(host, 'focusout', event => {
        if (event.target.matches('[data-date-segment]') && buffer) {
            const [group, index] = address(event.target)
            model.set(group, index, model.get(group, index)); buffer = ''; publish()
        }
        if (!host.contains(event.relatedTarget)) validate?.()
    })
    scope.listen(host, 'keydown', event => {
        const node = event.target.closest('[data-date-segment]'); if (!node) return
        if (event.key === 'F4' || (event.altKey && event.key === 'ArrowDown')) { event.preventDefault(); open(); return }
        if (event.key === 'Tab') return
        if (event.ctrlKey || event.metaKey) {
            if (['v', 'c'].includes(event.key.toLowerCase())) return
            event.preventDefault(); return
        }
        if (['ArrowLeft', 'ArrowRight'].includes(event.key)) {
            event.preventDefault(); focusAt(nodes.indexOf(node) + ((event.key === 'ArrowLeft' ? -1 : 1) * (config.direction === 'rtl' ? -1 : 1))); return
        }
        event.preventDefault()
        if (!writable()) return
        const [group, index] = address(node), segment = model.pattern[index]
        if (segment.type === 'period' && /^[ap]$/i.test(event.key)) { model.set(group, index, event.key.toLowerCase() === 'p' ? 1 : 0); buffer = ''; publish(); return }
        if (/^\d$/.test(decimalText(event.key))) { digits(node, event.key); return }
        if (['Backspace', 'Delete'].includes(event.key)) { model.set(group, index, null); buffer = ''; publish(); return }
        if (['ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)) {
            buffer = ''
            const max = model.limit(group, index), value = model.get(group, index)
            const next = event.key === 'Home' ? segment.min : event.key === 'End' ? max : value === null ? segment.min : value + (event.key === 'ArrowUp' ? 1 : -1)
            model.set(group, index, next > max ? segment.min : next < segment.min ? max : next)
            publish()
        } else if (['/', '.', '-', ':', ' ', 'Enter'].includes(event.key)) focusAt(nodes.indexOf(node) + 1)
    })
    scope.listen(host, 'beforeinput', event => {
        event.preventDefault()
        const node = event.target.closest('[data-date-segment]'); if (!node || composing || !writable()) return
        if (event.inputType.startsWith('delete')) { const [group, index] = address(node); model.set(group, index, null); buffer = ''; publish() }
        else digits(node, event.data || '')
    })
    scope.listen(host, 'input', render)
    scope.listen(host, 'compositionstart', () => { composing = true })
    scope.listen(host, 'compositionend', event => { composing = false; digits(event.target, event.data || '') })
    scope.listen(host, 'paste', event => {
        event.preventDefault(); if (!writable()) return
        const text = decimalText(event.clipboardData?.getData('text/plain') || '')
        const value = readSelection(text, config, config.displayFormat) ?? readSelection(text, config)
        if (value) { model.hydrate(value); buffer = ''; publish(); validate?.() }
        else if (/^\d+$/.test(text) && text.length <= 4) digits(event.target, text)
    })
    scope.listen(host, 'drop', event => event.preventDefault())
    render()
    return { set(value) { model.hydrate(value); buffer = ''; render() },
        clear() { model.hydrate(null); buffer = ''; publish(false, 'clear') },
        focus() { (active?.isConnected ? active : nodes[0])?.focus({ preventScroll: true }) },
    }
}
