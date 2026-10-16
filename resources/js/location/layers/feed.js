import { createResourceScope } from '../../runtime/lifecycle/resource-scope.js'

/** One request at a time, bounded payload, cancellable activity and capped exponential backoff. */
export function createLayerFeed({endpoint,interval,win,onData,onStatus,fetcher=fetch}) {
    const url=new URL(endpoint,win.location.href)
    if(url.origin!==win.location.origin||url.username||url.password||url.hash)throw new Error('Live map endpoint must be same-origin.')
    const scope=createResourceScope()
    let active=false,controller=null,timer=null,revision=0,failures=0
    const stop=()=>{revision++;controller?.abort();controller=null;if(timer!==null)win.clearTimeout(timer);timer=null}
    const request=async()=>{
        if(!active||scope.disposed)return
        const current=++revision
        const requestController=new AbortController();controller=requestController
        const timeout=win.setTimeout(()=>requestController.abort(),15000)
        onStatus('connecting')
        try {
            const response=await fetcher(url.href,{signal:requestController.signal,credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store',redirect:'error'})
            if(!response.ok)throw new Error('Live map feed failed.')
            if(Number(response.headers.get('content-length'))>4194304)throw new Error('Live map response exceeds its byte budget.')
            const reader=response.body?.getReader(),chunks=[];let size=0,text
            if(reader){try{for(;;){const part=await reader.read();if(part.done)break;size+=part.value.byteLength;if(size>4194304){await reader.cancel();throw new Error('Live map response exceeds its byte budget.')}chunks.push(part.value)}}finally{reader.releaseLock()}
                const buffer=new Uint8Array(size);let offset=0;for(const chunk of chunks){buffer.set(chunk,offset);offset+=chunk.length}text=new TextDecoder('utf-8',{fatal:true}).decode(buffer)
            }else{text=await response.text();if(new TextEncoder().encode(text).length>4194304)throw new Error('Live map response exceeds its byte budget.')}
            if(current!==revision||!active||scope.disposed)return
            onData(JSON.parse(text));failures=0;onStatus('live')
        }catch(error){if(current===revision&&active&&!scope.disposed){failures++;onStatus('retrying')}}
        finally{win.clearTimeout(timeout);if(current===revision){controller=null;if(active&&!scope.disposed)timer=win.setTimeout(request,Math.min(300000,interval*2**Math.min(failures,6)))}}
    }
    scope.defer(stop)
    return {
        active(value){if(active===value)return;active=value;stop();if(active)void request();else onStatus('paused')},
        retry(){if(!active||scope.disposed)return;failures=0;stop();void request()},
        dispose(){active=false;scope.dispose()},
    }
}
