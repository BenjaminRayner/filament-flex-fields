import { writeSlugState } from './wire.js'

// A repeated state path can have multiple rendered editors. Closing one must not
// clear the form's pending flag while another editor still owns an open draft.
const pendingByWire = new WeakMap()
export function createPendingEdit(owner, identity) {
    const wire = owner.$wire
    const path = owner.inlineEditPendingPath
    if (!wire || !path) return { set() {} }
    return {
        set(pending) {
            let paths = pendingByWire.get(wire)
            if (!paths) {
                if (!pending) return
                paths = new Map(); pendingByWire.set(wire, paths)
            }
            let editors = paths.get(path)
            if (pending) {
                if (!editors) { editors = new Set(); paths.set(path, editors) }
                editors.add(identity)
            } else {
                if (!editors?.delete(identity)) return
                if (!editors.size) paths.delete(path)
            }
            writeSlugState(owner, path, Boolean(editors?.size))
            if (!paths.size) pendingByWire.delete(wire)
        },
    }
}
