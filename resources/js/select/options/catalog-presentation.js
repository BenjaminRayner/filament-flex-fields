const presentations = new WeakMap()
const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]))

/** Shared Select catalog projection; immutable metadata is smaller than repeated rendered HTML. */
export function renderCatalogOption(option, context = 'dropdown') {
    let cache = presentations.get(option)
    if (!cache) { cache = Object.create(null); presentations.set(option, cache) }
    if (cache[context] !== undefined) return cache[context]
    const compact = context === 'trigger' && !option.richListTriggerDisplay
    const layout = compact ? 'trigger' : 'list'
    const trailing = !compact && option.descriptionPosition === 'trailing'
    const image = option.image ? `<span class="fff-select-option__${compact ? 'trigger-icon' : 'icon'} fff-select-option__${compact ? 'trigger-icon' : 'icon'}--primary" aria-hidden="true"><img src="${escape(option.image)}" alt="" class="fff-select-option__image" loading="lazy"></span>` : ''
    const label = `<span class="fff-select-option__${compact ? 'trigger-label' : 'label'}">${escape(option.label)}</span>`
    const description = !compact && option.description ? `<span class="fff-select-option__${trailing ? 'trailing-description' : 'description'}"${trailing ? ' dir="auto"' : ''}>${escape(option.description)}</span>` : ''
    cache[context] = `<span class="fff-select-option fff-select-option--${layout}${trailing ? ' fff-select-option--description-trailing' : ''}">${image}${compact ? label : `<span class="fff-select-option__content">${label}${trailing ? '' : description}</span>${trailing ? description : ''}`}</span>`
    return cache[context]
}
