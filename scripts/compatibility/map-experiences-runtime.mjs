import assert from 'node:assert/strict'
import { createExperienceRuntime } from '../../resources/js/location/layers/runtime.js'
import { createTrackPlayback } from '../../resources/js/location/layers/playback.js'
import { installLayer } from '../../resources/js/location/layers/renderer.js'
import { createLayerMarkers } from '../../resources/js/location/layers/markers.js'
import { createTrackPath } from '../../resources/js/location/layers/track-path.js'
import { createLayerFeed } from '../../resources/js/location/layers/feed.js'

let now = 0, nextFrame = 0
const frames = new Map(), observers = []
class Element extends EventTarget {
    style = { setProperty() {} }
    classes = new Set()
    classList = { contains: value => this.classes.has(value), toggle: (value, enabled) => enabled ? this.classes.add(value) : this.classes.delete(value) }
    children = []
    append(child) { this.children.push(child) }
    setAttribute() {}
}
const document = new EventTarget()
const win = {
    document, performance: { now: () => now }, matchMedia: () => ({ matches: false }),
    requestAnimationFrame(callback) { const id = ++nextFrame; frames.set(id, callback); return id },
    cancelAnimationFrame(id) { frames.delete(id) },
    MutationObserver: class { constructor(callback) { this.callback = callback; observers.push(this) } observe() {} disconnect() { this.disconnected = true } },
}
document.defaultView = win; document.body = new Element(); document.createElement = () => new Element()
const owner = new Element(); owner.ownerDocument = document; owner.parentElement = document.body
const sources = new Map(), layers = new Map(); let removed = 0, writes = 0
const map = {
    // Reproduces SDK state immediately after another source was added by the outer installer.
    isStyleLoaded: () => false,
    getSource: id => sources.get(id), getLayer: id => layers.get(id), getStyle: () => ({ layers: [{ id: 'labels', type: 'symbol' }] }),
    addSource(id, config) { sources.set(id, { data: config.data, setData(data) { this.data = data; writes++ } }) },
    addLayer(config, before) { layers.set(config.id, { ...config, before }) },
}
const sdkMarkers = []
const sdk = { Marker: class {
    constructor({ element }) { this.element = element; sdkMarkers.push(this) }
    setSubpixelPositioning(value) { this.subpixel = value; return this }
    setLngLat(coordinate) { this.coordinate = coordinate; return this }
    getLngLat() { return { lng: this.coordinate[0], lat: this.coordinate[1] } }
    addTo() { return this }
    remove() { removed++ }
} }
const collection = coordinates => ({ type: 'FeatureCollection', features: [{ type: 'Feature', id: 'courier', geometry: { type: 'Point', coordinates }, properties: { label: 'Courier' } }] })
const definition = { id: 'courier', kind: 'tracked', data: collection([0, 0]), color: '#4285f4', visible: true, interactive: true }
const field = { _pickerHostElement: owner, experienceRevision: 0, experienceCard: {}, labels: { loadFailed: 'failed' },
    selectExperienceFeature(layer, feature) { this.selection = [layer, feature] },
    mapConfiguration: { provider: 'maplibre', experience: { layers: [definition], initialSelection: { layer: 'courier', feature: 'courier' } } },
}
const runtime = createExperienceRuntime(field, { map, sdk })
runtime.install()
assert(sources.has('fff-data-courier'), 'An intermediate false isStyleLoaded must not skip experience sources')
assert.deepEqual(field.selection, ['courier', 'courier'])
assert.equal(field.experienceRevision, 1, 'Listing presentation must update after its sources become available')
runtime.install(); assert.equal(sources.size, 1, 'Reinstall must preserve one source per layer')
assert(runtime.update({ sequence: 2, layers: { courier: collection([1, 1]) } }))
assert.equal(runtime.update({ sequence: 1, layers: { courier: collection([2, 2]) } }), false)
const previous = JSON.stringify(runtime.feature('courier', 'courier'))
assert.throws(() => runtime.update({ sequence: 3, layers: { courier: collection([2, 2]), missing: collection([3, 3]) } }))
assert.equal(JSON.stringify(runtime.feature('courier', 'courier')), previous, 'Failed envelope must be atomic')
runtime.dispose(); runtime.dispose(); assert.equal(removed, 1); assert(observers[0].disconnected)
assert.equal(runtime.update({ sequence: 4, layers: {} }), false)

