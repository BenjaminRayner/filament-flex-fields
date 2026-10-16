import { createImperativeMenuSurface } from '../../overlay/menu/imperative-surface.js'
import { createResourceScope } from '../../runtime/lifecycle/resource-scope.js'
import { calculateExpression, calculatorResult } from '../domain/expression.js'
import { bindFloatingPanelDrag } from '../../overlay/floating-panel-drag.js'

const documents = new WeakMap()
const sessions = new WeakMap()

/** One actual panel per document; sessions are weakly owned by rendered field nodes. */
class CalculatorDocumentRuntime {
    constructor(document) {
        this.document = document
        this.fields = new Set()
        this.fieldRoots = new WeakMap()
        this.panel = null
        this.surface = null
        this.owner = null
        this.effects = null
        this.disposed = false
        this.floatingPosition = null
        this.drag = null
    }

    register(field) {
        this.fields.add(field)
        this.fieldRoots.set(field.root, field)
        return () => {
            if (!this.fields.delete(field)) return
            this.fieldRoots.delete(field.root)
            if (this.owner === field) this.close(true)
            if (this.fields.size !== 0) return
            this.disposed = true
            this.close(true)
            this.effects?.dispose()
            this.panel?.remove()
            documents.delete(this.document)
        }
    }

    open(field, anchor, {focus = true} = {}) {
        if (this.disposed || !this.fields.has(field) || !field.root.isConnected || !anchor?.isConnected) return
        const input = field.input()
        if (!input || input.disabled || input.readOnly) return
        const configuration = field.configuration()
        if (configuration.disabled) return
        if (this.owner === field && this.surface?.menuOpen) return
        const transferring = Boolean(this.surface?.menuOpen && !this.surface.__fffPanelClosing && !this.surface.__fffSheetClosing)
        if (transferring) {
            this.drag?.cancel()
            this.anchor?.setAttribute('aria-expanded', 'false')
            if (this.surface.menuReady && !this.panel.classList.contains('fff-teleported-menu--sheet')) {
                this.floatingPosition ??= this.panelPosition()
            }
        } else this.close(true)
        if (!this.panel) {
            this.panel = field.root.querySelector('[data-fff-calculator-template]')?.content.firstElementChild?.cloneNode(true)
            if (!this.panel) return
            this.effects = createResourceScope()
            this.drag = bindFloatingPanelDrag({panel:this.panel,
                handle:this.panel.querySelector('[data-calculator-drag-handle]'),
                isEnabled:() => this.surface?.menuOpen && this.surface.menuReady
                    && this.panel.classList.contains('is-open') && !this.panel.classList.contains('fff-teleported-menu--sheet'),
                onPosition:point => {
                    this.floatingPosition = point
                    this.surface?.updateMenuPosition({reveal:false})
                },
            })
            this.effects.defer(() => this.drag.dispose())
            this.effects.listen(this.panel, 'click', event => {
                const button = event.target.closest('[data-calculator-key]')
                if (button && this.panel.contains(button)) {
                    event.stopPropagation()
                    this.command(button.dataset.calculatorKey)
                }
            })
            this.effects.listen(this.panel, 'keydown', event => {
                if (!this.surface?.menuOpen || event.altKey || event.ctrlKey || event.metaKey || event.isComposing) return
                const key = event.key
                if (/^[\d()+\-*/%.]$/.test(key) || ['Backspace', 'Delete', 'Enter', 'Escape'].includes(key)) {
                    event.preventDefault()
                    event.stopPropagation()
                    this.command({Backspace:'backspace',Delete:'AC',Enter:'=',Escape:'close'}[key] ?? key)
                }
            })
        }
        this.owner = field
        this.configuration = configuration
        this.anchor = anchor
        this.session = sessions.get(field.root)
        if (!this.session) {
            this.session = {expression: String(input.value || '0'), evaluated:false}
            sessions.set(field.root, this.session)
        }
        this.panel.querySelector('[data-calculator-label]').textContent = configuration.label
        this.render()
        anchor.setAttribute('aria-expanded', 'true')
        if (transferring) {
            this.surface.retarget(anchor, field.root.querySelector('[data-fff-calculator-portal-host]'))
            return
        }
        this.surface = createImperativeMenuSurface({anchor, panel:this.panel, width:236, align:'end',
            ownerIdPrefix:'fff-calculator', ownershipAnchor:field.root.querySelector('[data-fff-calculator-portal-host]'),
            controllerOwner:this.panel,
            containsMenuInteraction:event => this.isFieldInteraction(event),
            resolveFloatingPosition:() => this.floatingPosition,
            onClose:() => this.didClose(),
        })
        this.surface.$watch('menuReady', ready => {
            if (ready && !this.panel.classList.contains('fff-teleported-menu--sheet')) {
                this.floatingPosition ??= this.panelPosition()
            }
            if (ready && focus && this.owner === field) this.panel.querySelector('[data-calculator-key="AC"]')?.focus({preventScroll:true})
        })
        this.surface.menuOpen = true
    }

