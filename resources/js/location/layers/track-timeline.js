/** Elapsed replay time -> route progress, including explicitly configured dwells. */
export function createTrackTimeline(duration, stops=[]) {
    const total=duration+stops.reduce((sum,stop)=>sum+stop.wait,0)
    return {duration:total,progressAt(elapsed){
        let waited=0
        for(const stop of stops){
            const arrival=stop.progress*duration+waited
            if(elapsed<arrival)break
            if(elapsed<arrival+stop.wait)return stop.progress
            waited+=stop.wait
        }
        return Math.max(0,Math.min(1,(elapsed-waited)/duration))
    }}
}
