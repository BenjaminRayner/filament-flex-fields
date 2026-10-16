@if ($canCreate())
<div class="fff-todo-list-field__create-dock" x-ref="createDock" @focusout="onCreateDockFocusOut($event)">
    <div class="fff-todo-list-field__row fff-todo-list-field__row--create" :class="{'is-creating': creating, 'is-create-with-description':createWithDescription, 'is-create-busy':createBusy, 'is-create-disabled':!canAddMore()}" :aria-busy="createBusy" tabindex="0" @click="onCreateRowClick($event)" @keydown.enter.prevent="!creating && startCreate()">
        <button type="button" class="fff-todo-list-field__create-icon" aria-label="{{ $getCreateLabel() }}" @mousedown="onCreateIconMouseDown($event)" @click.stop="onCreateIconClick($event)"><x-filament::icon :icon="$getPlusIcon()" class="fff-todo-list-field__plus" /></button>
        <div class="fff-todo-list-field__content">
            <span class="fff-todo-list-field__label" x-show="!creating">{{ $getCreateLabel() }}</span>
            <div class="fff-todo-list-field__create-fields" x-show="creating" style="display:none">
                <input class="fff-todo-list-field__create-input" x-ref="createInput" x-model="createDraft" :disabled="createBusy" :placeholder="createPlaceholder" :aria-label="createPlaceholder" maxlength="500" @click.stop @keydown.enter.stop.prevent="onCreateLabelEnter()" @keydown.escape.stop.prevent="cancelCreate()" />
                @if ($canCreateWithDescription()) <input class="fff-todo-list-field__create-description" x-ref="createDescriptionInput" x-model="createDescriptionDraft" :disabled="createBusy" :placeholder="createDescriptionPlaceholder" :aria-label="createDescriptionPlaceholder" maxlength="2000" @click.stop @keydown.enter.stop.prevent="confirmCreate()" @keydown.escape.stop.prevent="cancelCreate()" /> @endif
            </div>
        </div>
    </div>
</div>
@endif
