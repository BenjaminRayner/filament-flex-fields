<div class="fff-price-range__histogram">
    <div class="fff-price-range__chart" data-price-chart aria-hidden="true">
        @foreach ($histogram['bars'] as $heights)
            @php
                $styles = ['--fff-price-bar-index: '.$loop->index, '--fff-price-bar-height-max: '.$heights[$histogram['count']].'%'];
                foreach ($heights as $count => $height) {
                    $styles[] = '--fff-price-bar-height-'.$count.': '.$height.'%';
                }
            @endphp
            <span class="fff-price-range__bar" style="{{ implode('; ', $styles) }}"></span>
        @endforeach
    </div>
</div>
