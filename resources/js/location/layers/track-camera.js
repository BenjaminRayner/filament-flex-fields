/** Follow the rendered position, using its existing RAF rather than restarting SDK easing. */
export function createTrackCamera({map, configuration, scope,onChange=()=>{}}) {
    let active=true, paused=false, disposed=false, last=null, configured=false, writing=false
    const gestures=new Set()
    const pause=()=>{if(configuration?.pauseOnInteraction&&!paused){paused=true;onChange(false)}}
    onChange(Boolean(configuration))
    const update=(coordinate,bearing)=>{
        if(!configuration||disposed)return
        last={coordinate:[...coordinate],bearing}
        if(!active||paused||disposed||gestures.size)return
        const initial=!configured
        configured=true
        writing=true
        try { map.jumpTo({
            center:coordinate,
            ...(initial&&configuration.zoom!==null?{zoom:configuration.zoom}:{}),
            ...(initial&&configuration.pitch!==null?{pitch:configuration.pitch}:{}),
            ...(configuration.rotate&&Number.isFinite(bearing)?{bearing}:{}),
        }) } finally { writing=false }
    }
    // SDK zoom/rotation gestures must finish rather than being cancelled by jumpTo each frame.
    // Continue recording the latest rendered fix and recenter when the gesture ends.
    if(configuration){
        for(const type of ['zoom','rotate','pitch']){
            const start=()=>{if(!writing)gestures.add(type)}
            const end=()=>{if(writing)return;gestures.delete(type);if(last)update(last.coordinate,last.bearing)}
            map.on(`${type}start`,start);map.on(`${type}end`,end)
            scope.defer(()=>{map.off(`${type}start`,start);map.off(`${type}end`,end)})
        }
    }
    if(configuration?.pauseOnInteraction){
        // Stop following at interaction intent, before jumpTo can cancel SDK drag recognition.
        const canvas=map.getCanvas?.()
        if(canvas&&scope.listen){
            scope.listen(canvas,'pointerdown',pause,{capture:true,passive:true})
            scope.listen(canvas,'wheel',pause,{capture:true,passive:true})
            scope.listen(canvas,'keydown',event=>{if(['ArrowUp','ArrowDown','ArrowLeft','ArrowRight','+','-','='].includes(event.key))pause()},{capture:true})
        }
        const interrupt=event=>{if(!writing&&event.originalEvent)pause()}
        for(const type of ['dragstart','zoomstart','rotatestart','pitchstart']){
            map.on(type,interrupt)
            scope.defer(()=>map.off(type,interrupt))
        }
    }
    return {
        update,
        active(value){active=value},
        pause,
        resume(){paused=false;onChange(Boolean(configuration));if(last)update(last.coordinate,last.bearing)},
        dispose(){disposed=true;last=null},
    }
}
