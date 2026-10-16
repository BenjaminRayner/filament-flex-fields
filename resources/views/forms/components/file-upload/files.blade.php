{{-- Same row template for SSR and browser state, with matched dimensions. --}}
<div class="fff-flex-file-upload__files" x-show="!presentationMounted && files.length" @if (! $initialFiles) style="display: none;" @endif>
    @foreach ($initialFiles as $file)
        @include('filament-flex-fields::forms.components.file-upload.row', ['client' => false])
    @endforeach
</div>
{{-- The mounted owner updates these rows from FilePond; server morphs must not re-cloak them. --}}
<div class="fff-flex-file-upload__files" wire:ignore x-show="presentationMounted && files.length" x-cloak>
    <template x-for="file in files" :key="file.id">
        @include('filament-flex-fields::forms.components.file-upload.row', ['client' => true])
    </template>
</div>
