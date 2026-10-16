/** Display parameters observed in the reference; source tiles, sprites and fonts stay provider-owned. */
export const interpolate = (stops) => ['interpolate', ['linear'], ['zoom'], ...stops.flat()]
export const colors = {
    background: interpolate([[13,'#EDF0F6'],[17,'#E9ECEF'],[18,'#EBEEF1']]),
    park: interpolate([[5,'#DAEADB'],[9,'#BDE5C1'],[14,'#A7DFB6']]),
    water: interpolate([[0,'#ACD5FD'],[12,'#B3DAFF']]),
    casing: interpolate([[15.99,'#FFFFFF'],[16,'#D1D7DB'],[17,'#D5DBDF']]),
    road: '#FFFFFF', motorway: '#8599D1', motorwayCasing: '#6F84BB',
    building: '#D8DAE2', hospital: '#FBD6D4', airport: '#CBD4E1',
    label: '#556268', locality: '#576273', greenLabel: '#2A885D',
}
export function applySurface(map, layer) {
    const source=layer['source-layer'], id=layer.id
    const paint=(key,value)=>map.setPaintProperty(id,key,value)
    if(layer.type==='background'){paint('background-color',colors.background);return}
    if(layer.type==='fill'){
        if(source==='water'){paint('fill-color',colors.water);paint('fill-outline-color','#9CCEFF')}
        else if(source==='building'){
            paint('fill-color',colors.building);paint('fill-opacity',0.6);paint('fill-outline-color','rgba(202,205,218,0.8)')
            map.setLayerZoomRange(id,16,24)
        }else if(source==='park')paint('fill-color',colors.park)
        else if(source==='aeroway')paint('fill-color',colors.airport)
        else if(source==='landcover'||source==='landuse'){
            paint('fill-color',['match',['get','class'],['park','grass','wood','forest','garden','recreation_ground'],colors.park,'hospital',colors.hospital,['school','university'],'#E0E5EC',colors.background])
        }
    }else if(layer.type==='line'){
        if(source==='waterway')paint('line-color',colors.water)
        else if(source==='boundary')paint('line-color','#BCC8DE')
        else if(source==='aeroway')paint('line-color','#B1BBCA')
    }
}
