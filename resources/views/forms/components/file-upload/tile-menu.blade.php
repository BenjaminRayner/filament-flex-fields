<div x-data="createFileMenu()" x-id="['fff-upload-actions']" class="fff-flex-file-upload__tile-menu"
    x-effect="if (!hasFileMenu(file)) close()" data-fff-no-sort>
    <button type="button" class="fff-flex-file-upload__action" x-ref="actionTrigger"
        x-bind:id="$id('fff-upload-actions') + '-trigger'" x-bind:disabled="!!file.leaving"
        aria-haspopup="menu" x-bind:aria-expanded="menuExpanded" x-bind:aria-controls="$id('fff-upload-actions')"
        x-on:click.stop="toggleMenu($event)" x-on:keydown.down.prevent.stop="toggleMenu($event)"
        x-on:keydown.up.prevent.stop="toggleMenu($event)"
        aria-label="{{ __('filament-flex-fields::default.file_upload.visual.more') }}"><x-filament::icon icon="hugeicons-more-horizontal" /></button>
    <template x-teleport="body">
    <div x-ref="actionMenu" x-bind:id="$id('fff-upload-actions')" role="menu" tabindex="-1"
        x-bind:aria-labelledby="$id('fff-upload-actions') + '-trigger'" class="fff-upload-menu-panel"
        x-on:keydown="menuKeydown($event)" x-on:click.stop data-fff-no-sort>
        <div class="fff-upload-menu-panel__items">
        <button role="menuitem" tabindex="-1" type="button" class="fff-upload-menu-panel__item" x-show="fileMenuHas(file, 'retry')" x-on:click="close(); fileAction(file.pondId, 'retry')">{{ __('filament-flex-fields::default.file_upload.visual.retry') }}</button>
        @if ($field->isPausable())
            <button role="menuitem" tabindex="-1" type="button" class="fff-upload-menu-panel__item" x-show="fileMenuHas(file, 'pause')" x-on:click="close(); fileAction(file.pondId, 'pause')">{{ __('filament-flex-fields::default.file_upload.visual.pause') }}</button>
            <button role="menuitem" tabindex="-1" type="button" class="fff-upload-menu-panel__item" x-show="fileMenuHas(file, 'resume')" x-on:click="close(); fileAction(file.pondId, 'resume')">{{ __('filament-flex-fields::default.file_upload.visual.resume') }}</button>
        @endif
        <button role="menuitem" tabindex="-1" type="button" class="fff-upload-menu-panel__item" x-show="fileMenuHas(file, 'process')" x-on:click="close(); fileAction(file.pondId, 'process')">{{ __('filament-flex-fields::default.file_upload.visual.upload') }}</button>
        <button role="menuitem" tabindex="-1" type="button" class="fff-upload-menu-panel__item" x-show="fileMenuHas(file, 'cancel')" x-on:click="close(); fileAction(file.pondId, 'cancel')">{{ __('filament-flex-fields::default.file_upload.visual.cancel') }}</button>
        @if ($field->hasFileViewer())
            <button role="menuitem" tabindex="-1" type="button" class="fff-upload-menu-panel__item" x-show="fileMenuHas(file, 'viewer')" x-on:click="close(); fileAction(file, 'viewer')"
                x-bind:disabled="viewerPendingId !== null">{{ __('filament-flex-fields::default.file_upload.viewer.open') }}</button>
        @endif
        @if ($field->hasFileMetadataEditor())
            <button role="menuitem" tabindex="-1" type="button" class="fff-upload-menu-panel__item" x-show="fileMenuHas(file, 'metadata')" x-on:click="close(); fileAction(file.pondId, 'metadata')" x-bind:disabled="isDisabled">{{ __('filament-flex-fields::default.file_upload.metadata.edit') }}</button>
        @endif
        @if ($isImageEditorExplicitlyEnabled && ! $isDisabled)
            <button role="menuitem" tabindex="-1" type="button" class="fff-upload-menu-panel__item" x-show="fileMenuHas(file, 'edit')" x-on:click="close(); fileAction(file.pondId, 'edit')" x-bind:disabled="isDisabled">{{ __('filament-flex-fields::default.file_upload.visual.edit') }}</button>
        @endif
        @if ($isOpenable())
            <a role="menuitem" tabindex="-1" class="fff-upload-menu-panel__item" target="_blank" rel="noopener noreferrer"
                x-show="fileMenuHas(file, 'open')" x-bind:href="file.openUrl" x-on:click="close()">{{ __('filament-flex-fields::default.file_upload.visual.open') }}</a>
        @endif
        @if ($isDownloadable())
            <a role="menuitem" tabindex="-1" class="fff-upload-menu-panel__item" download
                x-show="fileMenuHas(file, 'download')" x-bind:href="file.downloadUrl" x-on:click="close()">{{ __('filament-flex-fields::default.file_upload.visual.download') }}</a>
        @endif
        @if ($isDeletable())
            <button role="menuitem" tabindex="-1" type="button" class="fff-upload-menu-panel__item fff-upload-menu-panel__item--danger" x-show="fileMenuHas(file, 'remove')" x-on:click="close(); fileAction(file.pondId, 'remove')" x-bind:disabled="isDisabled">{{ __('filament-flex-fields::default.file_upload.visual.remove') }}</button>
        @endif
        </div>
    </div>
</template>
</div>
