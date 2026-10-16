<span class="fff-flex-file-upload__paper-scene">
    @foreach ($uploadRequirements->icons('file') as $paper => $icon)
        <span class="fff-flex-file-upload__paper-card fff-flex-file-upload__paper-card--{{ ['serif', 'sans', 'bold'][$paper] }}">
            <span class="fff-flex-file-upload__paper-sheet"></span>
            <span class="fff-flex-file-upload__paper-fold"></span>
            <span class="fff-flex-file-upload__paper-glyph"><x-filament::icon :icon="$icon" /></span>
        </span>
    @endforeach
</span>
