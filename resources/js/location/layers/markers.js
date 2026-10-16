import { projectedTrackHeading } from './track-heading.js'
import { markerPresentation,isVehicleMarker } from './marker-presentation.js'

/** A bounded DOM presentation for price pills / moving assets. GPU layers handle large point datasets. */
export function createLayerMarkers({map,sdk,config,onSelect,onHover,onLeave,win,motionTarget=null,route=null,onMotion=null}) {
    const variant=config.markerVariant??'dot'
    const vehicle=isVehicleMarker(variant)
    const orient=(record,progress)=>{if(vehicle&&route&&progress!==null){const rotation=projectedTrackHeading(map,route,progress);record.bearing=route.bearingAt(progress);if(rotation!==record.rotation){record.marker.setRotation?.(rotation);record.rotation=rotation}}}
    const notify=record=>{if(onMotion&&idIsTarget(record)) {const point=record.marker.getLngLat();onMotion?.([point.lng,point.lat],record.bearing??(route&&record.progress!==null?route.bearingAt(record.progress):null),record.progress)}}
    const idIsTarget=record=>String(record.feature.id)===String(motionTarget)
    const markers=new Map()
    const visibility=record=>{
        const visible=config.visible&&!record.passed
        record.element.style.visibility=visible?'visible':'hidden'
        record.element.setAttribute('aria-hidden',String(!visible))
        if(!visible&&record.hovered){record.hovered=false;onLeave?.()}
    }
    const reorient=()=>{for(const record of markers.values())orient(record,record.progress)}
    if(vehicle&&route)map.on?.('move',reorient)
    let frame=null,disposed=false
    const reduced=()=>win.matchMedia?.('(prefers-reduced-motion: reduce)').matches
    const stop=()=>{ if(frame!==null)win.cancelAnimationFrame(frame);frame=null }
    const animate=time=>{
        frame=null
        if(disposed)return
        let pending=false
        for(const record of markers.values()) {
            if(!record.movement)continue
            const {from,to,start,duration,path,fromProgress,toProgress,recovery}=record.movement,progress=Math.min(1,Math.max(0,(time-start)/duration))
            // Shortest longitude arc avoids a world-spanning jump near the antimeridian.
            const delta=((to[0]-from[0]+540)%360)-180
            if(path){const phase=recovery?progress*progress*(3-2*progress):progress;record.progress=fromProgress+(toProgress-fromProgress)*phase;record.marker.setLngLat(path.coordinateAt(record.progress));orient(record,record.progress)}
            else record.marker.setLngLat([from[0]+delta*progress,from[1]+(to[1]-from[1])*progress])
            notify(record)
            if(progress===1)record.movement=null;else pending=true
        }
        if(pending)frame=win.requestAnimationFrame(animate)
    }
    return {
        update(data,{duration=0,motion=null}={}) {
            if(disposed)return
            const identities=new Set()
            for(const feature of data.features) {
                const id=String(feature.id);identities.add(id)
                let record=markers.get(id)
                if(!record) {
                    const element=win.document.createElement(config.interactive?'button':'span')
                    if(config.interactive)element.type='button'
                    element.className=`fff-location__data-marker is-${config.kind}`
                    markerPresentation(win.document,element,variant,config.markerImage)
                    const label=win.document.createElement('span');label.className='fff-location__data-marker-label';element.append(label)
                    const enter=()=>{if(record.feature.properties.tooltip){record.hovered=true;onHover?.(record.feature,record.feature.geometry.coordinates)}},leave=()=>{record.hovered=false;onLeave?.()}
                    const listener=event=>{event.stopPropagation();onSelect(config.id,id)}
                    if(config.interactive){element.addEventListener('click',listener);element.addEventListener('pointerenter',enter);element.addEventListener('pointerleave',leave)}
                    const marker=new sdk.Marker({element,...(vehicle?{rotationAlignment:typeof map.project==='function'?'viewport':'map',pitchAlignment:'viewport'}:{})})
                    // Preserve fractional screen positions instead of stair-stepping between pixels.
                    if(config.kind==='tracked')marker.setSubpixelPositioning?.(true)
                    element.classList.toggle('is-motion-target',id===String(motionTarget))
                    marker.setLngLat(feature.geometry.coordinates).addTo(map)
                    record={marker,element,label,listener,enter,leave,movement:null,progress:route&&id===String(motionTarget)?route.project(feature.geometry.coordinates):null,feature};markers.set(id,record)
                }
                record.feature=feature
                if(motion&&id===String(motionTarget))record.targetProgress=motion.progress
                record.element.style.setProperty('--fff-marker-color',/^#[a-fA-F0-9]{6}$/.test(feature.properties.color)?feature.properties.color:config.color)
                visibility(record)
                const text=String(feature.properties[config.labelProperty??'label']??feature.id)
                record.label.textContent=text
                record.element.setAttribute('aria-label',String(feature.properties.label??text))
                const target=feature.geometry.coordinates
                if(duration>0&&!reduced()&&config.kind==='tracked') {
                    const current=record.marker.getLngLat()
                    if(current.lng!==target[0]||current.lat!==target[1])record.element.classList.toggle('is-motion-target',true)
                    const followsRoute=route&&motion&&id===String(motionTarget)
                    record.movement={from:[current.lng,current.lat],to:target,start:win.performance.now(),duration:Math.min(duration,10000),path:followsRoute?route:null,fromProgress:record.progress,toProgress:motion?.progress,recovery:motion?.recovery}
                    if(frame===null)frame=win.requestAnimationFrame(animate)
                }else{record.movement=null;record.marker.setLngLat(target);if(route&&id===String(motionTarget))record.progress=record.targetProgress??route.project(target);orient(record,record.progress);notify(record)}
            }
            for(const[id,record]of markers)if(!identities.has(id)){record.element.removeEventListener('click',record.listener);record.element.removeEventListener('pointerenter',record.enter);record.element.removeEventListener('pointerleave',record.leave);record.marker.remove();markers.delete(id)}
        },
        select(id){for(const[key,record]of markers){const selected=key===String(id);record.element.classList.toggle('is-selected',selected);if(config.interactive)record.element.setAttribute('aria-pressed',String(selected))}},
        highlight(id){for(const[key,record]of markers)record.element.classList.toggle('is-hovered',key===String(id))},
        passed(id,passed){const record=markers.get(String(id));if(record&&record.passed!==passed){record.passed=passed;visibility(record)}},
        move(id,coordinate,motion=null){const record=markers.get(String(id));if(record){record.movement=null;record.marker.setLngLat(coordinate);if(motion){record.progress=motion.progress;orient(record,record.progress)}notify(record)}},
        pause(){stop();for(const record of markers.values())if(record.movement){record.marker.setLngLat(record.movement.to);if(record.movement.path)record.progress=record.movement.toProgress;orient(record,record.progress);notify(record);record.movement=null}},
        dispose(){disposed=true;stop();if(vehicle&&route)map.off?.('move',reorient);for(const record of markers.values()){record.element.removeEventListener('click',record.listener);record.element.removeEventListener('pointerenter',record.enter);record.element.removeEventListener('pointerleave',record.leave);record.marker.remove()}markers.clear()},
    }
}