const paints = [], paths = [], progress = [], status = []
const playback = createTrackPlayback({ config: { coordinates: [[0, 0], [1, 0], [1, 1]], distanceMetric: 'coordinate', duration: 1000, loop: true }, win,
    onPosition: coordinate => paints.push({ coordinate }), onRoute: path => paths.push(path), onProgress: value => progress.push(value), onPlaying: value => status.push(value) })
function frame(time) { now = time; const pending = [...frames.values()]; frames.clear(); pending.forEach(callback => callback(time)) }
playback.toggle(); assert.equal(frames.size, 1)
frame(0); frame(750)
assert.deepEqual(paints.at(-1).coordinate, [1, .5], 'Playback must turn at the route vertex instead of crossing the diagonal')
assert.deepEqual(paths.at(-1), [[0, 0], [1, 0], [1, .5]])
playback.active(false); assert.equal(frames.size, 0)
now = 2000; playback.active(true); frame(2000); assert.deepEqual(paints.at(-1).coordinate, [1, .5], 'Hidden-tab time must not advance replay')
frame(2250); assert.deepEqual(paints.at(-1).coordinate, [1, 1]); frame(2500); assert.deepEqual(paints.at(-1).coordinate, [.5, 0])
playback.dispose(); assert.equal(frames.size, 0)
assert.equal(writes, 1, 'Marker replay must not rebuild a GPU dataset every frame')

// High-refresh displays: position advances every frame, path allocation stays at 20 Hz.
let positions = 0, routeWrites = 0
const smooth = createTrackPlayback({ config: { coordinates: [[0, 0], [1, 0]], distanceMetric: 'coordinate', duration: 1000 }, win,
    onPosition: () => positions++, onRoute: () => routeWrites++, onProgress() {}, onPlaying() {} })
smooth.toggle()
for (let time = 0; time <= 1000; time += 5) frame(time)
assert.equal(positions, 201)
assert.equal(routeWrites, 21, 'Do not copy/upload completed paths at display refresh rate')
assert.equal(frames.size, 0, 'Non-looping playback stops at its endpoint')
smooth.dispose()

const moving = createLayerMarkers({ map, sdk, config: definition, win, motionTarget: 'courier' })
moving.update(collection([0, 0]))
const courier = sdkMarkers.at(-1)
assert.equal(courier.subpixel, true, 'Tracked markers must retain fractional SDK positioning')
assert(courier.element.classList.contains('is-motion-target'), 'Replay target must stack above destination before its first frame')
moving.move('courier', [.000125, .00025])
assert.deepEqual(courier.coordinate, [.000125, .00025])
moving.update(collection([1, 1]), { duration: 1000 }); frame(1500)
assert.equal(frames.size, 1, 'One frame per layer for interpolated feed updates')
moving.pause(); assert.equal(frames.size, 0)
assert(courier.element.classList.contains('is-motion-target'), 'Priority survives pause and endpoint arrival')
moving.dispose(); assert.equal(frames.size, 0)
// Other supported SDKs may not expose MapLibre's optional subpixel method.
const compatibleSdk = { Marker: class extends sdk.Marker { setSubpixelPositioning = undefined } }
const fallback = createLayerMarkers({ map, sdk: compatibleSdk, config: definition, win })
fallback.update(collection([0, 0])); fallback.move('courier', [1, 1]); fallback.dispose()

const liveField = { ...field, experienceCard: {}, mapConfiguration: { provider: 'maplibre', experience: { layers: [definition],
    liveTrack: { layer: 'courier', feature: 'courier', coordinates: [[0, 0], [1, 0], [1, 1]], recoveryDuration: 2500, staleAfter: 15000 } } } }
