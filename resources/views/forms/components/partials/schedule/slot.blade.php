@php
    $static = $static ?? false;
    $slot ??= [];
    $isBreak = ($slot['type'] ?? 'slot') === 'break';
@endphp
<div @class(['fff-schedule-field__slot', 'fff-schedule-field__slot--break' => $static && $isBreak])
    @unless ($static) x-bind:class="{ 'is-invalid': slotIsInvalid(@js($day), slotIndex), 'is-overlap': slotHasOverlap(@js($day), slotIndex), 'is-overnight': isSlotOvernight(slot), 'fff-schedule-field__slot--break': isBreakSlot(slot) }" @endunless>
    <span class="fff-schedule-field__slot-label">
        <span @class(['fff-schedule-field__slot-label-icon', 'fff-schedule-field__slot-label-icon--break' => $static && $isBreak, 'fff-schedule-field__slot-label-icon--slot' => $static && !$isBreak])
            @unless ($static) x-bind:class="isBreakSlot(slot) ? 'fff-schedule-field__slot-label-icon--break' : 'fff-schedule-field__slot-label-icon--slot'" @endunless>
            <span @unless ($static) x-show="!isBreakSlot(slot)" @else @if ($isBreak) hidden @endif @endunless><x-filament::icon :icon="\Bjanczak\FilamentFlexFields\Support\FlexIcon::Briefcase" class="fff-schedule-field__slot-icon" /></span>
            <span @unless ($static) x-show="isBreakSlot(slot)" @else @unless ($isBreak) hidden @endunless @endunless><x-filament::icon :icon="\Bjanczak\FilamentFlexFields\Support\FlexIcon::Clock" class="fff-schedule-field__slot-icon" /></span>
        </span>
        <span class="fff-schedule-field__slot-label-text" @unless ($static) x-text="slotEntryLabel(@js($day), slotIndex)" @endunless>{{ $slotCaption ?? '' }}</span>
    </span>
    <div class="fff-schedule-field__time-fields">
        @foreach (['from', 'to'] as $timePart)
            @php
                $timeStateExpression = "(daySlots('{$day}')[slotIndex] ?? {}).{$timePart} ?? ''";
                $timeChangeExpression = "values => updateSlotTime('{$day}', slotIndex, '{$timePart}', values[0])";
            @endphp
            <div class="fff-schedule-field__time-group">
                <label class="fff-schedule-field__time-label">{{ $labels[$timePart] }}</label>
                <x-filament-flex-fields::select-control :options="$timeOptions" :static="$static" :initial-value="$slot[$timePart] ?? ''"
                    :state-expression="$timeStateExpression"
                    :on-change="$timeChangeExpression"
                    disabled-expression="!isInteractive" :label="$labels[$timePart]" :size="$size" :variant="$variant" />
            </div>
        @endforeach
    </div>
    <div class="fff-schedule-field__slot-meta" @unless ($static) x-show="slotShowsOvernightToggle(slot)" @else @if ($isBreak) hidden @endif @endunless>
        <button type="button" @class(['fff-schedule-field__overnight-btn', 'is-active' => $static && !empty($slot['overnight'])])
            @if ($static) disabled tabindex="-1" @else x-bind:class="{ 'is-active': isSlotOvernight(slot) }" x-on:click="toggleSlotOvernight(@js($day), slotIndex)" x-bind:disabled="!isInteractive" x-bind:aria-pressed="isSlotOvernight(slot)" @endif
            aria-label="{{ $labels['overnight'] }}">
            <x-filament::icon :icon="\Bjanczak\FilamentFlexFields\Support\FlexIcon::Moon" class="h-3.5 w-3.5" /><span>{{ $labels['overnight'] }}</span>
        </button>
    </div>
    @unless ($static)
        <div class="fff-schedule-field__slot-actions" x-show="canRemoveSlot(@js($day)) || canMoveSlotUp(@js($day), slotIndex) || canMoveSlotDown(@js($day), slotIndex)">
            @foreach (['moveSlotUp' => 'hugeicons-arrow-up-01', 'moveSlotDown' => 'hugeicons-arrow-down-01', 'removeSlot' => \Bjanczak\FilamentFlexFields\Support\FlexIcon::TrashBin] as $command => $icon)
                @php $show = $command === 'removeSlot' ? "canRemoveSlot('{$day}')" : ($command === 'moveSlotUp' ? "canMoveSlotUp('{$day}', slotIndex)" : "canMoveSlotDown('{$day}', slotIndex)"); @endphp
                <button type="button" class="fff-schedule-field__icon-btn" x-show="{{ $show }}" x-on:click="{{ $command }}(@js($day), slotIndex)" x-bind:disabled="!isInteractive" aria-label="{{ $labels[$command] }}"><x-filament::icon :icon="$icon" class="h-4 w-4" /></button>
            @endforeach
        </div>
    @endunless
</div>
