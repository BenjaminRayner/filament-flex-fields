import { WEEKDAYS } from '../support/schedule-validation.js'
import { mergeAlpineComponentData } from '../support/merge-alpine-component-data.js'
import { createScheduleCommands } from '../schedule/commands.js'
import { createSchedulePresentation } from '../schedule/presentation.js'
import { createScheduleValidation } from '../schedule/validation.js'
import { createScheduleLifecycle } from '../schedule/lifecycle.js'

/** Weekly editor has no dropdown implementation; actual Select children own all menus. */
export default function scheduleFieldFormComponent({state,statePath,disabled=false,readOnly=false,days=[],dayLabels={},weekdays=WEEKDAYS,
    timeStep=5,minSlots=1,maxSlots=10,lockedDays=[],requireSlotsForEnabledDays=true,validationMessages={},
    allowCopyToWeekdays=true,copySourceDay='mon',showTimezone=true,defaultTimezone='UTC',labels={},initialState=null}) {
    const session={initialized:false,disposed:false,submitBound:false}
    return mergeAlpineComponentData(
        {scheduleState:state,statePath,disabled,readOnly,days,dayLabels,weekdays,timeStep,minSlots,maxSlots,lockedDays,
            requireSlotsForEnabledDays,validationMessages,allowCopyToWeekdays,copySourceDay,showTimezone,defaultTimezone,labels,initialState,
            displayReady:false,dayAnimationsEnabled:false},
        createSchedulePresentation(session),createScheduleCommands(session),createScheduleValidation(),createScheduleLifecycle(session),
    )
}
