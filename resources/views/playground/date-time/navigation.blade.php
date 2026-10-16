<nav class="fff-playground-layout-actions fff-playground-layout-actions--collection" aria-label="Date and time examples">
    <x-filament-flex-fields::assets component="flex-catalog-layout" consumer="date-gallery.navigation" :critical="true" />
    <h3 class="fff-playground-layout-actions__title">Related components</h3>
    <div class="fff-playground-layout-actions__list">
        @foreach ($links as $link)
            @include('filament-flex-fields::partials.playground.related-link', $link)
        @endforeach
    </div>
</nav>
