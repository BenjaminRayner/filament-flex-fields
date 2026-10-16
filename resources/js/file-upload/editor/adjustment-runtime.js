import { defaultAdjustments, transformPixels } from './adjustments.js'

/** One editor session owns pixels, its worker, pending requests and image URLs. */
export function createAdjustmentRuntime({ owner, image, apply }) {
    let originalImage = null, fullInWorker = false
    let worker = null, base = null, preview = null, canvas = null, width = 0, height = 0, previewWidth = 0, previewHeight = 0
    let latest = null, renderedFull = false
    let yieldTimer = null, yieldResolve = null
    let pending = null, queued = null, running = null, disposed = false
    const stopped = () => disposed || owner.disposed
    const workerSource = `const transformPixels = ${transformPixels.toString()}; let source; self.onmessage = ({data}) => { if(data.base){source={...data,base:null,preview:new Uint8ClampedArray(data.base)};return} if(data.fullSource){source.base=new Uint8ClampedArray(data.fullSource);return} try { const full=data.full; const pixels=transformPixels(full?source.base:source.preview,full?source.width:source.previewWidth,full?source.height:source.previewHeight,data.values);self.postMessage({pixels:pixels.buffer},[pixels.buffer]) } catch(error){self.postMessage({error:String(error)})} }`
    function stopWorker() { worker?.terminate(); worker = null; pending?.reject(new Error('Image worker stopped')); pending = null }
    const cleanup = owner.defer(() => { disposed = true; clearTimeout(yieldTimer); yieldResolve?.(); yieldResolve = null; stopWorker(); base = preview = null; latest = null; if (originalImage) originalImage.src = ''; originalImage = null; if (canvas) { canvas.width = canvas.height = 0 }; canvas = null; owner.releaseUrl('editor-adjust-worker'); owner.releaseUrl('editor-adjust-image') })

    async function initialize() {
        width = image.naturalWidth; height = image.naturalHeight
        if (!width || !height) throw new Error('Image dimensions are unavailable')
        // Keep a decoded original independently of Cropper.replace(). No full
        // pixel array is read/copied while opening or moving the ruler.
        originalImage = new Image(); originalImage.src = image.src; await originalImage.decode()
        if (stopped()) throw new Error('Editor closed')
        const scale = Math.min(1, 1024 / Math.max(width, height))
        previewWidth = Math.max(1, Math.round(width * scale)); previewHeight = Math.max(1, Math.round(height * scale))
        canvas = document.createElement('canvas'); canvas.width = previewWidth; canvas.height = previewHeight
        const context = canvas.getContext('2d', { willReadFrequently: true })
        if (!context) throw new Error('Canvas is unavailable')
        context.imageSmoothingEnabled = true; context.imageSmoothingQuality = 'high'
        context.drawImage(originalImage, 0, 0, previewWidth, previewHeight)
        preview = context.getImageData(0, 0, previewWidth, previewHeight).data
        try {
            const url = owner.objectUrl('editor-adjust-worker', new Blob([workerSource], { type: 'text/javascript' }))
            worker = new Worker(url)
            worker.onmessage = ({ data }) => { const request = pending; pending = null; data.error ? request?.reject(new Error(data.error)) : request?.resolve(new Uint8ClampedArray(data.pixels)) }
            worker.onerror = () => stopWorker()
            // Only bounded preview pixels are copied for worker/fallback ownership.
            const copy = preview.slice()
            worker.postMessage({ base: copy.buffer, width, height, previewWidth, previewHeight }, [copy.buffer])
        } catch { stopWorker() }
    }
    function readOriginal() {
        canvas.width = width; canvas.height = height
        const context = canvas.getContext('2d', { willReadFrequently: true })
        context.drawImage(originalImage, 0, 0, width, height)
        return context.getImageData(0, 0, width, height).data
    }
    async function pixels(values, full) {
        if (full && !base && !fullInWorker) {
            base = readOriginal()
            if (worker) {
                try { worker.postMessage({ fullSource: base.buffer }, [base.buffer]); fullInWorker = true; base = null }
                catch { stopWorker() }
            }
        }
        if (worker) {
            try { return await new Promise((resolve, reject) => { pending = { resolve, reject }; worker.postMessage({ values, full }) }) }
            catch (error) { if (stopped()) throw error; stopWorker() }
        }
        if (full && !base) base = readOriginal()
        const source = full ? base : preview, rows = full ? height : previewHeight, columns = full ? width : previewWidth
        const result = new Uint8ClampedArray(source.length)
        for (let row = 0; row < rows; row += 16) {
            if (stopped()) throw new Error('Editor closed')
            transformPixels(source, columns, rows, values, row, Math.min(row + 16, rows), result)
            await new Promise(resolve => { yieldResolve = resolve; yieldTimer = setTimeout(() => { yieldTimer = null; yieldResolve = null; resolve() }, 0) })
        }
        return result
    }
    async function render() {
        while (queued && !stopped()) {
            const { values, full } = queued; queued = null
            const output = await pixels(values, full)
            if (stopped()) return
            canvas.width = full ? width : previewWidth; canvas.height = full ? height : previewHeight
            canvas.getContext('2d').putImageData(new ImageData(output, canvas.width, canvas.height), 0, 0)
            const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'))
            if (stopped()) return
            if (!blob) throw new Error('Image preview could not be encoded')
            const url = owner.objectUrl('editor-adjust-image', blob)
            const decoded = new Image(); decoded.src = url; await decoded.decode()
            if (stopped()) return
            // Publish completed frames in sequence, then render the latest
            // queued settings. Dropping every superseded frame starves the
            // preview while continuous pointer input outruns pixel processing.
            await apply(url)
            renderedFull = full
        }
    }
    function schedule(values = defaultAdjustments()) {
        if (stopped()) return Promise.reject(new Error('Editor closed'))
        latest = { ...values }; queued = { values: latest, full: false }
        if (!running) running = render().finally(() => { running = null })
        return running
    }
    return { initialize, schedule, async flush() {
        await (running ?? Promise.resolve())
        if (stopped()) throw new Error('Editor closed')
        if (!latest || renderedFull) return
        queued = { values: latest, full: true }
        running = render().finally(() => { running = null })
        await running
    }, dispose: cleanup }
}
