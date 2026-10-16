@foreach (app(\Bjanczak\FilamentFlexFields\Support\Assets\Delivery\AssetDeliveryContext::class)->attributes($deliveryUrl) as $deliveryAttribute => $deliveryValue)
{{ $deliveryAttribute }}="{{ $deliveryValue }}"
@endforeach
