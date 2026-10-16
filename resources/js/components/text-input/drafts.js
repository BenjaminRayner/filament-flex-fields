import { createResourceScope } from '../../runtime/lifecycle/resource-scope.js'

/** Opt-in, expiring drafts. No password support, no implicit field-name persistence. */
export function createInputDraft(owner, skipRestore = false) {
    const scope = createResourceScope()
    let timer
    const key = `fff:draft:v1:${location.pathname}:${owner.options.draftKey}`
    const store = () => owner.options.draftStorage === 'local' ? localStorage : sessionStorage
    scope.defer(() => clearTimeout(timer))
    if (!skipRestore && !owner.$refs.input.value) {
        try {
            const data = JSON.parse(store().getItem(key) || 'null')
            if (data && typeof data.value === 'string' && data.value.length <= 8192 && Number.isFinite(data.saved) && data.saved <= Date.now() && Date.now() - data.saved <= owner.options.draftLifetime * 1000) {
                owner.commitToolValue(data.value)
                owner.showStatus(owner.options.messages.draft_restored)
            } else if (data) store().removeItem(key)
        } catch { owner.showStatus(owner.options.messages.draft_error) }
    }
    return {
        schedule() {
            clearTimeout(timer)
            timer = setTimeout(() => {
                if (scope.disposed || !owner.options.editable || owner.options.isPassword || !owner.$el.isConnected) return
                try {
                    const value = owner.$refs.input.value
                    if (!value) store().removeItem(key)
                    else if (value.length <= 8192) store().setItem(key, JSON.stringify({ saved: Date.now(), value }))
                } catch { owner.showStatus(owner.options.messages.draft_error) }
            }, 400)
        },
        dispose() { scope.dispose() },
    }
}
