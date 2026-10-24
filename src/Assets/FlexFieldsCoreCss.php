<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Assets;

use Bjanczak\FilamentFlexFields\Support\Assets\Delivery\AssetDeliveryContext;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\FirstPaintScripts;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\RuntimeEmission;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\RuntimeRenderContext;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/** Preserve native Filament publication while gating the document-level CSS tag. */
final class FlexFieldsCoreCss extends FlexFieldsCss
{
    public function getHtml(): Htmlable
    {
        if (! app(RuntimeRenderContext::class)->claim(RuntimeEmission::CoreStyles)) {
            return new HtmlString('');
        }
        $html = parent::getHtml()->toHtml();
        $attributes = app(AssetDeliveryContext::class)->attributes($this->getHref());
        $serialized = '';
        foreach ($attributes as $name => $value) {
            $serialized .= ' '.$name.'="'.e($value).'"';
        }

        $html = str_replace('<link', '<link'.$serialized, $html);
        $script = app(FirstPaintScripts::class)->inline('readiness');
        if ($script !== '') {
            $nonce = app(AssetDeliveryContext::class)->nonce();
            $html .= '<script'.($nonce !== null ? ' nonce="'.e($nonce).'"' : '').'>'.$script.'</script>';
        }

        return new HtmlString($html);
    }
}
