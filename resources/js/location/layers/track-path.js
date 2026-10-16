import { distance, validPoint, wrapLongitude } from '../geometry/coordinates.js'

/** Immutable route index shared by recorded playback and timestamped live motion. */
export function createTrackPath(input, metric='ground') {
    if(!Array.isArray(input)||input.length<2||input.length>1000||!input.every(validPoint))throw new Error('Invalid track route.')
    const points=input.map(point=>[...point]),lengths=[0]
    const delta=(a,b)=>((b-a+540)%360)-180
    for(let i=1;i<points.length;i++)lengths.push(lengths.at(-1)+(metric==='coordinate'?Math.hypot(delta(points[i-1][0],points[i][0]),points[i][1]-points[i-1][1]):distance(points[i-1],points[i])))
    const total=lengths.at(-1)
    if(total<=0)throw new Error('Track route must have a nonzero length.')
    const locate=progress=>{
        const target=Math.max(0,Math.min(1,progress))*total
        let low=1,high=points.length-1
        while(low<high){const mid=(low+high)>>1;if(lengths[mid]<target)low=mid+1;else high=mid}
        const part=(target-lengths[low-1])/(lengths[low]-lengths[low-1]||1),a=points[low-1],b=points[low]
        return {index:low,part,coordinate:progress>=1?[...points.at(-1)]:[wrapLongitude(a[0]+delta(a[0],b[0])*part),a[1]+(b[1]-a[1])*part]}
    }
    return {
        coordinateAt:progress=>locate(progress).coordinate,
        headingCoordinatesAt(progress){return [locate(Math.max(0,progress-.002)).coordinate,locate(Math.min(1,progress+.002)).coordinate]},
        // Look across a small route window to turn continuously around corners.
        bearingAt(progress){
            const a=locate(Math.max(0,progress-.002)).coordinate,b=locate(Math.min(1,progress+.002)).coordinate
            const radians=Math.PI/180,longitude=delta(a[0],b[0])*radians
            const y=Math.sin(longitude)*Math.cos(b[1]*radians),x=Math.cos(a[1]*radians)*Math.sin(b[1]*radians)-Math.sin(a[1]*radians)*Math.cos(b[1]*radians)*Math.cos(longitude)
            return (Math.atan2(y,x)/radians+360)%360
        },
        remainingAt(progress){if(progress>=1)return [];const {index,coordinate}=locate(progress);return [coordinate,...points.slice(index)]},
        completedAt(progress){const {index,coordinate}=locate(progress);return [...points.slice(0,index),coordinate]},
        project(point){
            let closest=Infinity,progress=0
            for(let i=1;i<points.length;i++){
                const a=points[i-1],b=points[i],scale=Math.cos(point[1]*Math.PI/180),dx=delta(a[0],b[0])*scale,dy=b[1]-a[1]
                const x=delta(a[0],point[0])*scale,y=point[1]-a[1],part=Math.max(0,Math.min(1,(x*dx+y*dy)/(dx*dx+dy*dy||1))),gap=Math.hypot(x-dx*part,y-dy*part)
                if(gap<closest){closest=gap;progress=(lengths[i-1]+(lengths[i]-lengths[i-1])*part)/total}
            }
            return progress
        },
    }
}
