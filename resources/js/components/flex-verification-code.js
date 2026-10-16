import { createVerificationRuntime } from '../verification-code/runtime.js'

export default function flexVerificationCodeFormComponent(configuration) {
    return createVerificationRuntime(configuration)
}
