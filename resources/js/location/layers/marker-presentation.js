export function isVehicleMarker(variant) {
    return variant==='car'||variant==='scooter'
}

/** Raster presentation is component-owned, cached by the browser and predecoded before map reveal. */
export function markerPresentation(document, element, variant, imageUrl) {
    element.classList.toggle(`is-${variant}`,true)
    if(!isVehicleMarker(variant))return
    const image=document.createElement('img')
    image.className='fff-location__vehicle-image'
    image.src=imageUrl
    image.alt=''
    image.width=40
    image.height=40
    image.draggable=false
    image.setAttribute('aria-hidden','true')
    element.append(image)
}
