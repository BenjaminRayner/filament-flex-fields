import { prepareRenderedSelectPortal } from '../../configuration/rendered-portal.js'
import headlessComboboxAlpine from '../../../components/select-field/headless-combobox-alpine.js'
import {createFontOptionSource} from './source.js'
import {getFontPreviewManager} from '../../../fonts/manager.js'
import {bindFontOwner} from '../../../fonts/owner-lifecycle.js'
import {resolveFontMenuHeight} from '../../../fonts/menu-geometry.js'
import {visibleFontDemand} from '../../../fonts/visible-demand.js'

export function createFontSelect(configuration, root = null) {
    const previewSelectedFont = configuration.previewSelectedFont !== false
    let owner,manager,unbind,frame=null,intent=null,generation=0,active=false
    const lease = Symbol('font-select')
    let demanded = new Set()
    let demandSignature = null
    let optionPress = null
    const source = createFontOptionSource({request:(method,params)=>owner.callSchemaMethod(method,params),
        category:()=>owner.fontCategory,perPage:configuration.perPage,origin:globalThis.location?.origin})
    const seed=configuration.seed
    const base=headlessComboboxAlpine({state:configuration.state,initialState:seed?.value ?? null,
        options:seed ? [seed] : [],initialOptionLabel:seed?.label,
        menuLabel:configuration.menuLabel,componentKey:configuration.componentKey,livewireId:configuration.livewireId,menuDomId:configuration.menuDomId,
        dataSource:source,hasPaginatedSearchResults:true,optionsLimit:0,isPreloaded:true,
        searchable:true,clearable:configuration.clearable,disabled:configuration.disabled,
        searchDebounce:120,minSearchLength:0,placeholder:configuration.placeholder,
        keepSelectedOptionsInDropdown:true,canOptionLabelsWrap:false,
        // Keep search, category tabs and the anchor stable while result rows are
        // replaced by loading/empty states. Available viewport space still caps it.
        matchTriggerWidth:false,dropdownMinWidth:320,menuHeight:360,menuMaxHeight:360,dropdownAlign:'start',
        virtualizeThreshold:1,virtualRowHeight:38,
        searchPrompt:configuration.labels.search,searchingMessage:configuration.labels.loading,
        noSearchResultsMessage:configuration.labels.empty,noOptionsMessage:configuration.labels.empty,
        loadingMoreMessage:configuration.labels.more})
    if (root) base.prepareHeadlessPortal = prepareRenderedSelectPortal(root)
    const schedule = () => {
        if (!owner || owner._selectDisposed || frame !== null) return
        frame=requestAnimationFrame(()=>{frame=null;owner.publishFontDemand()})
    }
    return {...base,
        _fontBindings:false,_fontPreviewStates:{},fontCategory:'',fontTabs:configuration.tabs,fontSelection:seed,
        init() {
            owner=this;manager=getFontPreviewManager(this.$el.ownerDocument)
            const ready=base.init.call(this)
            if (this._fontBindings) return ready
            this._fontBindings=true
            unbind=bindFontOwner(this.$el,value=>{active=value;schedule()})
            this.$watch('state',()=>void this.refreshFontSelection())
            for (const key of ['comboboxOpen','menuReady','options','virtualScrollTick']) this.$watch(key,schedule)
            schedule()
            return ready
        },
        async refreshFontSelection() {
            const ticket=++generation,value=this.state
            if (typeof value !== 'string' || !value) {this.fontSelection=null;schedule();return}
            const record=this.optionRecord(value)
            if (record?.font) {this.fontSelection=record;schedule();return}
            this.fontSelection=null;schedule()
            try {
                await this.loadOptionalMixins()
                if (this._selectDisposed || ticket !== generation) return
                const result=await this.callSchemaMethod('getFontSelectionPreview',{value})
                if (ticket === generation && !this._selectDisposed && this.state === value) {this.fontSelection=result;schedule()}
            } catch { /* The label remains readable; the server still validates the ID. */ }
        },
        publishFontDemand() {
            if (!active || this._selectDisposed || !this.$el.isConnected) {
                manager.releaseOwner(lease);demandSignature=null;demanded.clear();this.syncFontPreviewStates();return
            }
            const resources=previewSelectedFont && this.fontSelection?.font ? [{...this.fontSelection.font,selected:true}] : []
            const list=this.$refs.headlessOptionsList
            const height=this._virtualViewportHeight ?? 0,offset=this._virtualScrollTop ?? 0
            if (this.comboboxOpen && this.menuReady && list && height > 0) {
                const rows=this._virtualFlatRows
                const window=this.resolveVirtualWindowForFlatRows(rows,offset)
                resources.push(...visibleFontDemand(rows,window.virtualItems,offset,height,this._virtualContentOffset ?? 0)
                    .filter(resource=>intent === null || demanded.has(resource.key)))
            }
            // The scroll owner publishes geometry. Equal demand must not repaint
            // all font styles and force another modal layout on the next frame.
            const signature=JSON.stringify(resources.map(resource=>[resource.key,!!resource.selected]))
            if (signature === demandSignature) return
            demandSignature=signature
            demanded=new Set(resources.map(resource=>resource.key))
            manager.replaceDemand(lease,resources,()=>{if (!this._selectDisposed && active) this.syncFontPreviewStates()})
            this.syncFontPreviewStates()
        },
        captureFontOptionPress(event,value) {
            if (event.isPrimary === false || event.button !== 0) return
            optionPress={element:event.currentTarget,value,pointerId:event.pointerId}
        },
        cancelFontOptionPress(event) {
            if (optionPress?.pointerId === event.pointerId) optionPress=null
        },
        selectPressedFontOption(event,value) {
            // A virtual slot can represent another font between down and up.
            // Pointer selection belongs to the font that was actually pressed.
            const pressed=optionPress
            optionPress=null
            this.toggleOption(event.detail > 0 && pressed?.element === event.currentTarget ? pressed.value : value)
        },
        shouldMeasureVirtualRowHeights() {return false},
        resolveVirtualRowHeight(row) {return row?.type === 'option' ? 38 : base.resolveVirtualRowHeight.call(this,row)},
        resolveAnchoredMenuMaxHeight(trigger,bounds,gap) {return resolveFontMenuHeight(trigger,bounds,this.menuMaxHeight,gap,this.position)},
        syncFontPreviewStates() {
            // A completed font invalidates only its own specimen, rather than
            // every style/skeleton binding in the virtual window.
            for (const key of demanded) {
                const next=manager.read(key),previous=this._fontPreviewStates[key]
                if (!previous || previous.status !== next.status || previous.alias !== next.alias || previous.error !== next.error) {
                    this._fontPreviewStates[key]=Object.freeze(next)
                }
            }
            for (const key of Object.keys(this._fontPreviewStates)) if (!demanded.has(key)) delete this._fontPreviewStates[key]
        },
        fontPreview(resource) {return resource ? this._fontPreviewStates[resource.key] ?? {status:'idle',alias:''} : {status:'idle',alias:''}},
        fontPreviewStyle(resource) {
            const preview=this.fontPreview(resource)
            return preview.status === 'ready' ? {fontFamily:`"${preview.alias}"`,fontWeight:resource.weight,fontStyle:resource.style} : {}
        },
        selectedFontPreviewStyle() {return previewSelectedFont ? this.fontPreviewStyle(this.fontSelection?.font) : {}},
        retryFontPreviews() {
            if (!active || this._selectDisposed) return
            const resources=[this.fontSelection?.font,...this._virtualFlatRows.map(row=>row.option?.font)].filter(Boolean)
            for (const resource of resources) manager.retry(resource.key)
        },
        hasFailedFontPreviews() {return Object.values(this._fontPreviewStates).some(preview=>preview.status === 'failed')},
        selectFontCategory(value) {
            if (this.disabled || this._selectDisposed || value === this.fontCategory || !this.fontTabs.some(tab=>tab.value === value)) return
            this.fontCategory=value;this.cancelRelationshipSearch();this.applyRemoteOptions([])
            if (this.$refs.headlessOptionsList) this.$refs.headlessOptionsList.scrollTop=0
            this._virtualScrollTop=0;this.scheduleAsyncSearch(this.comboboxQuery);schedule()
        },
        onHeadlessOptionsScroll(event) {
            base.onHeadlessOptionsScroll.call(this,event)
            // Release departed previews now. A brief intent window avoids starting
            // a new network flight at every intermediate fast-scroll frame.
            if (intent !== null) clearTimeout(intent)
            intent=setTimeout(()=>{intent=null;schedule()},60)
            schedule()
        },
        destroy() {
            optionPress=null;generation++;if (frame !== null) cancelAnimationFrame(frame)
            if (intent !== null) clearTimeout(intent)
            unbind?.();manager?.releaseOwner(lease);source.dispose();base.destroy.call(this);owner=null
        },
    }
}