const liveRuntime = createExperienceRuntime(liveField, { map, sdk }); liveRuntime.install()
const liveMarker = sdkMarkers.at(-1)
now = 2000
assert(liveRuntime.update({ sequence: 1, layers: {}, motion: { progress: .25, observedAt: 1000 } }))
frame(2500); const beforeRetarget = [...liveMarker.coordinate]
assert(liveRuntime.update({ sequence: 2, layers: {}, motion: { progress: .5, observedAt: 2000 } }))
assert.deepEqual(liveMarker.coordinate, beforeRetarget, 'A new fix must rebase from the rendered position without a jump')
frame(3500)
assert(Math.abs(liveMarker.coordinate[0]-1)<1e-9 && Math.abs(liveMarker.coordinate[1])<1e-9)
frame(63500)
assert(Math.abs(liveMarker.coordinate[1])<1e-9, 'No feed means no fabricated courier movement')
assert(liveRuntime.update({ sequence: 3, layers: {}, motion: { progress: 1, observedAt: 62000 } }))
assert.equal(frames.size, 1, 'Recovery shares the single marker RAF')
frame(64750)
assert(Math.abs(liveMarker.coordinate[0]-1)<1e-9 && Math.abs(liveMarker.coordinate[1]-.5)<1e-9, '60-second recovery follows the road corner, not a diagonal shortcut')
assert.equal(liveRuntime.update({ sequence: 4, layers: {}, motion: { progress: .1, observedAt: 2000 } }), false, 'Older GPS timestamps must not move a courier backwards even with a newer envelope sequence')
const liveSnapshot = JSON.stringify(liveRuntime.feature('courier', 'courier'))
assert.throws(() => liveRuntime.update({ sequence: 5, layers: {}, motion: { progress: 2, observedAt: 63000 } }))
assert.throws(() => liveRuntime.update({ sequence: 5, layers: { courier: { type: 'FeatureCollection', features: [] } }, motion: { progress: 1, observedAt: 63000 } }))
assert.equal(JSON.stringify(liveRuntime.feature('courier', 'courier')), liveSnapshot, 'Invalid recovery is atomic')
frame(66000); assert.deepEqual(liveMarker.coordinate, [1, 1]); assert.equal(frames.size, 0)
liveRuntime.dispose(); assert.equal(frames.size, 0)
const dateline = createTrackPath([[179, 0], [-179, 0]])
assert(Math.abs(Math.abs(dateline.coordinateAt(.5)[0])-180)<1e-9, 'Route indexes preserve the shortest antimeridian crossing')
assert.throws(() => createTrackPath([[0, 0], [0, 0]]))

// Exercise the actual transport failure/backoff/reconnect path with a virtual clock.
const timers = new Map(), feedStatuses = []; let nextTimer = 0, requestCount = 0
const feedWindow = { ...win, location: new URL('https://example.test/maps'),
    setTimeout(callback, delay) { const id = ++nextTimer; timers.set(id, { callback, due: now+delay }); return id },
    clearTimeout(id) { timers.delete(id) } }
const feedField = { ...liveField, experienceCard: {} }, feedRuntime = createExperienceRuntime(feedField, { map, sdk })
feedRuntime.install(); const feedMarker = sdkMarkers.at(-1)
const flush = async () => { for(let i=0;i<30;i++)await Promise.resolve() }
const transport = createLayerFeed({ endpoint: '/positions', interval: 1000, win: feedWindow,
    onData: message => feedRuntime.update(message), onStatus: status => feedStatuses.push(status),
    fetcher: async () => {
        requestCount++
        if(requestCount===2)throw new Error('Simulated lost connection')
        return new Response(JSON.stringify({ sequence: requestCount, layers: {}, motion: { progress: requestCount===1?.25:1, observedAt: requestCount===1?1000:62000 } }))
    } })
