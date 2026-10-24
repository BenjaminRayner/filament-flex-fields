import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import {chromium} from '@playwright/test'

// Local synthetic fixtures, real production assets, no customer data or server mutation.
const base=process.env.FFF_BENCHMARK_ORIGIN ?? 'https://wyachts-super-app.test'
assert.ok(['wyachts-super-app.test','localhost','127.0.0.1'].includes(new URL(base).hostname),'Local diagnostics only')
const output=path.resolve(process.env.FFF_BENCHMARK_OUTPUT ?? '.internal/performance-evidence-2026-10-21/component-workloads/browser.json')
const slugs=(process.env.FFF_BENCHMARK_PAGES ?? 'textarea,date-time,catalogs,color,tags,rich-editor,native-textarea,native-color,native-rich-editor').split(',')
const rounds=Number(process.env.FFF_BENCHMARK_ROUNDS ?? 3)
const width=Number(process.env.FFF_BENCHMARK_WIDTH ?? 1440)
const per=process.env.FFF_BENCHMARK_PER
const results=[]
fs.mkdirSync(path.dirname(output),{recursive:true})
const browser=await chromium.launch({headless:true})
const context=await browser.newContext({storageState:path.resolve('.auth/playground.json'),ignoreHTTPSErrors:true,viewport:{width,height:1000}})
await context.addInitScript(()=>{
    window.__workloadLongTasks=[]
    new PerformanceObserver(list=>{
        window.__workloadLongTasks.push(...list.getEntries().map(entry=>({start:entry.startTime,duration:entry.duration})))
    }).observe({type:'longtask',buffered:true})
})
try {
    for(let round=0;round<rounds;round++){
        // Alternate order to reduce systematic warming bias across native/FLEX.
        for(const slug of round%2 ? [...slugs].reverse() : slugs){
            const page=await context.newPage(),errors=[]
            page.on('pageerror',e=>errors.push(e.message))
            const row={slug,round,width,errors}
            const cdp=await context.newCDPSession(page)
            await cdp.send('Performance.enable')
            try{
                const url=new URL(`/admin/field-performance/${slug}`,base)
                url.searchParams.set('_measure','1')
                if(per)url.searchParams.set('per',per)
                const response=await page.goto(url.href,{waitUntil:'domcontentloaded',timeout:45000})
                row.status=response.status()
                console.log(JSON.stringify({slug,round,stage:'document',status:row.status}))
                assert.equal(row.status,200)
                await page.waitForFunction(()=>window.__fffLocalBenchmarkReceipt,null,{timeout:30000})
                row.receipt=await page.evaluate(()=>window.__fffLocalBenchmarkReceipt)
                assert.equal(row.receipt.fields,per?Number(per)*6:300)
                assert.equal(row.receipt.pending,0)
                row.startup=await page.evaluate(()=>({
                    longTasks:window.__workloadLongTasks,
                    resources:performance.getEntriesByType('resource').filter(e=>e.initiatorType==='script'||e.initiatorType==='link').map(e=>({name:new URL(e.name).pathname,bytes:e.decodedBodySize,duration:e.duration})),
                }))
                row.metrics=(await cdp.send('Performance.getMetrics')).metrics
                assert.deepEqual(errors,[])
                console.log(JSON.stringify({slug,round,width,...row.receipt}))
            }catch(error){
                row.failure=error.message
                row.diagnostic=await Promise.race([page.evaluate(()=>({fields:document.querySelectorAll('[data-benchmark-field]').length,body:document.querySelector('[data-benchmark-form]')?null:document.title,pending:[...document.querySelectorAll('[x-fff-load-src]')].filter(el=>el.getClientRects().length&&!el.hasAttribute('data-fff-alpine-ready')).map(el=>el.getAttribute('x-fff-load-src').split('/').pop()),editors:document.querySelectorAll('.tiptap').length})).catch(()=>null),new Promise(resolve=>setTimeout(()=>resolve({unresponsive:true}),2000))])
                console.log(JSON.stringify({slug,round,failure:row.failure,diagnostic:row.diagnostic,errors}))
                process.exitCode=1
            }finally{
                results.push(row)
                fs.writeFileSync(output,JSON.stringify({format:1,createdAt:new Date().toISOString(),browser:'Chromium',transport:'real Herd HTTPS; local diagnostic receipt; browser context HTTP cache shared; new page per navigation',results},null,2))
                await page.close()
            }
        }
    }
}finally{await browser.close()}
