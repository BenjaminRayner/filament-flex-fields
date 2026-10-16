/** Deferred writes never request a render merely to enter or leave an editor. */
export function writeSlugState(owner, path, value) {
    if (!path || !owner.$wire) return
    const set = owner.$wire.$set ?? owner.$wire.set
    if (typeof set !== 'function') throw new Error('Missing Livewire state adapter.')
    const result = set.call(owner.$wire, path, value, false)
    // Livewire's deferred setter is synchronous apart from its resolved promise.
    result?.catch?.(() => { owner.uniqueError = owner.labels.error })
}
export function readSlugState(owner, path, fallback = null) {
    if (!path || !owner.$wire) return fallback
    const get = owner.$wire.$get ?? owner.$wire.get
    return typeof get === 'function' ? get.call(owner.$wire, path) ?? fallback : fallback
}
export function watchSlugState(owner, scope, path, callback) {
    if (!path || typeof owner.$wire?.$watch !== 'function') return
    const release = owner.$wire.$watch(path, value => { if (!scope.disposed) callback(value) })
    if (typeof release === 'function') scope.defer(release)
}
export function callSlugMethod(owner, method, parameters) {
    if (!owner.componentKey || typeof owner.$wire?.callSchemaComponentMethod !== 'function') {
        return Promise.reject(new Error('Missing schema method adapter.'))
    }
    return Promise.resolve(owner.$wire.callSchemaComponentMethod(owner.componentKey, method, parameters))
}
