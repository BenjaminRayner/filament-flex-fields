<div class="fff-schedule-field__slot-toolbar">
    @foreach (['addSlot' => \Bjanczak\FilamentFlexFields\Support\FlexIcon::Plus, 'addBreak' => \Bjanczak\FilamentFlexFields\Support\FlexIcon::Clock] as $command => $icon)
        <button type="button" class="fff-schedule-field__action-btn" @if ($static ?? false) disabled tabindex="-1" @else x-on:click="{{ $command }}(@js($day))" x-bind:disabled="!canAddSlot(@js($day))" @endif aria-label="{{ $labels[$command] }}"><x-filament::icon :icon="$icon" class="h-3.5 w-3.5" /><span>{{ $labels[$command] }}</span></button>
    @endforeach
</div>
