import {colors,interpolate} from './palette.js'
const fadeOut=end=>interpolate([[end-0.2,1],[end,0]])
export function applyLabel(map,layer){
    if(layer.type!=='symbol'||!layer.layout?.['text-field'])return
    const id=layer.id,name=id.toLowerCase(),source=layer['source-layer']
    const paint=(key,value)=>map.setPaintProperty(id,key,value),layout=(key,value)=>map.setLayoutProperty(id,key,value)
    paint('text-color',colors.label);paint('text-halo-color','rgba(255,255,255,0.8)');paint('text-halo-width',1.2)
    if(source==='transportation_name'||source==='road_label'){
        if(/shield/.test(name))return
        const minor=/minor|local|path/.test(name)
        map.setLayerZoomRange(id,minor?14:11,24)
        paint('text-color',interpolate([[14,'#5E5E5E'],[15,'#444444']]))
        layout('text-transform','uppercase');layout('text-letter-spacing',0.1)
        layout('text-size',interpolate([[13,10],[16,13],[18,15]]));layout('symbol-spacing',250)
    }else if(source==='place'){
        const neighborhood=/other|neigh|suburb/.test(name),country=/country/.test(name),state=/state/.test(name)
        if(neighborhood){
            map.setLayerZoomRange(id,10,14.5);paint('text-opacity',interpolate([[14.39,1],[14.4,0]]))
            paint('text-color',interpolate([[10,'#6A87C5'],[14,'#92AADD']]))
            layout('text-transform','uppercase');layout('text-letter-spacing',0.2);layout('text-size',interpolate([[12,13],[13,15]]))
        }else if(country||state){map.setLayerZoomRange(id,layer.minzoom??1,8);paint('text-opacity',fadeOut(8));paint('text-color',country?'#000000':colors.locality)}
        else {map.setLayerZoomRange(id,layer.minzoom??1,13.5);paint('text-opacity',fadeOut(13.5));paint('text-color',colors.locality)}
    }else if(source==='housenumber'){map.setLayerZoomRange(id,17.5,24);paint('text-color','#6B737D')}
    else if(source==='poi'){
        map.setLayerZoomRange(id,13,24);layout('text-size',interpolate([[16,13],[17,15]]));layout('text-padding',2)
    }
}
/** Positron omits these data layers. Add them only to a recognized OpenMapTiles source. */
export function addOpenMapTilesLabels(map,layers){
    const provider=layers.find(layer=>layer.source==='openmaptiles'&&layer['source-layer']==='transportation')
    if(!provider)return
    const font=layers.find(layer=>layer.id==='label_city')?.layout?.['text-font']??['Noto Sans Regular']
    if(!layers.some(layer=>layer['source-layer']==='housenumber'))map.addLayer({
        id:'fff-delivery-house-numbers',type:'symbol',source:provider.source,'source-layer':'housenumber',minzoom:17.5,
        layout:{'text-field':['get','housenumber'],'text-font':font,'text-size':11,'text-padding':2},
        paint:{'text-color':'#6B737D','text-halo-color':'rgba(255,255,255,0.5)','text-halo-width':1},
    })
    if(!layers.some(layer=>layer['source-layer']==='poi'))map.addLayer({
        id:'fff-delivery-poi',type:'symbol',source:provider.source,'source-layer':'poi',minzoom:13,
        filter:['all',['has','name'],['<=',['coalesce',['get','rank'],99],['step',['zoom'],2,14,5,15,10,17,1000]],['any',['!',['in',['get','class'],['literal',['bus','railway','entrance']]]],['==',['get','agg_stop'],1],['==',['get','subclass'],'station']]],
        layout:{'text-field':['get','name'],'text-font':font,'text-size':interpolate([[16,13],[17,15]]),'text-padding':interpolate([[13,18],[14,18],[15,16],[16,8],[17,5]]),'text-max-width':8,'text-variable-anchor':['left','right'],'text-radial-offset':0.7},
        paint:{'text-color':['match',['get','class'],['park','golf'],colors.greenLabel,colors.label],'text-halo-color':'rgba(255,255,255,0.8)','text-halo-width':1.2},
    })
}
