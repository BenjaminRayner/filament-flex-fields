import {createAppearanceNamespace} from '../sdk/appearance-namespace.js'

const names = createAppearanceNamespace('font-preview')
const MAX_FONT_BYTES = 524288

export function assertFontResource(resource, origin = globalThis.location?.origin) {
    if (!resource || !/^[a-f0-9]{64}$/.test(resource.key) || !['google-css','file'].includes(resource.kind)
        || typeof resource.url !== 'string' || resource.url.length > 4096
        || typeof resource.text !== 'string' || !resource.text.length || /[\x00-\x1f\x7f]/.test(resource.text) || [...resource.text].length > 128
        || !Number.isInteger(resource.weight) || resource.weight < 1 || resource.weight > 1000
        || !['normal','italic'].includes(resource.style)) throw new TypeError('Invalid font preview descriptor.')
    const url = new URL(resource.url, origin)
    if (url.username || url.password || url.hash) throw new TypeError('Invalid font URL credentials.')
    if (resource.kind === 'google-css') {
        if (url.origin !== 'https://fonts.googleapis.com' || url.pathname !== '/css2') throw new TypeError('Invalid Google font origin.')
    } else if (url.origin !== origin || !/\.(woff2?|ttf|otf)$/i.test(url.pathname)) throw new TypeError('Invalid local font origin.')
    return resource
}

async function boundedBody(response, maximum, signal) {
    if (!response.ok || Number(response.headers?.get('content-length') ?? 0) > maximum) throw new Error('Font preview response rejected.')
    if (!response.body?.getReader) {
        const buffer = await response.arrayBuffer()
        if (buffer.byteLength > maximum) throw new Error('Font preview exceeds its byte budget.')
        return buffer
    }
    const reader = response.body.getReader(), chunks = []
    let bytes = 0
    try {
        for (;;) {
            const {done,value} = await reader.read()
            if (signal.aborted) throw new DOMException('Aborted','AbortError')
            if (done) break
            bytes += value.byteLength
            if (bytes > maximum) throw new Error('Font preview exceeds its byte budget.')
            chunks.push(value)
        }
    } catch (error) { await reader.cancel().catch(()=>{}); throw error }
    finally { reader.releaseLock() }
    const buffer = new Uint8Array(bytes)
    let offset = 0
    for (const chunk of chunks) {buffer.set(chunk,offset);offset += chunk.byteLength}
    return buffer.buffer
}

/** Fetch only the requested subset and decode under a private family alias. */
export async function loadFontPreview(resource, signal, {fetch = globalThis.fetch, Font = globalThis.FontFace, origin = globalThis.location?.origin} = {}) {
    assertFontResource(resource,origin)
    const controller = new AbortController()
    const abort = () => controller.abort()
    if (signal.aborted) abort()
    signal.addEventListener('abort',abort,{once:true})
    const timer = setTimeout(abort,15000)
    try {
        let url = resource.url
        if (resource.kind === 'google-css') {
            const response = await fetch(url,{signal:controller.signal,credentials:'omit',referrerPolicy:'no-referrer'})
            if (new URL(response.url || url).origin !== 'https://fonts.googleapis.com') throw new Error('Unexpected font CSS redirect.')
            const css = new TextDecoder().decode(await boundedBody(response,32768,controller.signal))
            const matches = [...css.matchAll(/src\s*:\s*url\(\s*['"]?(https:\/\/fonts\.gstatic\.com\/[^\s)'"]+)['"]?\s*\)/g)]
            const urls = [...new Set(matches.map(match=>match[1]))]
            if (urls.length !== 1) throw new Error('Font subset requires one verified binary.')
            url = urls[0]
        }
        const response = await fetch(url,{signal:controller.signal,credentials:'omit',referrerPolicy:'no-referrer'})
        const finalUrl = new URL(response.url || url,origin)
        if (resource.kind === 'google-css' ? finalUrl.origin !== 'https://fonts.gstatic.com' : finalUrl.origin !== origin) throw new Error('Unexpected font redirect.')
        const buffer = await boundedBody(response,MAX_FONT_BYTES,controller.signal)
        const signature = new Uint8Array(buffer,0,Math.min(4,buffer.byteLength))
        const magic = String.fromCharCode(...signature)
        if (!['wOF2','wOFF','OTTO','\u0000\u0001\u0000\u0000'].includes(magic)) throw new Error('Unsupported font binary.')
        if (controller.signal.aborted) throw new DOMException('Aborted','AbortError')
        const alias = names.font(`f${resource.key.slice(0,48)}`)
        const face = new Font(alias,buffer,{weight:String(resource.weight),style:resource.style,display:'block'})
        await waitForFontDecode(face,controller.signal)
        if (controller.signal.aborted || face.status !== 'loaded') throw new DOMException('Font owner retired.','AbortError')
        return {face,alias,bytes:buffer.byteLength}
    } finally {clearTimeout(timer);signal.removeEventListener('abort',abort)}
}

/** Retire the wait promptly; native decoding itself cannot be forcibly interrupted. */
async function waitForFontDecode(face,signal) {
    if (signal.aborted) throw new DOMException('Aborted','AbortError')
    let stop
    const cancelled=new Promise((_,reject)=>{
        stop=()=>reject(new DOMException('Aborted','AbortError'))
        signal.addEventListener('abort',stop,{once:true})
    })
    try {await Promise.race([face.load(),cancelled])}
    finally {signal.removeEventListener('abort',stop)}
}
