import { validPoint } from '../geometry/coordinates.js'
import { validRing } from '../geometry/collection.js'
import { validMetadata, validMetadataText } from '../editing/metadata.js'
import { validFeatureProperties } from '../editing/properties.js'

const bytes = value => new TextEncoder().encode(JSON.stringify(value)).length
export const geometryPositions = geometry => {
    const depth = {Point:0, LineString:1, MultiPoint:1, Polygon:2, MultiLineString:2, MultiPolygon:3}[geometry.type]
    return depth === 0 ? [geometry.coordinates] : geometry.coordinates.flat(depth - 1)
}
const inside = ([x,y], ring) => {
    let result = false
    for (let i=0,j=ring.length-1;i<ring.length;j=i++) {
        if ((ring[i][1]>y)!==(ring[j][1]>y) && x<(ring[j][0]-ring[i][0])*(y-ring[i][1])/(ring[j][1]-ring[i][1])+ring[i][0]) result=!result
    }
    return result
}

/** All live/imported data is validated before touching SDK sources or reactive presentation. */
export function normalizeDataset(input, {kind='geometry', cluster=false, navigationRoute=false} = {}) {
    if (!input || input.type !== 'FeatureCollection' || !Array.isArray(input.features) || input.features.length>10000 || bytes(input)>4194304) throw new Error('Invalid or oversized map dataset.')
    const ids = new Set(); let vertices=0
    const features=input.features.map((feature,index) => {
        const id=String(feature?.id ?? `feature-${index}`), geometry=feature?.geometry
        if (feature?.type!=='Feature' || !geometry || !['Point','LineString','Polygon','MultiPoint','MultiLineString','MultiPolygon'].includes(geometry.type)
            || !['string','number','undefined'].includes(typeof feature.id) || typeof feature.id==='number' && !Number.isSafeInteger(feature.id)
            || !id || new TextEncoder().encode(id).length>128 || !validMetadataText(id) || ids.has(id)) throw new Error('Invalid map feature identity or geometry.')
        ids.add(id)
        if ((['heatmap','pills','tracked'].includes(kind)||cluster) && geometry.type!=='Point') throw new Error('This layer requires Point features.')
        if(navigationRoute && geometry.type!=='LineString')throw new Error('Navigation routes require LineString geometry.')
        const properties=feature.properties ?? {}
        if (typeof properties!=='object' || Array.isArray(properties) || Object.keys(properties).length>32 || bytes(properties)>65536) throw new Error('Invalid map properties.')
        for (const [key,value] of Object.entries(properties)) {
            if (!/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/.test(key) || ['constructor','prototype'].includes(key) || !validMetadata(value)) throw new Error('Invalid map metadata.')
        }
        if(!validFeatureProperties(properties))throw new Error('Invalid presentation properties.')
        const depth={Point:0,LineString:1,MultiPoint:1,Polygon:2,MultiLineString:2,MultiPolygon:3}[geometry.type]
        const coordinates=(value,d) => {
            if (d===0) { if (!validPoint(value) || ++vertices>50000) throw new Error('Invalid coordinate or vertex budget.'); return [...value] }
            if (!Array.isArray(value) || !value.length || value.length>10000) throw new Error('Invalid coordinate list.')
            return value.map(part=>coordinates(part,d-1))
        }
        const output={type:geometry.type,coordinates:coordinates(geometry.coordinates,depth)}
        const polygons=output.type==='MultiPolygon'?output.coordinates:output.type==='Polygon'?[output.coordinates]:[]
        for (const polygon of polygons) {
            if (polygon.length>32 || !polygon.every(validRing) || polygon.slice(1).some(hole=>!inside(hole[0],polygon[0]))) throw new Error('Invalid polygon rings.')
        }
        const lines=output.type==='MultiLineString'?output.coordinates:output.type==='LineString'?[output.coordinates]:[]
        if (lines.some(line=>line.length<2 || line.some((point,i)=>i>0 && point[0]===line[i-1][0] && point[1]===line[i-1][1]))) throw new Error('Invalid line geometry.')
        return {type:'Feature',id,geometry:output,properties:structuredClone(properties)}
    })
    if (['pills','tracked'].includes(kind) && features.length>200) throw new Error('DOM marker budget exceeded.')
    return {type:'FeatureCollection',features}
}
