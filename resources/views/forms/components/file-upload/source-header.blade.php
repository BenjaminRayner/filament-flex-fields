{{-- Source-specific illustrations share one responsive canvas and heading tracks. --}}
<div class="fff-flex-file-upload__source-header" data-fff-upload-header="{{ $source }}">
    @if ($source !== 'file' || ! $field->shouldUseCompactHeader())
    <div class="fff-flex-file-upload__source-art" aria-hidden="true">
        @if ($source === 'url')
            @include('filament-flex-fields::forms.components.file-upload.source-art-url')
        @elseif ($source === 'camera')
            @include('filament-flex-fields::forms.components.file-upload.source-art-camera')
        @else
            @include('filament-flex-fields::forms.components.file-upload.source-art-files')
        @endif
    </div>
    @endif
    <strong class="fff-flex-file-upload__source-title">{{ $heading }}</strong>
    <div class="fff-flex-file-upload__source-description">
        @if (count($uploadRequirements->formats()) <= 3)
            <p class="fff-flex-file-upload__source-formats">{{ $uploadRequirements->description() }}</p>
        @else
            <p class="fff-flex-file-upload__source-formats">{{ $uploadRequirements->formatDescription() }}</p>
            @if ($limits = $uploadRequirements->limits())
                <p class="fff-flex-file-upload__source-limits">
                    @foreach ($limits as $limit)
                        <span>{{ $limit }}@unless ($loop->last)<span class="fff-flex-file-upload__limit-separator" aria-hidden="true"> · </span>@endunless</span>
                    @endforeach
                </p>
            @endif
        @endif
    </div>
    @if (filled($field->getEmptyStateHint()))
        <p class="fff-flex-file-upload__source-note">{{ $field->getEmptyStateHint() }}</p>
    @endif
</div>
