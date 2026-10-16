import { mergeAlpineComponentData } from '../support/merge-alpine-component-data.js'
import { createCountryInteraction } from '../location/country/interaction.js'
import { createCountrySelection } from '../location/country/selection.js'

/** Map commands compose around the actual Select renderer; no parallel menu/search runtime. */
export default function countryMapFormComponent({ state, allowed, multiple, readOnly, camera = {} }) {
    let disposed = false
    return mergeAlpineComponentData(
        { state },
        createCountrySelection({ allowed, multiple, readOnly, isDisposed: () => disposed }),
        createCountryInteraction({ minZoom: 1, maxZoom: 6, animationDuration: 180, scrollZoom: false, tooltips: true, ...camera }, { onDispose: () => { disposed = true } }),
    )
}