transport.active(true); await flush(); assert.equal(feedStatuses.at(-1), 'live')
frame(now+1000); const heldPosition = [...feedMarker.coordinate]
const runTimer = () => { const [id, timer] = [...timers].sort((a,b)=>a[1].due-b[1].due)[0]; timers.delete(id); now=Math.max(now,timer.due); timer.callback() }
runTimer(); await flush(); assert.equal(feedStatuses.at(-1), 'retrying')
now+=60000
assert.deepEqual(feedMarker.coordinate, heldPosition, 'Failed requests retain the last confirmed position')
runTimer(); await flush(); assert.equal(feedStatuses.at(-1), 'live')
assert.deepEqual(feedMarker.coordinate, heldPosition, 'Reconnection must not teleport before its first animation frame')
frame(now+1250); assert(Math.abs(feedMarker.coordinate[0]-1)<1e-9 && feedMarker.coordinate[1]>0)
frame(now+1250); assert.deepEqual(feedMarker.coordinate, [1, 1])
transport.dispose(); feedRuntime.dispose(); assert.equal(timers.size, 0); assert.equal(frames.size, 0)
let inFlightSignal
const pendingFeed = createLayerFeed({ endpoint: '/positions', interval: 1000, win: feedWindow, onData() {}, onStatus() {},
    fetcher: (_url, {signal}) => { inFlightSignal=signal; return new Promise((_resolve,reject)=>signal.addEventListener('abort',()=>reject(new Error('aborted')))) } })
pendingFeed.active(true); pendingFeed.dispose(); await flush(); assert(inFlightSignal.aborted); assert.equal(timers.size, 0)

installLayer(map, { id: 'demand', kind: 'heatmap', data: { type: 'FeatureCollection', features: [] }, visible: true, weightProperty: 'weight', weightMax: 1,
    colorStops: [[0, '#4285f400'], [.25, '#4285f466'], [1, '#ef4444d9']], radiusStops: [[10, 18], [14, 40]], heatmapIntensity: .9, opacity: .8 }, 'maplibre')
const heatmap = layers.get('fff-data-demand-heat')
assert.equal(heatmap.before, 'labels', 'Labels must render above demand heatmap')
assert.deepEqual(heatmap.paint['heatmap-radius'], ['interpolate', ['linear'], ['zoom'], 10, 18, 14, 40])
assert.equal(heatmap.paint['heatmap-color'][4], 'rgba(66,133,244,0)')
console.log('PASS: subpixel motion/priority, bounded route cadence, timestamped 60-second road recovery, actual feed failure/backoff/reconnect/abort, retarget continuity, stale fixes/atomic validation, no fabricated movement, dateline routes, source idempotence and heatmap.')

// Explicit stops add dwell time without changing route speed or consuming another RAF.
const stopPaints=[]
const stopReplay=createTrackPlayback({config:{coordinates:[[0,0],[1,0]],duration:1000,stops:[{progress:.4,wait:300}]},win,
    onPosition:(coordinate,motion)=>stopPaints.push({coordinate,motion}),onProgress(){},onPlaying(){}})
stopReplay.toggle();frame(0);frame(400);frame(650)
assert(Math.abs(stopPaints.at(-1).coordinate[0]-.4)<1e-12);assert.equal(stopPaints.at(-1).coordinate[1],0)
frame(700);assert(Math.abs(stopPaints.at(-1).motion.progress-.4)<1e-12)
frame(900);assert(Math.abs(stopPaints.at(-1).motion.progress-.6)<1e-12)
frame(1300);assert.equal(stopPaints.at(-1).motion.progress,1);assert.equal(frames.size,0)
stopReplay.dispose()
const headingPath=createTrackPath([[0,0],[1,0],[1,1]],'coordinate')
assert(Math.abs(headingPath.bearingAt(.25)-90)<.001)
assert(Math.abs(headingPath.bearingAt(.75))<.001)
assert(headingPath.bearingAt(.5)>40&&headingPath.bearingAt(.5)<50,'Car turns smoothly across the road vertex')
const vehicleSdk={Marker:class extends sdk.Marker{
    constructor(options){super(options);this.options=options}
    setRotation(value){this.rotation=value;return this}
}}
const car=createLayerMarkers({map,sdk:vehicleSdk,config:{...definition,markerVariant:'car'},win,motionTarget:'courier',route:headingPath})
car.update(collection([0,0]));const carMarker=sdkMarkers.at(-1)
assert.equal(carMarker.options.rotationAlignment,'map');assert.equal(carMarker.options.pitchAlignment,'viewport')
assert(carMarker.element.classes.has('is-car'));assert.equal(carMarker.element.children[0].className,'fff-location__vehicle-image')
car.move('courier',[1,.5],{progress:.75});assert(Math.abs(carMarker.rotation)<.001)
car.dispose();assert.equal(frames.size,0)
console.log('PASS: optional dwell stops, shared route heading, component-owned vehicle image, map-relative rotation and viewport pitch and owner disposal.')

