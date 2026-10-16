import { createResourceScope } from '../../runtime/lifecycle/resource-scope.js'

/** Single-flight, latest-value verification; ignored results cannot revive a disposed field. */
export function createInputVerification(owner) {
    const scope = createResourceScope(), input = owner.$refs.input
    let timer, generation = 0, inFlight = false, pending = false
    const run = async () => {
        if (scope.disposed || owner.composing || !owner.options.editable || !owner.options.verificationEnabled) return
        const value = input.value
        if (Array.from(value).length < owner.options.verificationMinLength) { owner.verificationState = 'idle'; owner.verificationMessage = ''; return }
        if (inFlight) { pending = true; return }
        const current = ++generation
        const valid = () => !scope.disposed && current === generation && input.value === value && owner.options.verificationEnabled
        inFlight = true; owner.verificationState = 'pending'; owner.verificationMessage = owner.options.messages.verification_pending
        try {
            const result = await owner.$wire.callSchemaComponentMethod(owner.options.componentKey, 'verifyTextInputForJs', { value })
            if (!valid()) return
            if (!['idle', 'success', 'error', 'warning'].includes(result?.state) || typeof result.message !== 'string') throw new Error('Invalid verification')
            owner.verificationState = result.state; owner.verificationMessage = result.message
        } catch {
            if (valid()) { owner.verificationState = 'error'; owner.verificationMessage = owner.options.messages.verification_error }
        } finally {
            inFlight = false
            if (pending) { pending = false; if (!scope.disposed) run() }
        }
    }
    scope.defer(() => { generation++; clearTimeout(timer); owner.verificationState = 'idle'; owner.verificationMessage = '' })
    return { schedule() {
        generation++; clearTimeout(timer); owner.verificationState = 'idle'; owner.verificationMessage = ''
        timer = setTimeout(run, owner.options.verificationDebounce)
    }, dispose() { scope.dispose() } }
}
