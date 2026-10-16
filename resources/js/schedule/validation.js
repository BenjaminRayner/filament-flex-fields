import { validateDaySlots, slotsOverlap, slotHasInvalidRange, slotOverlapsAtIndex, normalizeTime, timeToMinutes, ALL_DAYS } from '../support/schedule-validation.js'

/** Cyclic week parity with PHP WeeklyScheduleIntervals, including Sunday→Monday. */
export function weeklyOverlap(state,days) {
    const segments=[]
    for (let index=0;index<ALL_DAYS.length;index++) {
        const day=ALL_DAYS[index], data=state?.days?.[day]
        if (!days.includes(day) || !data?.enabled) continue
        for (const slot of data.slots ?? []) {
            const from=normalizeTime(slot.from), to=normalizeTime(slot.to)
            if (!from || !to || slotHasInvalidRange(slot)) continue
            const start=index*1440+timeToMinutes(from), end=index*1440+timeToMinutes(to)+(slot.overnight ? 1440 : 0)
            segments.push({start,end:Math.min(end,10080),day})
            if (end>10080) segments.push({start:0,end:end-10080,day})
        }
    }
    segments.sort((a,b)=>a.start-b.start)
    for (let i=1;i<segments.length;i++) if (segments[i].start<segments[i-1].end) return [segments[i-1].day,segments[i].day]
    return null
}

export function createScheduleValidation() {
    return {
        dayHasOverlap(day) { return slotsOverlap(this.daySlots(day)) },
        slotHasOverlap(day,index) { return slotOverlapsAtIndex(this.daySlots(day),index) },
        slotIsInvalid(day,index) { const slot=this.daySlots(day)[index]; return Boolean(slot && (slotHasInvalidRange(slot) || this.slotHasOverlap(day,index))) },
        slotValidationErrorMessage(day,index) {
            const slot=this.daySlots(day)[index]
            if (!slot) return null
            return slotHasInvalidRange(slot) ? this.validationMessages.from_before_to ?? null : this.slotHasOverlap(day,index) ? this.validationMessages.overlap ?? null : null
        },
        dayValidationError(day) {
            if (!this.isDayEnabled(day)) return null
            return validateDaySlots(this.daySlots(day),{minSlots:this.minSlots,maxSlots:this.maxSlots,requireSlots:this.requireSlotsForEnabledDays})
        },
        dayValidationErrorMessage(day) {
            const code=this.dayValidationError(day)
            if (!code) return null
            return String(this.validationMessages[code] ?? code).replace(':count',String(code==='max_slots' ? this.maxSlots : this.minSlots))
        },
        firstClientValidationErrorMessage() {
            for (const day of this.days) {
                if (!this.isDayEnabled(day)) continue
                const message=this.dayValidationErrorMessage(day)
                if (message) return message
                for (let index=0;index<this.daySlots(day).length;index++) {
                    const slot=this.slotValidationErrorMessage(day,index)
                    if (slot) return slot
                }
            }
            return weeklyOverlap(this.scheduleState,this.days) ? this.validationMessages.overlap ?? 'overlap' : null
        },
        hasClientValidationErrors() { return this.firstClientValidationErrorMessage()!==null },
        syncClientValidationErrorsToLivewire() {
            const message=this.firstClientValidationErrorMessage()
            if (message && this.$wire && this.statePath) this.$wire.addError(this.statePath,message)
        },
    }
}