const {createTrackCamera}=await import('../../resources/js/location/layers/track-camera.js')
const cameraCommands=[],cameraEvents=new Map(),cameraCleanups=[]
const followMap={jumpTo(command){cameraCommands.push(command)},on(type,listener){if(!cameraEvents.has(type))cameraEvents.set(type,new Set());cameraEvents.get(type).add(listener)},off(type,listener){assert.ok(cameraEvents.get(type).delete(listener));if(!cameraEvents.get(type).size)cameraEvents.delete(type)}}
const cameraEvent=(type,event)=>cameraEvents.get(type)?.forEach(listener=>listener(event))
const follow=createTrackCamera({map:followMap,configuration:{zoom:15.5,pitch:40,rotate:true,pauseOnInteraction:true},scope:{defer(cleanup){cameraCleanups.push(cleanup)}}})
follow.update([1,2],90);follow.update([2,3],100)
assert.deepEqual(cameraCommands[0],{center:[1,2],zoom:15.5,pitch:40,bearing:90})
assert.deepEqual(cameraCommands[1],{center:[2,3],bearing:100},'Do not reset user zoom or pitch on each tracking frame')
cameraEvent('dragstart',{});follow.update([3,4],110);assert.equal(cameraCommands.length,3,'Programmatic camera updates never pause following')
cameraEvent('dragstart',{originalEvent:{}});follow.update([4,5],120);assert.equal(cameraCommands.length,3)
follow.resume();assert.deepEqual(cameraCommands.at(-1),{center:[4,5],bearing:120})
follow.active(false);follow.update([5,6],130);assert.equal(cameraCommands.length,4)
follow.active(true);follow.update([5,6],130);assert.equal(cameraCommands.length,5)
follow.dispose();cameraCleanups.forEach(cleanup=>cleanup());follow.update([6,7],140)
assert.equal(cameraEvents.size,0);assert.equal(cameraCommands.length,5);assert.equal(frames.size,0,'Camera following must not add an independent frame loop')
console.log('PASS: camera follows rendered positions without repeated easing, optional gesture pause/recenter, preserved zoom/pitch and owner disposal.')

const zoomWrites=[],zoomListeners=new Map(),zoomCleanup=[]
const gestureMap={jumpTo(value){zoomWrites.push(value)},on(type,callback){zoomListeners.set(type,callback)},off(type){zoomListeners.delete(type)}}
const gestureFollow=createTrackCamera({map:gestureMap,configuration:{zoom:null,pitch:null,rotate:false,pauseOnInteraction:false},scope:{defer(fn){zoomCleanup.push(fn)}}})
gestureFollow.update([1,2],0);zoomListeners.get('zoomstart')({});gestureFollow.update([2,3],0)
assert.equal(zoomWrites.length,1,'Following must allow the SDK zoom animation to finish')
zoomListeners.get('zoomend')({});assert.deepEqual(zoomWrites.at(-1),{center:[2,3]})
gestureFollow.dispose();zoomCleanup.forEach(fn=>fn());assert.equal(zoomListeners.size,0)
console.log('PASS: SDK zoom/rotation completion is not cancelled by tracking camera writes.')

