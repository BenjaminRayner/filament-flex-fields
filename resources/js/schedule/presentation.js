import { createEmptyDay, isBreakSlot, slotIsOvernight, canRemoveDaySlot } from '../support/schedule-validation.js'

export function createSchedulePresentation(session) {
    return {
        get isInteractive() { return !session.disposed && !this.disabled && !this.readOnly },
        dayState(day) { return this.scheduleState?.days?.[day] ?? createEmptyDay() },
        isDayEnabled(day) { return Boolean(this.dayState(day).enabled) },
        isDayLocked(day) { return this.lockedDays.includes(day) },
        daySlots(day) { const slots=this.dayState(day).slots; return Array.isArray(slots) ? slots : [] },
        isBreakSlot, isSlotOvernight:slotIsOvernight,
        slotShowsOvernightToggle(slot) { return !isBreakSlot(slot) },
        slotEntryLabel(day,index) {
            const slots=this.daySlots(day), slot=slots[index]
            if (!slot) return ''
            if (isBreakSlot(slot)) return this.labels.break ?? 'Break'
            let number=0
            for (let i=0;i<=index;i++) if (!isBreakSlot(slots[i])) number++
            return `${this.labels.slot ?? 'Slot'} ${number}`
        },
        shouldShowCopyButton(day) { return this.allowCopyToWeekdays && day===this.copySourceDay && this.days.includes(day) && this.weekdays.some(value=>value!==day && this.days.includes(value)) },
        canAddSlot(day) { return this.isInteractive && this.days.includes(day) && this.isDayEnabled(day) && this.daySlots(day).length<this.maxSlots },
        canRemoveSlot(day) { return this.days.includes(day) && canRemoveDaySlot({slotCount:this.daySlots(day).length,minSlots:this.minSlots,isEnabled:this.isDayEnabled(day),isInteractive:this.isInteractive}) },
        canMoveSlotUp(day,index) { return this.isInteractive && Number.isInteger(index) && index>0 && index<this.daySlots(day).length },
        canMoveSlotDown(day,index) { return this.isInteractive && Number.isInteger(index) && index>=0 && index<this.daySlots(day).length-1 },
    }
}
