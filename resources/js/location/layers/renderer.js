import { geometryPositions } from './dataset.js'

const font = provider => provider==='maplibre'?['Noto Sans Regular']:['DIN Offc Pro Medium','Arial Unicode MS Regular']
const cssColor = color => color.length === 9 ? `rgba(${parseInt(color.slice(1,3),16)},${parseInt(color.slice(3,5),16)},${parseInt(color.slice(5,7),16)},${parseInt(color.slice(7,9),16)/255})` : color
const filter = type => ['==',['geometry-type'],type]

/** Native GPU layers are reconstructed after style changes; each dataset owns exactly one source. */
export function installLayer(map, config, provider) {
    const source=`fff-data-${config.id}`, definitions=[]
    if (!map.getSource(source)) map.addSource(source,{type:'geojson',data:config.data,...(config.navigationRoute?{tolerance:0}:{}),...(config.cluster?{cluster:true,clusterRadius:config.clusterRadius,clusterMaxZoom:config.clusterMaxZoom}:{})})
    const color=['coalesce',['get','color'],config.color], visibility=config.visible?'visible':'none'
    const add=(suffix,type,paint,layout={},condition=null) => definitions.push({id:`${source}-${suffix}`,source,type,paint,layout:{...layout,visibility},...(condition?{filter:condition}:{})})
    if(config.navigationRoute){
        add('line','line',{'line-color':color,'line-width':config.width,'line-opacity':config.opacity},{'line-cap':'butt','line-join':'miter','line-miter-limit':4},filter('LineString'))
    } else if (config.kind==='heatmap') {
        const stops=config.colorStops ? config.colorStops.flatMap(([density,color])=>[density,cssColor(color)]) : [0,'rgba(0,0,0,0)',...config.colors.flatMap((color,i)=>[(i+1)/config.colors.length,color])]
        const radius=config.radiusStops ? ['interpolate',['linear'],['zoom'],...config.radiusStops.flat()] : config.heatmapRadius
        add('heat','heatmap',{'heatmap-weight':['interpolate',['linear'],['to-number',['get',config.weightProperty],0],0,0,config.weightMax,1],
            'heatmap-intensity':config.heatmapIntensity,'heatmap-radius':radius,'heatmap-opacity':config.opacity,
            'heatmap-color':['interpolate',['linear'],['heatmap-density'],...stops]})
    } else if (!['pills','tracked'].includes(config.kind)) {
        add('fill','fill',{'fill-color':color,'fill-opacity':config.opacity*.25},{},filter('Polygon'))
        add('outline','line',{'line-color':color,'line-width':config.width,'line-opacity':config.opacity},{},filter('Polygon'))
        add('line','line',{'line-color':color,'line-width':config.width,'line-opacity':config.opacity,...(config.dash?{'line-dasharray':config.dash}:{})},{'line-cap':'round','line-join':'round'},filter('LineString'))
        const points=['all',filter('Point'),['!', ['has','point_count']]]
        add('points','circle',{'circle-color':color,'circle-radius':config.radius,'circle-opacity':config.opacity,'circle-stroke-width':2,'circle-stroke-color':'#ffffff'},{},points)
        if (config.labelProperty) add('labels','symbol',{'text-color':'#18181b','text-halo-color':'#ffffff','text-halo-width':2},
            {'text-field':['coalesce',['to-string',['get',config.labelProperty]],''],'text-size':12,'text-offset':[0,-1.6],'text-font':font(provider)},points)
        if (config.cluster) {
            add('clusters','circle',{'circle-color':config.color,'circle-radius':['step',['get','point_count'],18,20,24,100,30],'circle-stroke-width':2,'circle-stroke-color':'#ffffff'},{},['has','point_count'])
            add('count','symbol',{'text-color':'#ffffff'},{'text-field':['get','point_count_abbreviated'],'text-size':12,'text-font':font(provider)},['has','point_count'])
        }
    }
    for (const definition of definitions) if (!map.getLayer(definition.id)) map.addLayer(definition, definition.type==='heatmap'?map.getStyle().layers?.find(layer=>layer.type==='symbol')?.id:undefined)
    return definitions.map(layer=>layer.id)
}

export const layerPositions = layers => layers.flatMap(layer=>layer.visible?layer.data.features.flatMap(feature=>geometryPositions(feature.geometry)):[])
