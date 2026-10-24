/** Read only this owner's inert transport; no document cache of form values. */
export function readRenderedSelectConfiguration(root) {
    const template = root?.querySelector?.(':scope > template[data-fff-select-configuration]')
    if (!template) throw new Error('Select configuration is missing from its owner.')
    const text = template.content?.textContent ?? template.textContent ?? ''
    const configuration = JSON.parse(text)
    if (configuration === null || typeof configuration !== 'object' || Array.isArray(configuration)) {
        throw new Error('Select configuration must be an object.')
    }
    return configuration
}
