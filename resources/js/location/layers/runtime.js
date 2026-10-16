import { createResourceScope } from '../../runtime/lifecycle/resource-scope.js'
import { normalizeDataset,geometryPositions } from './dataset.js'
import { installLayer,layerPositions } from './renderer.js'
import { createLayerMarkers } from './markers.js'
import { createLayerFeed } from './feed.js'
import { isAssetElementActive } from '../../runtime/lifecycle/asset-activation.js'
import { validMetadataText } from '../editing/metadata.js'
import { createTrackPlayback } from './playback.js'
import { createTrackCamera } from './track-camera.js'
import { createRemainingRoute } from './remaining-route.js'
import { createTrackPath } from './track-path.js'
import { createTrackWaypoints } from './track-waypoints.js'

function normalizeCardPatch(patch) {
    if(!patch||typeof patch!=='object'||Array.isArray(patch))throw new Error('Invalid card update.')
    const result={}
    for(const[key,value]of Object.entries(patch)) {
        if(['title','description','progressLabel','progressValue'].includes(key)) {
            if(!validMetadataText(value)||new TextEncoder().encode(value).length>256)throw new Error('Invalid card text.')
        }else if(key==='progress') {if(typeof value!=='number'||!Number.isFinite(value)||value<0||value>1)throw new Error('Invalid progress.')}
        else throw new Error('Unsupported card update.')
        result[key]=value
    }
    return result
}

