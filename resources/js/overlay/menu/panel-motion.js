/** Optional presentation belongs to a rendered panel, never to a component name. */
const presentations = new WeakMap()
export function registerMenuPanelMotion(panel, presentation) {
    presentations.set(panel, presentation)
    return () => { if (presentations.get(panel) === presentation) { presentations.delete(panel); presentation.dispose?.() } }
}
export function menuPanelMotion(panel) { return presentations.get(panel) }