// Navigation preserves routing geometry instead of simplifying small street bends.
let routeSource; const routeLayers=[];
const routeMap={getSource:()=>null,addSource(id,configuration){routeSource=configuration},getLayer:()=>null,addLayer(layer){routeLayers.push(layer)}};
const routeGeometry={type:'FeatureCollection',features:[{type:'Feature',properties:{},geometry:{type:'LineString',coordinates:[[0,0],[.00001,.00002],[.00003,.00003]]}}]};
installLayer(routeMap,{id:'road',kind:'geometry',navigationRoute:true,data:routeGeometry,color:'#000000',width:4,opacity:1,visible:true},'maplibre');
assert.equal(routeSource.tolerance,0);assert.equal(routeSource.data,routeGeometry);assert.equal(routeLayers.length,1);
assert.deepEqual(routeLayers[0].layout,{'line-cap':'butt','line-join':'miter','line-miter-limit':4,visibility:'visible'});
assert.equal(routeLayers[0].paint['line-width'],4);
console.log('PASS: navigation uses one GPU line, exact road vertices and a 4 px pixel-width presentation.');

const {createRemainingRoute}=await import('../../resources/js/location/layers/remaining-route.js');
const trims=[];let trimZoom=15,trimTime=0;
const clip=createRemainingRoute({map:{getZoom:()=>trimZoom,getSource:()=>({setData(value){trims.push(value)}})},layer:'road',path:headingPath,win:{performance:{now:()=>trimTime}}});
clip.update(0);assert.deepEqual(trims.at(-1).features[0].geometry.coordinates,[[0,0],[1,0],[1,1]]);
trimTime=50;clip.update(.75);assert.deepEqual(trims.at(-1).features[0].geometry.coordinates,[[1,.5],[1,1]],'Visible geometry starts at the rendered road coordinate');
const trimCount=trims.length;clip.update(.75);assert.equal(trims.length,trimCount,'Stationary fixes do not upload geometry');
trimTime=55;clip.update(.76);assert.equal(trims.length,trimCount,'Writes stay bounded below high zoom');
trimZoom=18;trimTime=67;clip.update(.8);assert.deepEqual(trims.at(-1).features[0].geometry.coordinates[0],headingPath.coordinateAt(.8),'High zoom tightens cadence without gradient metric drift');
clip.update(1);assert.deepEqual(trims.at(-1).features,[]);clip.update(.1);assert.deepEqual(trims.at(-1).features[0].geometry.coordinates[0],headingPath.coordinateAt(.1),'Loop/backward correction restores remaining route immediately');
clip.reset();clip.update(.5);assert.deepEqual(trims.at(-1).features[0].geometry.coordinates[0],headingPath.coordinateAt(.5),'Style replacement restores exact remaining geometry');
console.log('PASS: remaining street geometry follows rendered progress, high-zoom cadence, endpoint/loop recovery and style reset.');

const {createResourceScope}=await import('../../resources/js/runtime/lifecycle/resource-scope.js');
const inputCanvas=new EventTarget(),inputScope=createResourceScope(),inputWrites=[],inputEvents=new Map(),inputStates=[];
const inputMap={getCanvas:()=>inputCanvas,jumpTo:value=>inputWrites.push(value),on(type,handler){inputEvents.set(type,handler)},off(type){inputEvents.delete(type)}};
const inputCamera=createTrackCamera({map:inputMap,configuration:{zoom:null,pitch:null,rotate:false,pauseOnInteraction:true},scope:inputScope,onChange:value=>inputStates.push(value)});
inputCamera.update([1,2],0);inputCanvas.dispatchEvent(new Event('pointerdown'));inputCamera.update([2,3],0);
assert.equal(inputWrites.length,1,'Pointer intent must detach before SDK drag detection can be cancelled');assert.equal(inputStates.at(-1),false);
inputCamera.resume();assert.deepEqual(inputWrites.at(-1),{center:[2,3]});inputCanvas.dispatchEvent(new Event('wheel'));inputCamera.update([3,4],0);assert.equal(inputWrites.length,2);
inputScope.dispose();inputCamera.resume();inputCanvas.dispatchEvent(new Event('pointerdown'));inputCamera.update([4,5],0);assert.equal(inputWrites.length,4,'Disposed canvas listeners cannot pause another owner');inputCamera.dispose();assert.equal(inputEvents.size,0);
console.log('PASS: pointer/wheel interaction intent detaches before SDK gestures, explicit resume and listener disposal.');

