/** Dedicated source window follows street geometry; coalesced writes never add another RAF. */
export function createRemainingRoute({map,layer,path,win}) {
    let last=null,lastWrite=-Infinity
    return {
        update(progress){
            if(!layer||!path||!Number.isFinite(progress)||progress===last)return
            const source=map.getSource(`fff-data-${layer}`)
            if(!source)return
            const time=win.performance.now()
            // At high zoom the marker spans less ground; tighten the source cadence.
            const interval=(map.getZoom?.()??0)>=17?16:50
            if(last!==null&&progress>last&&progress<1&&time-lastWrite<interval)return
            const coordinates=path.remainingAt(progress)
            source.setData({type:'FeatureCollection',features:coordinates.length<2?[]:[{type:'Feature',id:layer,properties:{},geometry:{type:'LineString',coordinates}}]})
            last=progress;lastWrite=time
        },
        reset(){last=null;lastWrite=-Infinity},
    }
}
