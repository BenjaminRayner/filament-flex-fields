/** Each map generation owns its preload; browser HTTP caching shares the bounded resource. */
export async function loadTrackMarkerImages(configuration, document, scope) {
    const urls=[...new Set((configuration.experience?.layers??[]).map(layer=>layer.markerImage).filter(Boolean))]
    await Promise.all(urls.map(url=>new Promise((resolve,reject)=>{
        const image=document.createElement('img')
        let settled=false
        const finish=error=>{
            if(settled)return
            settled=true
            clearTimeout(deadline)
            image.onload=null
            image.onerror=null
            if(error){image.removeAttribute('src');reject(error)}else resolve()
        }
        const deadline=setTimeout(()=>finish(new Error('Track marker image failed to load.')),20000)
        scope.defer(()=>{finish(new Error('Map image owner was disposed.'));image.removeAttribute('src')})
        image.onload=()=>{if(typeof image.decode==='function')image.decode().then(()=>finish(),finish);else finish()}
        image.onerror=()=>finish(new Error('Track marker image failed to load.'))
        image.src=url
    })))
}