const {projectedTrackHeading}=await import('../../resources/js/location/layers/track-heading.js');
const diagonalPath=createTrackPath([[0,0],[1,1]],'coordinate'),headingEvents=new Map();let pitchScale=.5;
const pitchedMap={...map,project:([lng,lat])=>({x:lng,y:-lat*pitchScale}),getCenter:()=>({lng:.5}),on(type,listener){headingEvents.set(type,listener)},off(type){headingEvents.delete(type)}};
assert(Math.abs(projectedTrackHeading(pitchedMap,diagonalPath,.5)-63.4349488229)<.001,'Screen heading must follow the tilted street rather than raw compass bearing');
let geographicHeading;
const tiltedCar=createLayerMarkers({map:pitchedMap,sdk:vehicleSdk,config:{...definition,markerVariant:'car'},win,motionTarget:'courier',route:diagonalPath,onMotion:(coordinate,bearing)=>{geographicHeading=bearing}});
tiltedCar.update(collection([.5,.5]));const tiltedMarker=sdkMarkers.at(-1);
assert.equal(tiltedMarker.options.rotationAlignment,'viewport');assert(Math.abs(tiltedMarker.rotation-63.4349488229)<.001);assert(Math.abs(geographicHeading-45)<.01,'Camera receives geographic heading separately');
pitchScale=.25;headingEvents.get('move')();assert(Math.abs(tiltedMarker.rotation-75.963756532)<.001,'Camera motion reprojects even a stopped vehicle');
tiltedCar.dispose();assert.equal(headingEvents.size,0);
console.log('PASS: vehicle tangent matches the visible pitched street, stationary camera changes and independent geographic camera heading.');

// Passed waypoints follow the painted fix, never a received target ahead of it.
const waypointPoint=(id,coordinates)=>({type:'FeatureCollection',features:[{type:'Feature',id,geometry:{type:'Point',coordinates},properties:{label:id,tooltip:id}}]})
const originDefinition={...definition,id:'origin',markerVariant:'origin',data:waypointPoint('departure',[0,0])}
const destinationDefinition={...definition,id:'destination',markerVariant:'destination',data:waypointPoint('arrival',[1,0])}
let tooltipClosed=0
const waypointField={...field,closeFeatureTooltip(){tooltipClosed++},showFeatureTooltip(){},experienceCard:{},mapConfiguration:{provider:'maplibre',experience:{
    layers:[definition,originDefinition,destinationDefinition],hidePassedWaypoints:true,stops:[{id:'pickup',label:'Pickup',progress:.4,wait:300}],
    liveTrack:{layer:'courier',feature:'courier',coordinates:[[0,0],[1,0]],recoveryDuration:2500,staleAfter:15000}
}}}
const waypointRuntime=createExperienceRuntime(waypointField,{map,sdk})
waypointRuntime.install()
const waypointMarkers=sdkMarkers.slice(-4),[waypointCourier,originMarker,destinationMarker,stopMarker]=waypointMarkers
assert.equal(originMarker.element.style.visibility,'visible')
assert.equal(stopMarker.element.style.visibility,'visible')
originMarker.element.dispatchEvent(new Event('pointerenter'))
now=0
waypointRuntime.update({sequence:1,layers:{},motion:{progress:.4,observedAt:1000}})
assert.equal(originMarker.element.style.visibility,'visible','Receiving a future fix does not hide the current start')
frame(500)
assert.equal(originMarker.element.style.visibility,'hidden')
assert.equal(tooltipClosed,1,'Passing the hovered origin closes its owned tooltip')
assert.equal(stopMarker.element.style.visibility,'visible')
frame(1000)
assert.equal(stopMarker.element.style.visibility,'visible','A stopped courier at the exact waypoint keeps its label')
frame(61000)
assert.equal(stopMarker.element.style.visibility,'visible','An outage does not invent completed stops')
waypointRuntime.update({sequence:2,layers:{},motion:{progress:.8,observedAt:62000}})
assert.equal(stopMarker.element.style.visibility,'visible','Recovery starts from the painted waypoint')
frame(62250)
assert.equal(stopMarker.element.style.visibility,'hidden','The stop disappears after the rendered courier passes it')
assert.equal(destinationMarker.element.style.visibility,'visible','The destination remains visible')
frame(63500)
waypointRuntime.install()
assert.equal(originMarker.element.style.visibility,'hidden','Style restoration does not resurrect a passed origin')
assert.equal(stopMarker.element.style.visibility,'hidden')
waypointRuntime.visible('origin',false)
waypointRuntime.update({sequence:3,layers:{},duration:0,motion:{progress:0,observedAt:63000}})
assert.equal(originMarker.element.style.visibility,'hidden','Reset does not override explicit layer visibility')
waypointRuntime.visible('origin',true)
assert.equal(originMarker.element.style.visibility,'visible')
assert.equal(stopMarker.element.style.visibility,'visible','Backward fixes restore upcoming stops')
waypointRuntime.dispose()
assert.equal(frames.size,0)

