import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { normalizeScheduleState } from '../support/schedule-validation.js'

export function createScheduleLifecycle(session) {
    const scope=createResourceScope()
    return {
        init() {
            if (session.initialized || session.disposed) return
            session.initialized=true
            const normalized=normalizeScheduleState(this.scheduleState ?? this.initialState,this.days,this.showTimezone ? this.defaultTimezone : null)
            if (!this.showTimezone) delete normalized.timezone
            if (JSON.stringify(normalized)!==JSON.stringify(this.scheduleState)) this.scheduleState=normalized
            this.bindFormSubmitGuard()
            this.markDisplayReady()
        },
        bindFormSubmitGuard() {
            if (session.submitBound || session.disposed) return
            const form=this.$el.closest('form')
            if (!form) return
            session.submitBound=true
            scope.listen(form,'submit',event=>{
                if (!this.hasClientValidationErrors()) return
                event.preventDefault(); event.stopImmediatePropagation(); this.syncClientValidationErrorsToLivewire()
            },true)
        },
        markDisplayReady() {
            const root=this.$el
            let queued=false, releaseReady=()=>{}
            const reveal=()=>{
                if (queued || session.disposed || this.displayReady) return
                queued=true
                this.$nextTick(()=>{
                    queued=false
                    if (session.disposed || this.displayReady) return
                    // x-for creates Select children after the parent initializes.
                    // Retain the rendered values until their real init promises settle.
                    const controls=root.querySelectorAll('.fff-schedule-field__day-body-live .fff-select-control[x-data]')
                    if ([...controls].some(control=>control.dataset.fffAlpineReady!=='true')) return
                    this.displayReady=true
                    releaseReady()
                    const frame=requestAnimationFrame(()=>{release();if (!session.disposed) this.dayAnimationsEnabled=true})
                    const release=scope.defer(()=>cancelAnimationFrame(frame))
                })
            }
            releaseReady=scope.listen(root,'fff-load-ready',reveal)
            reveal()
        },
        destroy() { if (session.disposed) return; session.disposed=true; scope.dispose() },
    }
}
