/** Project both route tangents through the SDK so pitch and bearing preserve road alignment. */
export function projectedTrackHeading(map,route,progress) {
    if(typeof map.project!=='function')return route.bearingAt(progress)
    const points=route.headingCoordinatesAt(progress)
    const anchor=map.getCenter?.().lng??points[0][0]
    const project=point=>map.project([anchor+((point[0]-anchor+540)%360)-180,point[1]])
    const a=project(points[0]),b=project(points[1]),dx=b.x-a.x,dy=b.y-a.y
    if(!Number.isFinite(dx)||!Number.isFinite(dy)||Math.hypot(dx,dy)<1e-7)return route.bearingAt(progress)
    return (Math.atan2(dx,-dy)*180/Math.PI+360)%360
}
