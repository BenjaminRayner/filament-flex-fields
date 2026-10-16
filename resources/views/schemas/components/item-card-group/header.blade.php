@if (filled($group->heading) || filled($group->description))
    <div class="item-card-group__header" data-slot="item-card-group-header">
        @if (filled($group->heading))
            <h3 class="item-card-group__title" data-slot="item-card-group-title">{{ $group->heading }}</h3>
        @endif
        @if (filled($group->description))
            <p class="item-card-group__description" data-slot="item-card-group-description">{{ $group->description }}</p>
        @endif
    </div>
@endif
