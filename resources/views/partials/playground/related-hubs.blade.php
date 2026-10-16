@if (count($hubs) > 0)
    <nav class="fff-playground-layout-actions" data-slot="playground-related-hubs" aria-labelledby="{{ $currentSlug }}-related-title">
        <h3 id="{{ $currentSlug }}-related-title" class="fff-playground-layout-actions__title">Related components</h3>
        <div class="fff-playground-layout-actions__list">
            @foreach ($hubs as $hub)
                @include('filament-flex-fields::partials.playground.related-link', [
                    'url' => $hub['url'],
                    'label' => $hub['label'],
                    'icon' => match ($hub['slug']) {
                            'translatable-fields' => 'hugeicons-translate',
                            'segment-tabs' => 'hugeicons-archive-02',
                            'item-card-group' => 'hugeicons-collections-bookmark',
                    },
                    'current' => false,
                ])
            @endforeach
        </div>
    </nav>
@endif
