import assert from 'node:assert/strict'
import schedule from '../../resources/js/components/schedule-field.js'
import { weeklyOverlap } from '../../resources/js/schedule/validation.js'
import { slotHasInvalidRange, validateDaySlots } from '../../resources/js/support/schedule-validation.js'

const seed={timezone:'UTC',days:{mon:{enabled:true,slots:[{from:'09:00',to:'17:00',type:'slot'}]},tue:{enabled:false,slots:[]},sun:{enabled:true,slots:[{from:'22:00',to:'06:00',type:'slot',overnight:true}]}}}
const options={state:seed,days:['mon','tue','sun'],lockedDays:['sun'],maxSlots:3,minSlots:1,validationMessages:{overlap:'Overlap',from_before_to:'Invalid range'},labels:{copyConfirm:'Copy?'}}
const first=schedule(options), second=schedule(options)
first.$el={closest:()=>null}; first.$nextTick=()=>{}
first.init(); first.init()
first.updateSlotTime('mon',0,'from','10:00')
assert.equal(first.daySlots('mon')[0].from,'10:00'); assert.equal(seed.days.mon.slots[0].from,'09:00')
first.updateSlotTime('unknown',0,'from','10:00'); assert.equal(first.scheduleState.days.unknown,undefined)
first.updateSlotTime('mon',0,'arbitrary','10:00'); assert.equal(first.daySlots('mon')[0].arbitrary,undefined)
first.toggleDay('sun'); assert.equal(first.isDayEnabled('sun'),true)
first.toggleDay('tue'); assert.equal(first.daySlots('tue').length,1)
first.addSlot('mon'); first.addBreak('mon'); first.addSlot('mon'); assert.equal(first.daySlots('mon').length,3)
first.moveSlotUp('mon',2); assert.equal(first.daySlots('mon')[1].type,'break')
first.removeSlot('mon',2); first.removeSlot('mon',1); first.removeSlot('mon',0); assert.equal(first.daySlots('mon').length,1)
assert.equal(first.isInteractive,true); first.destroy(); assert.equal(first.isInteractive,false)
const destroyed=first.scheduleState; first.toggleDay('mon'); first.updateTimezone('Europe/Warsaw'); assert.equal(first.scheduleState,destroyed)
assert.equal(second.daySlots('mon')[0].from,'09:00')
assert.equal(slotHasInvalidRange({from:'09:00',to:'17:00',overnight:true}),true)
assert.equal(validateDaySlots([{from:'09:00',to:'17:00',overnight:true}]),'from_before_to')
const cyclic={days:{sun:{enabled:true,slots:[{from:'22:00',to:'06:00',overnight:true}]},mon:{enabled:true,slots:[{from:'05:00',to:'08:00'}]}}}
assert.deepEqual(weeklyOverlap(cyclic,['mon','sun']),['sun','mon'])
cyclic.days.mon.slots[0].from='06:00'; assert.equal(weeklyOverlap(cyclic,['mon','sun']),null)
globalThis.window={confirm:()=>true}
const copy=schedule({...options,state:seed})
copy.copySourceToWeekdays(); assert.equal(copy.isDayEnabled('tue'),false); assert.equal(copy.daySlots('tue')[0].from,'09:00')
assert.notEqual(copy.daySlots('tue'),copy.daySlots('mon'))
const readonly=schedule({...options,readOnly:true}); readonly.toggleDay('mon'); assert.equal(readonly.scheduleState,seed)
console.log('PASS: immutable schedule commands, slot/day authority, limits, locked toggles, read-only/disposal/sibling isolation, copy without enabled-state changes and cyclic overnight PHP parity.')
