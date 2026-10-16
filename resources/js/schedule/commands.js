import { copyDay, createDefaultSlot, createWorkSlotAfter, createBreakSlot, normalizeTime, slotIsOvernight, isBreakSlot } from '../support/schedule-validation.js'

/** Each edit publishes one new schedule; commands cannot add unknown days/slot keys. */
export function createScheduleCommands(session) {
    const active = field => !session.disposed && field.isInteractive
    const hasDay = (field, day) => field.days.includes(day)
    const indexIn = (field, day, index) => Number.isInteger(index) && index >= 0 && index < field.daySlots(day).length
    function replaceDay(field, day, value) {
        if (!active(field) || !hasDay(field, day)) return
        field.scheduleState = {...field.scheduleState, days:{...field.scheduleState.days,[day]:value}}
    }
    function editSlot(field,day,index,transform) {
        if (!active(field) || !hasDay(field,day) || !indexIn(field,day,index)) return
        const current=field.dayState(day)
        replaceDay(field,day,{...current,slots:current.slots.map((slot,i)=>i===index ? transform(slot) : slot)})
    }
    function append(field,day,create) {
        if (!field.canAddSlot(day) || session.disposed) return
        const current=field.dayState(day)
        replaceDay(field,day,{...current,slots:[...current.slots,create(current.slots.at(-1))]})
    }
    return {
        toggleDay(day) {
            if (!active(this) || !hasDay(this,day) || this.isDayLocked(day)) return
            const current=this.dayState(day), enabled=!current.enabled
            replaceDay(this,day,{...current,enabled,slots:enabled && current.slots.length===0 ? [createDefaultSlot()] : current.slots})
        },
        updateDay(day,value) { replaceDay(this,day,value) },
        addSlot(day) { append(this,day,last=>last?.to ? createWorkSlotAfter(last.to) : createDefaultSlot()) },
        addBreak(day) { append(this,day,last=>createBreakSlot(last?.to ?? '12:00')) },
        removeSlot(day,index) {
            if (!this.canRemoveSlot(day) || !indexIn(this,day,index)) return
            const current=this.dayState(day)
            replaceDay(this,day,{...current,slots:current.slots.filter((_,i)=>i!==index)})
        },
        updateSlotTime(day,index,key,value) {
            if (key!=='from' && key!=='to') return
            const time=normalizeTime(value)
            if (!time) return
            editSlot(this,day,index,slot=>({...slot,[key]:time}))
        },
        normalizeSlotTime(day,index,key) { this.updateSlotTime(day,index,key,this.daySlots(day)[index]?.[key]) },
        toggleSlotOvernight(day,index) {
            editSlot(this,day,index,slot=>{
                if (isBreakSlot(slot)) return slot
                const next={...slot}
                if (slotIsOvernight(slot)) delete next.overnight
                else next.overnight=true
                return next
            })
        },
        moveSlot(day,from,to) {
            if (!active(this) || !indexIn(this,day,from) || !indexIn(this,day,to) || from===to) return
            const current=this.dayState(day), slots=[...current.slots], [moved]=slots.splice(from,1)
            slots.splice(to,0,moved); replaceDay(this,day,{...current,slots})
        },
        moveSlotUp(day,index) { this.moveSlot(day,index,index-1) },
        moveSlotDown(day,index) { this.moveSlot(day,index,index+1) },
        updateTimezone(timezone) {
            if (!active(this) || !this.showTimezone || typeof timezone!=='string' || timezone.length>256 || !timezone.trim()) return
            this.scheduleState={...this.scheduleState,timezone:timezone.trim()}
        },
        copySourceToWeekdays() {
            if (!active(this) || !this.shouldShowCopyButton(this.copySourceDay)) return
            if (!window.confirm(this.labels.copyConfirm ?? 'Copy this schedule to workdays?')) return
            let state=this.scheduleState
            for (const day of this.weekdays) {
                if (day===this.copySourceDay || !hasDay(this,day)) continue
                const enabled=this.isDayEnabled(day)
                state=copyDay(state,this.copySourceDay,day)
                state={...state,days:{...state.days,[day]:{...state.days[day],enabled}}}
            }
            this.scheduleState=state
        },
    }
}
