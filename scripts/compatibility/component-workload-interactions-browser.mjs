import assert from 'node:assert/strict'
import fs from 'node:fs'
import {chromium,expect} from '@playwright/test'

const output=process.env.FFF_INTERACTION_OUTPUT ?? '.internal/performance-evidence-2026-10-21/component-workloads/interactions.json'
const browser=await chromium.launch({headless:true})
const context=await browser.newContext({storageState:'.auth/playground.json',ignoreHTTPSErrors:true})
const results=[]
const inventories={
    'date-time':['date','datetime','range','time','month','year'],
    catalogs:['country','phone','timezone','currency','icon','font'],
    color:['hex','rgb','hsl','rgba','palette','grid'],
    tags:['basic','suggestions','async','reorderable','limited','affixes'],
    textarea:['basic','multiline','autosize','fixed','maxlength','tools'],
    'rich-editor':['default','compact','multiline','json','list','long'],
}
try{
    for(const width of [1440,390])for(const [slug,variants] of Object.entries(inventories).filter(([slug])=>!process.env.FFF_INTERACTION_PAGES||process.env.FFF_INTERACTION_PAGES.split(',').includes(slug))){
        const page=await context.newPage(),errors=[],labelCalls=[]
        page.on('request',request=>{
            if(slug!=='tags'||request.method()!=='POST'||!request.url().includes('livewire'))return
            for(const component of request.postDataJSON()?.components??[])for(const call of component.calls??[])
                if(call.method==='callSchemaComponentMethod'&&['getOptionLabelsForJs','getOptionLabel'].includes(call.params?.[1]))labelCalls.push(call.params[0])
        })
        page.on('pageerror',error=>errors.push(error.message))
        const small=slug==='rich-editor'
        await page.setViewportSize({width,height:1000})
        const url=`https://wyachts-super-app.test/admin/field-performance/${slug}?_measure=1${small?'&per=1':''}`
        await page.goto(url,{waitUntil:'domcontentloaded'})
        await page.waitForFunction(()=>window.__fffLocalBenchmarkReceipt,null,{timeout:30000})
        assert.equal(await page.locator('[data-benchmark-field]').count(),small?6:300)
        for(const variant of variants)for(const index of small?[1]:[1,50]){
            const field=page.locator(`[data-benchmark-field="${variant}-${index}"]`)
            const row={width,slug,variant,index,fields:small?6:300}
            try{
                if(slug==='tags'){
                    const input=field.locator('input[role="combobox"]')
                    const value=`probe-${variant}-${index}`
                    await input.fill(value);await input.press('Enter')
                    await expect(field.locator('.fff-tags-field__tags-live')).toContainText(value)
                    row.edit=true
                    if(variant==='async'){
                        const start=performance.now()
                        await input.fill('tag-0999')
                        const option=page.getByRole('option',{name:'tag-09990',exact:true})
                        await expect(option).toBeVisible({timeout:15000})
                        row.asyncSearchMs=performance.now()-start
                        await option.click()
                        await expect(field.locator('.fff-tags-field__tags-live')).toContainText('tag-09990')
                        await page.keyboard.press('Escape')
                        row.asyncSelect=true
                    }
                }else if(slug==='textarea'){
                    const input=field.locator('textarea:not([aria-hidden])').first()
                    await input.fill('First measurement line.\nSecond measurement line.\nThird measurement line.')
                    await expect(input).toHaveValue(/Third measurement line/)
                    if(variant!=='fixed'){
                        const originalMinimum=await input.evaluate(el=>parseFloat(getComputedStyle(el).minHeight))
                        await input.fill(Array.from({length:20},(_,i)=>'Line '+i).join('\n'))
                        await expect.poll(()=>input.evaluate(el=>el.clientHeight)).toBeGreaterThan(100)
                        await expect.poll(()=>input.evaluate(el=>Math.abs(el.getBoundingClientRect().height-parseFloat(el.style.height)))).toBeLessThan(.1)
                        row.expandedHeight=await input.evaluate(el=>el.clientHeight)
                        row.minimumHeight=originalMinimum
                    }
                    await input.fill('Short note.')
                    await expect(input).toHaveValue('Short note.')
                    if(variant!=='fixed'){
                        await expect.poll(()=>input.evaluate(el=>parseFloat(el.style.height))).toBe(row.minimumHeight)
                        await expect.poll(()=>input.evaluate(el=>Math.abs(el.getBoundingClientRect().height-parseFloat(el.style.height)))).toBeLessThan(.1)
                        await expect.poll(()=>input.evaluate(el=>el.clientHeight)).toBe(row.minimumHeight)
                        row.shrunkHeight=await input.evaluate(el=>el.clientHeight)
                        assert.ok(row.shrunkHeight>=row.minimumHeight)
                    }
                    if(variant==='tools'){
                        await input.press('End')
                        await field.locator('.fff-flex-textarea__emoji-trigger').click()
                        const emojiPanel=page.locator('.fff-emoji-picker__panel:visible')
                        await expect(emojiPanel.locator('.fff-emoji-picker__host')).toHaveAttribute('aria-busy','false',{timeout:45000})
                        const emoji=emojiPanel.locator('emoji-picker .emoji-menu button.emoji:visible').first()
                        const unicode=await emoji.innerText()
                        await emoji.click()
                        await expect(input).toHaveValue('Short note.'+unicode)
                        await expect(field.locator('.fff-flex-textarea__counter')).toHaveText(`${Array.from('Short note.'+unicode).length}/500`)
                        row.emoji=true
                        await field.getByRole('button',{name:'Preview',exact:true}).click()
                        const modal=page.getByRole('dialog').filter({hasText:'This preview action uses the native Filament modal.'}).locator('.fi-modal-window')
                        await expect(modal).toBeVisible({timeout:15000})
                        await page.keyboard.press('Escape')
                        await expect(modal).not.toBeVisible({timeout:5000})
                        row.nativeAction=true
                    }
                    row.edit=true
                }else if(slug==='rich-editor'){
                    const editor=field.locator('.tiptap[contenteditable]')
                    await editor.fill('Editable performance example.')
                    await expect(editor).toHaveText('Editable performance example.')
                    row.edit=true
                }else{
                    const trigger=slug==='date-time'
                        ?field.locator('.fff-flex-text-input__shell').first()
                        :slug==='color'?field.locator('.fff-color-trigger')
                        :field.locator('[data-fff-open-trigger]').first()
                    await trigger.scrollIntoViewIfNeeded()
                    await trigger.evaluate(el=>el.addEventListener('click',()=>{window.__workloadClickAt=performance.now()},{once:true,capture:true}))
                    await trigger.click(slug==='date-time'?{position:{x:14,y:16}}:{})
                    const panel=page.locator('.fff-teleported-menu:visible, .fff-color-panel:visible, .fff-icon-picker__panel:visible').first()
                    await expect(panel).toBeVisible({timeout:15000})
                    await expect.poll(()=>panel.evaluate(el=>Number(getComputedStyle(el).opacity)),{timeout:15000}).toBeGreaterThan(.02)
                    row.firstVisibleMs=await page.evaluate(()=>performance.now()-window.__workloadClickAt)
                    if(slug==='color'&&['palette','grid'].includes(variant)){
                        const swatch=panel.locator('[data-fff-color-live] button').first()
                        await swatch.click();row.select=true
                    }
                    await page.keyboard.press('Escape')
                    // Some wrappers intentionally dismiss on outside click instead of Escape.
                    if(await panel.isVisible())await page.locator('h1').first().click()
                    await expect(panel).not.toBeVisible({timeout:5000})
                    row.dismiss=true
                }
                console.log(JSON.stringify(row))
            }catch(error){row.failure=error.message;process.exitCode=1;console.log(JSON.stringify(row));await page.keyboard.press('Escape').catch(()=>{})}
            results.push(row)
            fs.writeFileSync(output,JSON.stringify({browser:'Chromium',note:'Real DOM clicks and edits; all families use 300 owners except Rich Editor, whose six-field smoke does not resolve its 300-owner startup failure. Timing includes automation polling after actual click; descriptive, not frame-exact latency.',results},null,2))
        }
        if(slug==='tags'){
            assert.deepEqual(labelCalls,[],'Tags requested labels already represented by local tag strings')
            const row={width,slug,variant:'livewire-morph',fields:300}
            try{
                await page.locator('[data-benchmark-field="basic-1"] [x-fff-load-src]').evaluate(el=>{window.__ownedTagsProbe=el})
                await page.evaluate(async()=>{
                    const root=document.querySelector('[data-benchmark-form]').closest('[wire\\:id]')
                    await Livewire.find(root.getAttribute('wire:id')).$commit()
                })
                row.sameOwner=await page.locator('[data-benchmark-field="basic-1"] [x-fff-load-src]').evaluate(el=>el===window.__ownedTagsProbe)
                assert.equal(row.sameOwner,true,'State-only labels remounted an existing Tags owner')
                row.labelLookups=labelCalls.length
                assert.equal(row.labelLookups,0)
            }catch(error){row.failure=error.message;process.exitCode=1}
            results.push(row);console.log(JSON.stringify(row))
            fs.writeFileSync(output,JSON.stringify({browser:'Chromium',results},null,2))
        }
        assert.deepEqual(errors,[])
        await page.close()
    }
}finally{await browser.close()}
