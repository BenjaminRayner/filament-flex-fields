import {colors,interpolate} from './palette.js'
const local=[[10,0.75],[11,0.75],[12,1],[13,2],[14,3],[15,4],[16,8],[17,16],[18,24],[19,28],[20,52]]
const major=[[10,1],[12,2],[13,3],[14,4],[15,7],[16,11],[17,20],[18,32],[20,64]]
const motorway=[[6,0.8],[8,1],[10,1.5],[12,2],[13,2],[14,3],[15,8],[16,14],[17,20],[18,32],[20,60]]
/** Match semantic roles, leaving provider filters and bridge/tunnel ordering intact. */
export function applyRoad(map,layer){
    if(layer.type!=='line'||!['transportation','road'].includes(layer['source-layer']))return
    const name=layer.id.toLowerCase(),paint=(key,value)=>map.setPaintProperty(layer.id,key,value)
    if(/rail|transit/.test(name)){paint('line-color',interpolate([[13,'#C9D8DF'],[16,'#B1C1C8']]));return}
    if(/path|pedestrian/.test(name)){
        paint('line-color','rgba(255,255,255,0.5)');paint('line-width',interpolate([[16,1.5],[18,3]]));map.setLayerZoomRange(layer.id,16,24);return
    }
    if(/pier/.test(name)){paint('line-color',colors.road);return}
    const casing=/casing/.test(name),highway=/motorway/.test(name),arterial=/major|primary|secondary|trunk/.test(name)
    paint('line-color',highway?(casing?colors.motorwayCasing:colors.motorway):(casing?colors.casing:colors.road))
    paint('line-opacity',1)
    if(/minor|local|major|primary|secondary|motorway/.test(name)){
        const widths=(highway?motorway:arterial?major:local).map(([z,width])=>[z,width+(casing&&z>=16?(highway?4:2):0)])
        paint('line-width',interpolate(widths))
    }
}
