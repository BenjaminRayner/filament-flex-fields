import { createBarcodeRuntime } from '../barcode/runtime.js'

export default function barcodeScannerFieldFormComponent(configuration) {
    return createBarcodeRuntime(configuration)
}
