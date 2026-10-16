import { createCardRuntime } from '../credit-card/runtime.js'
export { CARD_MASK_CHAR, detectCardBrand, formatCardNumberInput, formatExpiryInput, displayCardNumber } from '../credit-card/format.js'
export default function creditCardFormComponent(configuration) { return createCardRuntime(configuration) }
