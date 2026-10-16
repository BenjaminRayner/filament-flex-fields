import {applySurface} from './delivery/palette.js'
import {applyRoad} from './delivery/roads.js'
import {applyLabel,addOpenMapTilesLabels} from './delivery/labels.js'
/** Opt-in, style-generation scoped mapping. Never modifies sources, glyphs or application layers. */
export function applyDeliveryAppearance(map,enabled){
    if(!enabled)return
    const layers=map.getStyle()?.layers??[]
    for(const layer of layers){
        if(layer.id.startsWith('fff-'))continue
        applySurface(map,layer)
        applyRoad(map,layer)
        applyLabel(map,layer)
    }
    addOpenMapTilesLabels(map,layers)
}
