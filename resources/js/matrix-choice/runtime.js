import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { waitForAssetActivation } from '../runtime/lifecycle/asset-activation.js'
import { watchOwnedAlpineValue } from '../runtime/alpine/owned-watch.js'
import { createMatrixSelection } from './model.js'
import { matrixDestination } from './keyboard.js'
import { createMatrixPresentation } from './presentation.js'

export function createMatrixRuntime(input) {
    let root,scope,model,presentation,ready,cancelRefresh,disposed=false
    return {
        state:input.state,disabled:input.disabled ?? false,
        render() {
            if (!presentation || disposed) return
            const next = model.prune(this.state,this.disabled)
            if (next !== this.state) this.state=next
            presentation.render(model.snapshot(this.state,this.disabled),this.disabled)
        },
        change(cell) {
            if (!cell || disposed) return
            this.state=model.change(this.state,cell.rowKey,cell.columnKey,this.disabled)
            this.render()
        },
        refresh() {
            if (disposed) return
            const config=JSON.parse(root.dataset.matrixConfig)
            model=createMatrixSelection(config)
            presentation=createMatrixPresentation(root)
            this.disabled=config.disabled ?? false
            this.render()
        },
        init() {
            if (ready || disposed) return ready
            root=this.$el
            scope=createResourceScope({onEffectError:() => this.$dispatch?.('fff-matrix-error',{reason:'effect_failed'})})
            ready=(async () => {
                if (!await waitForAssetActivation(root,{signal:scope.signal}) || disposed) return
                this.refresh()
                watchOwnedAlpineValue(scope,() => this.state,() => this.render())
                watchOwnedAlpineValue(scope,() => this.disabled,() => this.render())
                scope.listen(root,'click',event => {
                    const cell=presentation.fromEvent(event)
                    if (!cell) return
                    event.preventDefault();this.change(cell);presentation.focus(cell)
                })
                scope.listen(root,'keydown',event => {
                    const cell=presentation.fromEvent(event)
                    if (!cell || event.altKey || event.ctrlKey || event.metaKey) return
                    if (event.key === ' ' || event.key === 'Enter') {
                        event.preventDefault();if (!event.repeat) this.change(cell);return
                    }
                    if (!['Home','End','ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(event.key)) return
                    event.preventDefault()
                    presentation.focus(matrixDestination(presentation.cells,cell,event.key,root.matches(':dir(rtl)')))
                })
                const observer=new root.ownerDocument.defaultView.MutationObserver(() => {
                    if (cancelRefresh || disposed) return
                    cancelRefresh=scope.frame(() => {cancelRefresh=null;this.refresh()})
                })
                observer.observe(root,{childList:true,subtree:true,attributes:true,attributeFilter:['data-matrix-config']})
                scope.trackObserver(observer)
            })()
            return ready
        },
        destroy() { if (disposed) return;disposed=true;scope?.dispose();presentation=null;model=null },
    }
}
