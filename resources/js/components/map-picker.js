import { mergeAlpineComponentData } from '../support/merge-alpine-component-data.js'
import { createPickerLifetimeMixin } from '../overlay/picker-lifetime.js'
import { createLocationLifecycle } from '../location/runtime/lifecycle.js'
import { createLocationSelection } from '../location/editing/selection.js'
import { createGeometryHistory } from '../location/editing/history.js'
import { createFeatureProperties } from '../location/editing/properties.js'
import { createZoneDrawing } from '../location/editing/drawing.js'
import { createZoneVertices } from '../location/editing/vertices.js'
import { createMapLayers } from '../location/presentation/layers.js'
import { createMapControls } from '../location/presentation/controls.js'
import { createLocationCards } from '../location/presentation/cards.js'
import { createLocationPresentation } from '../location/presentation/marker.js'
import { createMapExperiences } from '../location/layers/experience.js'

/** Public Alpine entrypoint composes capabilities; SDK objects belong to the runtime owner. */
export default function mapPickerFormComponent(configuration) {
    return mergeAlpineComponentData({selectionError:null,geocodeError:null,mapReady:false,mapLoading:true,mapError:null,summaryLabel:null,selectedLabel:'',...configuration},
        createPickerLifetimeMixin(),
        createLocationLifecycle(), createMapExperiences(), createMapControls(), createLocationCards(), createLocationPresentation(), createLocationSelection(), createMapLayers(), createGeometryHistory(), createFeatureProperties(), createZoneDrawing(), createZoneVertices())
}
