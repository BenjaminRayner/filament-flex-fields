/** Native file keys and PHP callbacks remain authoritative; browser labels/URLs are never submitted. */
export async function activateUploadedFile({vm,file,config,owner,pond,requests,navigate = url => globalThis.location.assign(url)}) {
    const id=file?.pondId
    if (!config.hasUploadedFileClick || config.isDisabled || owner.disposed || typeof id !== 'string'
        || !file.complete || file.failed || file.leaving || requests.has(id)) return
    requests.add(id)
    vm.fileClickPending={...vm.fileClickPending,[id]:true}
    try {
        const target=await pond.whenReady()
        owner.assertActive()
        const native=target.pond.getFiles().find(item=>item.id===id)
        if (!native || !vm.files.some(item=>item.pondId===id && item.complete && !item.failed && !item.leaving)) return
        const rawKey=native.origin===3 ? target.component.uploadedFileIndex?.[native.source] : native.serverId
        const key=typeof rawKey==='string' ? rawKey : Number.isSafeInteger(rawKey) && rawKey>=0 ? String(rawKey) : null
        if (typeof key !== 'string' || !key) throw new Error('The uploaded file is unavailable.')
        const result=await vm.$wire.callSchemaComponentMethod(config.schemaComponentKey,'activateUploadedFile',{fileKey:key})
        if (owner.disposed) return
        if (result !== null && result !== undefined) {
            if (typeof result !== 'string' || result.length > 2048 || result.includes('\\') || /[\x00-\x20\x7f]/.test(result)) throw new Error('Invalid file activation URL.')
            const url=new URL(result,globalThis.location?.href)
            if (!['http:','https:'].includes(url.protocol) || url.username || url.password) throw new Error('Invalid file activation URL.')
            navigate(url.href)
        }
    } catch(error) {
        if (!owner.disposed) vm.actionError=vm.sourceError(error,config.labels?.actionFailed ?? 'Unable to open this file.')
    } finally {
        requests.delete(id)
        if (!owner.disposed) {const next={...vm.fileClickPending};delete next[id];vm.fileClickPending=next}
    }
}

/** Queued, uploading and failed rows reserve slots until removed; single files retain replacement. */
export function canAddUploadFiles(config,files) {
    if (config.isDisabled) return false
    if (!config.isMultiple || config.maxFiles == null) return true
    return files.filter(file=>!file.leaving).length < config.maxFiles
}
