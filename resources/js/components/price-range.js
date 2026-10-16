import { createPriceRangeRuntime } from '../price-range/runtime.js'

export default function priceRangeFormComponent(configuration) {
    return createPriceRangeRuntime(configuration)
}
