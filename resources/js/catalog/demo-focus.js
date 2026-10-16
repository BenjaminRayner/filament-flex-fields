import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'

// Demonstrations are not login forms. Password-manager heuristics may focus
// them without autofocus; never apply this policy to application-owned fields.
const documents = new WeakMap()
export function guardDemoFocus(input, ownerScope) {
    const doc = input.ownerDocument
    if (!doc || input.autofocus) return
    let policy = documents.get(doc)
    if (!policy) {
        const scope = createResourceScope()
        policy = { scope, owners: 0, interacted: false }
        documents.set(doc, policy)
        const activate = event => {
            if (!event.isTrusted) return
            policy.interacted = true
            scope.dispose()
        }
        scope.listen(doc, 'pointerdown', activate, { capture: true })
        scope.listen(doc, 'keydown', activate, { capture: true })
    }
    policy.owners++
    const preventAutomaticFocus = () => {
        if (!policy.interacted && !input.autofocus && doc.activeElement === input) input.blur()
    }
    ownerScope.listen(input, 'focus', preventAutomaticFocus)
    ownerScope.defer(() => {
        if (--policy.owners === 0) {
            policy.scope.dispose()
            if (!policy.interacted) documents.delete(doc)
        }
    })
    preventAutomaticFocus()
}