const replayWaypointField={...waypointField,mapConfiguration:{provider:'maplibre',experience:{
    layers:[definition,originDefinition,destinationDefinition],hidePassedWaypoints:true,stops:[{id:'pickup',label:'Pickup',progress:.4,wait:300}],
    playback:{layer:'courier',feature:'courier',coordinates:[[0,0],[1,0]],duration:1000,loop:true,autoPlay:false}
}}}
const replayWaypoints=createExperienceRuntime(replayWaypointField,{map,sdk})
replayWaypoints.install()
const [,replayOrigin,,replayStop]=sdkMarkers.slice(-4)
now=0;replayWaypoints.playback();frame(0);frame(400);frame(650)
assert.equal(replayOrigin.element.style.visibility,'hidden')
assert.equal(replayStop.element.style.visibility,'visible','Replay dwell keeps the current stop visible')
frame(701)
assert.equal(replayStop.element.style.visibility,'hidden')
frame(1300);frame(1300)
assert.equal(replayOrigin.element.style.visibility,'visible','Exact replay reset restores the start')
assert.equal(replayStop.element.style.visibility,'visible','Replay loop restores all upcoming stops')
replayWaypoints.dispose();assert.equal(frames.size,0)
console.log('PASS: passed start/tooltip/stop visibility, exact dwell, outages, rendered recovery, style restore, explicit visibility, backward fixes, replay loops and disposal.')

const scooter=createLayerMarkers({map:pitchedMap,sdk:vehicleSdk,config:{...definition,markerVariant:'scooter',markerImage:'/delivery-scooter.png'},win,motionTarget:'courier',route:diagonalPath})
scooter.update(collection([0,0]))
const scooterMarker=sdkMarkers.at(-1)
assert.equal(scooterMarker.options.rotationAlignment,'viewport')
assert.equal(scooterMarker.options.pitchAlignment,'viewport')
assert(scooterMarker.element.classes.has('is-scooter'))
assert.equal(scooterMarker.element.children[0].src,'/delivery-scooter.png')
scooter.move('courier',diagonalPath.coordinateAt(.5),{progress:.5})
assert(Number.isFinite(scooterMarker.rotation),'Scooter shares projected road heading')
scooter.dispose();assert.equal(frames.size,0)
console.log('PASS: scooter artwork, shared vehicle presentation, projected heading and disposal.')
