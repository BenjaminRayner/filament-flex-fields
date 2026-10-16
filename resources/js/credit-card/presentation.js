import { cardState, displayCardNumber, detectCardBrand, formattedValue, CARD_MASK_CHAR, replaceInputValue } from './format.js'

export function createCardPresentation(root, labels = {}) {
    const scene = root.querySelector('[data-card-scene]'), front = root.querySelector('[data-card-front]')
    const flip = root.querySelector('[data-card-flip]')
    const preview = new Map(Array.from(root.querySelectorAll('[data-card-preview]'), node => [node.dataset.cardPreview, node]))
    let inputs = Array.from(root.querySelectorAll('[data-card-input]')), lastBrand
    const text = (key, value) => { const node = preview.get(key); if (node && node.textContent !== value) node.textContent = value }
    return {
        paint(value, flipped, disabled, readOnly) {
            if (inputs.some(input => !root.contains(input))) inputs = Array.from(root.querySelectorAll('[data-card-input]'))
            const state = cardState(value), brand = detectCardBrand(state.number)
            text('number', displayCardNumber(state.number))
            text('name', state.name.trim().toUpperCase() || labels.name || '')
            text('expiry', state.expiry || labels.expiry || '')
            text('cvv', state.cvv.padEnd(Math.max(3, state.cvv.length), CARD_MASK_CHAR))
            if (brand !== lastBrand) {
                for (const name of ['unknown', 'visa', 'mastercard']) front?.classList.toggle(`is-brand-${name}`, name === brand)
                lastBrand = brand
            }
            front?.classList.toggle('has-long-number', state.number.length > 16)
            scene?.classList.toggle('is-flipped', flipped)
            if (flip) { flip.disabled = disabled; flip.setAttribute('aria-pressed', String(flipped)) }
            for (const input of inputs) {
                if (input.disabled !== disabled) input.disabled = disabled
                if (input.readOnly !== readOnly) input.readOnly = readOnly
                replaceInputValue(input, formattedValue(input.dataset.cardInput, state[input.dataset.cardInput]))
            }
        },
    }
}
