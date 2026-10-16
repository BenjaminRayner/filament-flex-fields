/** An authored pen-trajectory demo, exported through the production ink serializer. */
import fs from 'node:fs'
import path from 'node:path'
import { strokesToSvg } from '../resources/js/signature/geometry.js'

const root = path.resolve(import.meta.dirname, '..')
const output = path.join(root, 'resources/fixtures/signature-replay-preview.svg')
// Cubics describe pen movements, including separate pen lifts, rather than a filled silhouette.
const trajectories = [
    [[69,157], [100,126,150,91,173,105], [194,119,160,176,140,196], [121,218,103,209,118,184], [130,163,152,156,164,151]],
    [[161,153], [180,118,201,131,181,157], [168,174,160,165,176,143], [189,127,195,126,185,150], [174,176,187,160,201,140], [210,123,199,165,201,161], [205,142,219,126,222,135], [226,143,207,172,218,164], [235,151,247,117,250,112], [270,78,270,65,258,80], [247,94,231,156,238,166], [245,180,254,163,266,151]],
    [[287,146], [315,98,341,77,340,94], [338,109,291,125,285,144], [274,163,285,184,310,177], [334,169,331,151,310,150], [292,148,300,139,321,129], [340,118,363,105,374,101]],
    [[353,130], [343,149,336,176,344,169], [359,157,367,137,374,135], [383,135,360,168,369,167], [379,167,394,137,398,137], [407,139,382,173,393,169], [406,163,421,137,421,145], [423,162,411,175,407,168], [402,159,427,141,438,143], [457,148,449,167,434,171], [417,177,425,160,444,148], [464,136,487,133,484,144], [480,158,457,162,451,162]],
    [[335,127], [374,118,404,112,428,109]],
    [[120,215], [230,201,380,210,485,190]],
]
const strokes = trajectories.map((trajectory, strokeIndex) => {
    let current = trajectory[0], elapsed = 0
    const points = [{ x: current[0], y: current[1], p: .5, t: 0 }]
    for (const segment of trajectory.slice(1)) {
        const [x1,y1,x2,y2,x3,y3] = segment, [x0,y0] = current
        const length = Math.hypot(x1-x0,y1-y0)+Math.hypot(x2-x1,y2-y1)+Math.hypot(x3-x2,y3-y2)
        const steps = Math.max(6,Math.ceil(length/7))
        for (let i=1;i<=steps;i++) {
            const t=i/steps,u=1-t
            elapsed += 8
            points.push({ x: +(u*u*u*x0+3*u*u*t*x1+3*u*t*t*x2+t*t*t*x3).toFixed(1),
                y: +(u*u*u*y0+3*u*u*t*y1+3*u*t*t*y2+t*t*t*y3).toFixed(1),
                p: +(.48+.17*Math.sin((points.length+strokeIndex)*.18)).toFixed(3), t:elapsed })
        }
        current = [x3,y3]
    }
    return { ink:true, smoothing:true, color:'#18181b', width:strokeIndex===5?2.6:4.6, points }
})
const svg = strokesToSvg(strokes,{penColor:'#18181b',penWidth:4.6,backgroundColor:null,viewBoxWidth:600,viewBoxHeight:260})+'\n'
if (Buffer.byteLength(svg)>48*1024) throw new Error('Replay fixture exceeds its field budget')
if (process.argv.includes('--check')) {
    if(fs.readFileSync(output,'utf8')!==svg)throw new Error('Rebuild the replay signature fixture')
} else fs.writeFileSync(output,svg)
console.log('Exported replayable signature fixture:',Buffer.byteLength(svg),'bytes')