/** All SDK objects and sources are owned by this generation; live values never mutate form state. */
export function createExperienceRuntime(field, resources) {
    const {map,sdk}=resources,owner=field._pickerHostElement,win=owner.ownerDocument.defaultView,scope=createResourceScope()
    const experience=field.mapConfiguration.experience??{},recording=experience.playback,liveTrack=experience.liveTrack
    const track=recording??liveTrack,trackPath=track?createTrackPath(track.coordinates,recording?.distanceMetric):null
    const camera=createTrackCamera({map,configuration:experience.followCamera,scope,onChange:following=>{field.trackFollowing=following}})
    const stops=experience.stops??[]
    const definitions=[...(experience.layers??[])]
    if(stops.length&&trackPath)definitions.push({id:'fff-track-stops',kind:'tracked',markerVariant:'stop',visible:true,interactive:false,color:'#111111',cluster:false,data:{type:'FeatureCollection',features:stops.map(stop=>({type:'Feature',id:stop.id,properties:{label:stop.label},geometry:{type:'Point',coordinates:trackPath.coordinateAt(stop.progress)}}))}})
    const layers=new Map(definitions.map(input=>{const config=JSON.parse(JSON.stringify(input));return [config.id,{config,data:config.data,ids:[],markers:null}]}))
    const remainingRoute=createRemainingRoute({map,layer:experience.remainingRouteLayer,path:trackPath,win})
    const waypoints=createTrackWaypoints({layers,stops,enabled:experience.hidePassedWaypoints===true})
    const renderedMotion=(coordinate,bearing,progress)=>{remainingRoute.update(progress);waypoints.update(progress);camera.update(coordinate,bearing)}
    const livePath=liveTrack?trackPath:null
    let lastObservedAt=null,lastMotion=null
    let sequence=-1,disposed=false,active=null,started=false,initialized=false
    const select=(layerId,id)=>field.selectExperienceFeature(layerId,id)
    let feed=null,playback=null
    const activity=()=>{
        if(disposed)return
        const next=!owner.ownerDocument.hidden&&isAssetElementActive(owner)
        if(next===active)return
        active=next;camera.active(active);feed?.active(active);playback?.active(active)
        if(!active)for(const record of layers.values())record.markers?.pause()
    }
    const observer=new win.MutationObserver(activity)
    for(let node=owner;node&&node!==owner.ownerDocument.body;node=node.parentElement)observer.observe(node,{attributes:true,attributeFilter:['class','style','hidden','aria-hidden','data-fff-asset-active','open']})
    scope.defer(()=>observer.disconnect());scope.listen(owner.ownerDocument,'visibilitychange',activity)
    const controller={
        install(){
            // The outer style-load transaction already proved the style is usable.
            // Adding its first source makes isStyleLoaded() false until worker completion;
            // testing it again here would permanently skip all experience layers.
            if(disposed)return
            remainingRoute.reset()
            for(const record of layers.values()) {
                record.config.data=record.data
                record.ids=installLayer(map,record.config,field.mapConfiguration.provider)
                if(['pills','tracked'].includes(record.config.kind)&&!record.markers)record.markers=createLayerMarkers({map,sdk,config:record.config,onSelect:select,onHover:(feature,coordinate)=>field.showFeatureTooltip(feature,coordinate),onLeave:()=>field.closeFeatureTooltip(),win,motionTarget:recording?.layer===record.config.id?recording.feature:liveTrack?.layer===record.config.id?liveTrack.feature:null,route:track?.layer===record.config.id?trackPath:null,onMotion:renderedMotion})
                record.markers?.update(record.data,{motion:liveTrack?.layer===record.config.id?lastMotion:null})
            }
            waypoints.refresh()
            field.experienceRevision++
            if(!initialized){initialized=true;const selection=field.mapConfiguration.experience?.initialSelection;if(selection)select(selection.layer,selection.feature)}
            if(recording?.autoPlay&&!started){started=true;if(!win.matchMedia?.('(prefers-reduced-motion: reduce)').matches)playback?.toggle()}
        },
        update(message){
            if(disposed)return false
            if(!message||!Number.isSafeInteger(message.sequence)||message.sequence<0||!message.layers||typeof message.layers!=='object'||Array.isArray(message.layers))throw new Error('Invalid live map envelope.')
            if(message.sequence<=sequence)return false
            if(message.duration!==undefined&&(!Number.isFinite(message.duration)||message.duration<0||message.duration>10000))throw new Error('Invalid interpolation duration.')
            if(new TextEncoder().encode(JSON.stringify(message)).length>4194304)throw new Error('Live map envelope exceeds its byte budget.')
            const prepared=[]
            for(const[id,value]of Object.entries(message.layers)){
                const record=layers.get(id);if(!record||(id==='fff-track-stops'||id===experience.remainingRouteLayer))throw new Error('Unknown or immutable map layer.')
                prepared.push([record,normalizeDataset(value,record.config)])
            }
            const staged=new Map(prepared.map(([record,data])=>[record.config.id,data]))
            let motion=null
            if(message.motion!==undefined){
                const fix=message.motion
                if(!liveTrack||!fix||typeof fix.progress!=='number'||!Number.isFinite(fix.progress)||fix.progress<0||fix.progress>1||!Number.isSafeInteger(fix.observedAt)||fix.observedAt<0)throw new Error('Invalid timestamped route fix.')
                if(lastObservedAt!==null&&fix.observedAt<=lastObservedAt)return false
                const record=layers.get(liveTrack.layer),data=staged.get(liveTrack.layer)??normalizeDataset(record.data,record.config)
                const feature=data.features.find(feature=>String(feature.id)===String(liveTrack.feature))
                if(!feature)throw new Error('Live track target cannot be removed.')
                feature.geometry.coordinates=livePath.coordinateAt(fix.progress)
                if(!staged.has(liveTrack.layer))prepared.push([record,data])
                staged.set(liveTrack.layer,data)
                motion={...fix,recovery:lastObservedAt!==null&&fix.observedAt-lastObservedAt>liveTrack.staleAfter}
            }else if(liveTrack&&staged.has(liveTrack.layer))throw new Error('Tracked layer updates require timestamped route progress.')
            const totalBytes=[...layers.values()].reduce((sum,record)=>sum+new TextEncoder().encode(JSON.stringify(staged.get(record.config.id)??record.data)).length,0)
            if(totalBytes>4194304)throw new Error('Combined live map layers exceed their byte budget.')
            const card=message.card===undefined?null:normalizeCardPatch(message.card)
            // Validate the entire envelope before applying any part of it.
            for(const[record,data]of prepared){record.data=data;record.config.data=data;map.getSource(`fff-data-${record.config.id}`)?.setData(data);const followsTrack=motion&&record.config.id===liveTrack.layer;record.markers?.update(data,{duration:active?(followsTrack&&motion.recovery?liveTrack.recoveryDuration:(message.duration??1000)):0,motion:followsTrack?motion:null})}
            waypoints.refresh()
            sequence=message.sequence
            if(motion){lastObservedAt=motion.observedAt;lastMotion=motion}
            if(card)field.experienceCard={...field.experienceCard,...card}
            field.experienceRevision++
            field.mapSourceError=null
            return true
        },
        feature(layer,id){return layers.get(layer)?.data.features.find(feature=>String(feature.id)===String(id))},
        listings(layer){return (layers.get(layer)?.data.features??[]).slice(0,200)},
        positions(){return layerPositions([...layers.values()].map(record=>({...record.config,data:record.data})))},
        highlight(layer,id){for(const[key,record]of layers)record.markers?.highlight(key===layer?id:null)},
        selected(layer,id){for(const[key,record]of layers)record.markers?.select(key===layer?id:null)},
        async hit(point){
            const ids=[...layers.values()].filter(record=>record.config.interactive&&record.config.visible).flatMap(record=>record.ids).filter(id=>map.getLayer(id))
            const hit=ids.length?map.queryRenderedFeatures(point,{layers:ids})[0]:null
            if(!hit)return false
            const record=[...layers.values()].find(record=>`fff-data-${record.config.id}`===hit.source)
            if(!record)return false
            if(hit.properties?.cluster){
                const source=map.getSource(hit.source)
                try{
                    const zoom=field.mapConfiguration.provider==='mapbox'?await new Promise((resolve,reject)=>source.getClusterExpansionZoom(hit.properties.cluster_id,(error,zoom)=>error?reject(error):resolve(zoom))):await source.getClusterExpansionZoom(hit.properties.cluster_id)
                    if(!disposed)map.easeTo({center:hit.geometry.coordinates,zoom,duration:field.motionDuration()})
                }catch{if(!disposed)field.mapSourceError=field.labels.loadFailed}
            }else select(record.config.id,hit.id)
            return true
        },
        visible(id,visible){const record=layers.get(id);if(!record)return;record.config.visible=visible;for(const layer of record.ids)if(map.getLayer(layer))map.setLayoutProperty(layer,'visibility',visible?'visible':'none');record.markers?.update(record.data);field.experienceRevision++},
        playback(){playback?.toggle()},
        recenter(){camera.resume()},
        detach(){camera.pause()},
        retry(){feed?.retry()},
        dispose(){if(disposed)return;disposed=true;scope.dispose();camera.dispose();feed?.dispose();playback?.dispose();waypoints.dispose();for(const record of layers.values())record.markers?.dispose();layers.clear()},
    }
    scope.listen(owner,'fff-map-track-recenter',event=>{if(event.target===owner)camera.resume()})
    scope.listen(owner,'fff-map-layer-update',event=>{
        // A composed child cannot update a parent map's datasets.
        if(event.target!==owner)return
        try{controller.update(event.detail)}catch{field.mapSourceError=field.labels.loadFailed}
    })
    const endpoint=field.mapConfiguration.experience?.feed
    if(endpoint)feed=createLayerFeed({...endpoint,win,onData:message=>controller.update(message),onStatus:status=>{field.mapFeedStatus=status;if(status==='retrying')field.mapSourceError=field.labels.loadFailed}})
    if(recording)playback=createTrackPlayback({config:{...recording,stops},path:trackPath,win,onPlaying:value=>{field.trackPlaying=value},onProgress:progress=>{field.experienceCard={...field.experienceCard,progress,...(recording.arrivalMinutes?{progressValue:`${Math.max(1,Math.ceil((1-progress)*recording.arrivalMinutes))} min`}:{})}},
        onPosition:(coordinate,motion)=>{
            const record=layers.get(recording.layer),feature=controller.feature(recording.layer,recording.feature)
            if(feature){feature.geometry.coordinates=coordinate;record.markers?.move(recording.feature,coordinate,motion)}
        },
        onRoute:recording.completedRouteLayer?path=>{
            const route=layers.get(recording.completedRouteLayer)
            if(route){route.data={type:'FeatureCollection',features:[{type:'Feature',id:recording.completedRouteLayer,properties:{},geometry:{type:'LineString',coordinates:path}}]};map.getSource(`fff-data-${route.config.id}`)?.setData(route.data)}
        }:null})
    activity()
    return controller
}

export function firstCoordinate(feature){return geometryPositions(feature.geometry)[0]}