    panelPosition() {
        // Use layout coordinates, not an in-flight enter animation's scaled rect.
        const left = Number.parseFloat(this.panel.style.left)
        const top = Number.parseFloat(this.panel.style.top)
        return Number.isFinite(left) && Number.isFinite(top) ? {left, top} : null
    }

    isFieldInteraction(event) {
        const path = event.composedPath?.() ?? [event.target]
        for (const node of path) {
            if (!node?.matches?.('[data-fff-calculator-trigger], [data-fff-text-input] input')) continue
            const field = this.fieldRoots.get(node.closest('[data-fff-calculator-field]'))
            if (!field || !this.fields.has(field) || !field.root.isConnected) continue
            const input = field.input()
            if (input && !input.disabled && !input.readOnly && !field.configuration().disabled) return true
        }
        return false
    }

    didClose() {
        this.drag?.cancel()
        this.anchor?.setAttribute('aria-expanded', 'false')
        this.owner = null
    }

    close(immediate = false) {
        this.drag?.cancel()
        const surface = this.surface
        if (!surface) return
        if (immediate) {
            surface.closeTeleportedMenuImmediate('calculator-owner-change')
            surface.dispose()
            this.surface = null
            this.didClose()
        } else surface.closeTeleportedMenu()
    }

    command(key) {
        if (!this.owner || !this.surface?.menuOpen) return
        const input = this.owner.input()
        if (!input || input.disabled || input.readOnly) { this.close(true); return }
        if (key === 'close') { this.close(); return }
        if (key === 'apply') {
            try {
                const value = calculatorResult(calculateExpression(this.session.expression), this.configuration)
                input.value = String(value)
                input.dispatchEvent(new Event('input', {bubbles:true}))
                input.dispatchEvent(new Event('change', {bubbles:true}))
                this.close()
                input.focus({preventScroll:true})
            } catch { this.showError() }
            return
        }
        if (key === '=') {
            try {
                this.session.expression = String(calculatorResult(calculateExpression(this.session.expression), this.configuration))
                this.session.evaluated = true
            } catch { this.showError(); return }
        } else if (key === 'AC') {
            this.session.expression = '0'
            this.session.evaluated = false
        } else if (key === 'backspace') {
            this.session.expression = this.session.expression.slice(0, -1) || '0'
            this.session.evaluated = false
        } else if (/^[\d()+\-*/%.×÷−]$/.test(key)) {
            if (this.session.expression.length >= 2048) return
            const startsNew = /[\d.(]/.test(key) && (this.session.evaluated || this.session.expression === '0')
            this.session.expression = startsNew ? key : this.session.expression + key
            this.session.evaluated = false
        } else return
        this.render()
    }

    render() {
        this.panel.querySelector('[data-calculator-expression]').textContent = this.session.expression
        const result = this.panel.querySelector('[data-calculator-result]')
        const error = this.panel.querySelector('[data-calculator-error]')
        error.hidden = true
        try {
            result.textContent = String(calculatorResult(calculateExpression(this.session.expression), this.configuration))
            this.panel.querySelector('[data-calculator-key="apply"]').disabled = false
        } catch {
            result.textContent = '—'
            this.panel.querySelector('[data-calculator-key="apply"]').disabled = true
        }
    }

    showError() { this.panel.querySelector('[data-calculator-error]').hidden = false }
}

export function registerCalculatorField(field) {
    const document = field.root.ownerDocument
    let runtime = documents.get(document)
    if (!runtime) { runtime = new CalculatorDocumentRuntime(document); documents.set(document, runtime) }
    const release = runtime.register(field)
    return {open:(anchor, options) => runtime.open(field, anchor, options),
        focus:() => { if (runtime.surface?.menuOpen && runtime.owner !== field) runtime.open(field, field.root.querySelector('[data-fff-calculator-trigger]'), {focus:false}) },
        release}
}
