/** Visibility follows painted progress, including dwell, recovery and replay resets. */
export function createTrackWaypoints({layers,stops,enabled=false}) {
    let progress=0,waypoints=[]
    const apply=waypoint=>{
        const passed=progress>waypoint.progress+1e-9
        if(passed===waypoint.passed)return
        waypoint.markers.passed(waypoint.id,passed)
        waypoint.passed=passed
    }
    return {
        refresh(){
            if(!enabled)return
            waypoints=[]
            for(const record of layers.values()) {
                if(!record.markers)continue
                if(record.config.kind==='tracked'&&record.config.markerVariant==='origin') {
                    for(const feature of record.data.features)waypoints.push({markers:record.markers,id:feature.id,progress:0})
                }
                if(record.config.id==='fff-track-stops') {
                    for(const stop of stops)waypoints.push({markers:record.markers,id:stop.id,progress:stop.progress})
                }
            }
            for(const waypoint of waypoints)apply(waypoint)
        },
        update(value){
            if(!enabled||!Number.isFinite(value))return
            progress=value
            for(const waypoint of waypoints)apply(waypoint)
        },
        dispose(){waypoints=[]},
    }
}
