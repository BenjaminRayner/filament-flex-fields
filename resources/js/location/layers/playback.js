import { createTrackTimeline } from './track-timeline.js'
import { createTrackPath } from './track-path.js'

/** Constant ground-speed replay, one animation frame and no SDK source rebuild per frame. */
export function createTrackPlayback({config,win,onPosition,onProgress,onPlaying,onRoute,path=null}) {
    const route=path??createTrackPath(config.coordinates,config.distanceMetric)
    const timeline=createTrackTimeline(config.duration,config.stops)
    let elapsed=0,start=null,frame=null,playing=false,active=true,disposed=false,lastAnnouncement=-1,lastRoutePaint=-Infinity,lastPosition=null,lastRouteFraction=null
    const paint=(fraction,time)=>{
        const coordinate=route.coordinateAt(fraction)
        if(fraction!==lastPosition){onPosition(coordinate,{progress:fraction,bearing:route.bearingAt(fraction)});lastPosition=fraction}
        // Build/copy route geometry only when it will be sent to the worker.
        // Endpoints and loop restarts are exact even between cadence boundaries.
        if(onRoute&&fraction!==lastRouteFraction&&(time-lastRoutePaint>=50||fraction===0||fraction===1)) {
            lastRoutePaint=time;lastRouteFraction=fraction
            onRoute(route.completedAt(fraction))
        }
        const step=Math.round(fraction*100)
        if(step!==lastAnnouncement){lastAnnouncement=step;onProgress(fraction)}
    }
    const stop=()=>{if(frame!==null)win.cancelAnimationFrame(frame);frame=null;if(start!==null){elapsed+=Math.max(0,win.performance.now()-start);start=null}}
    const tick=time=>{
        frame=null;if(disposed||!playing||!active)return
        start??=time
        const age=elapsed+time-start,fraction=timeline.progressAt(age)
        paint(fraction,time)
        if(fraction===1){if(config.loop){elapsed=0;start=time}else{playing=false;elapsed=timeline.duration;start=null;onPlaying(false);return}}
        frame=win.requestAnimationFrame(tick)
    }
    const schedule=()=>{if(playing&&active&&frame===null&&!disposed)frame=win.requestAnimationFrame(tick)}
    return {
        toggle(){if(disposed)return;playing=!playing;if(playing&&elapsed>=timeline.duration){elapsed=0;lastAnnouncement=-1}if(!playing)stop();onPlaying(playing);schedule()},
        active(value){if(active===value)return;active=value;if(!active)stop();else schedule()},
        dispose(){disposed=true;playing=false;stop()},
    }
}
