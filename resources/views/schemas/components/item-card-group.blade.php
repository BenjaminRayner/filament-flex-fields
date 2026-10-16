@php
    $group = $getItemCardGroupRenderState();
    $groupId = $getId();
    $groupAttributes = $attributes->merge([
        'id' => $groupId,
        'role' => 'group',
    ], escape: false)->merge([
        'aria-label' => filled($group->heading) ? \Bjanczak\FilamentFlexFields\Support\ItemCard\Presentation\PlainText::from($group->heading) : null,
    ])->merge($getExtraAttributes(), escape: false)->class($group->rootClasses());
@endphp
@include('filament-flex-fields::partials.load-stylesheet', [
    'component' => 'item-card-group',
    'assetSchemaComponent' => $schemaComponent,
    'livewireKey' => $getLivewireKey(),
])
<div {{ $groupAttributes }} data-slot="item-card-group">
    @include('filament-flex-fields::schemas.components.item-card-group.header')
    @if ($group->outsideHeader)
        <div @class($group->surfaceClasses()) data-slot="item-card-group-surface">
            @include('filament-flex-fields::schemas.components.item-card-group.content')
        </div>
    @else
        @include('filament-flex-fields::schemas.components.item-card-group.content')
    @endif
</div>
