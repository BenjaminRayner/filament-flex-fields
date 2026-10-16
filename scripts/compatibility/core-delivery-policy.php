<?php

declare(strict_types=1);

require __DIR__.'/core-framework-host.php';

use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
use Bjanczak\FilamentFlexFields\Support\Assets\Delivery\AssetDeliveryContext;
use Bjanczak\FilamentFlexFields\Support\Assets\Delivery\AssetDeliveryPolicy;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\RuntimeRenderContext;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

function deliveryCheck(bool $result, string $message): void
{
    if (! $result) {
        throw new RuntimeException($message);
    }
}
function deliveryReject(Closure $work): void
{
    try {
        $work();
    } catch (InvalidArgumentException $error) {
        return;
    }
    throw new RuntimeException('Malformed delivery metadata was accepted.');
}
$hash = 'sha256-'.base64_encode(hash('sha256', 'verified', true));
$callerOrigin = 'https://cdn.example.test';
$callerDigest = $hash;
$detached = new AssetDeliveryPolicy([&$callerOrigin], integrity: ['/verified.css' => &$callerDigest]);
$callerOrigin = 'https://forbidden.example.test';
$callerDigest = 'sha256-'.base64_encode(hash('sha256', 'replaced', true));
deliveryCheck($detached->allowedOrigins === ['https://cdn.example.test'] && $detached->integrity['/verified.css'] === $hash, 'Caller PHP references changed admitted delivery authority.');
$policy = new AssetDeliveryPolicy(['https://cdn.example.test'], 'anonymous', 'no-referrer', ['/verified.css?signature=kept' => $hash]);
deliveryCheck($policy->attributes('https://app.example.test/verified.css?signature=kept', 'https://app.example.test')['integrity'] === $hash, 'Same-origin path SRI failed.');
deliveryCheck(! isset($policy->attributes('/verified.css?signature=other', 'https://app.example.test')['integrity']), 'Signed query identity was stripped.');
$policy->assertUrl('https://cdn.example.test/styles.css', 'https://app.example.test');
deliveryReject(fn () => $policy->assertUrl('https://cdn.example.test.evil.test/styles.css', 'https://app.example.test'));
deliveryReject(fn () => $policy->assertUrl('https://cdn.example.test:8443/styles.css', 'https://app.example.test'));
foreach (['//evil.test/a', 'https://cdn.example.test/x"onclick="unsafe', 'javascript:alert(1)', '/path#fragment', 'https://user:pass@cdn.example.test/a', '/a\\b', "/a\nb"] as $url) {
    deliveryReject(fn () => $policy->assertUrl($url, 'https://app.example.test'));
}
foreach (['https://cdn.example.test/path', 'https://cdn.example.test/', 'https://CDN.example.test', 'https://cdn.example.test:443', 'https://*.example.test'] as $origin) {
    deliveryReject(fn () => new AssetDeliveryPolicy([$origin]));
}
deliveryReject(fn () => new AssetDeliveryPolicy(crossorigin: 'use-credentials'));
deliveryReject(fn () => new AssetDeliveryPolicy(referrerPolicy: 'invented'));
deliveryReject(fn () => new AssetDeliveryPolicy(integrity: ['/x' => 'sha256-'.str_repeat('A', 43).'!']));
deliveryReject(fn () => AssetDeliveryPolicy::fromArray(['unknown' => true]));
deliveryReject(fn () => AssetDeliveryPolicy::fromArray(['integrity' => false]));
deliveryCheck((new AssetDeliveryPolicy([]))->json() === (new AssetDeliveryPolicy([]))->json(), 'Projection is not deterministic.');
deliveryReject(fn () => (new AssetDeliveryPolicy(requireIntegrity: true))->attributes('/unsigned.css', 'https://app.example.test'));
deliveryReject(fn () => AssetDeliveryPolicy::fromArray(['require_integrity' => 'yes']));
echo "PASS: bounded typed policy rejects malformed origins, credentials, hashes and unknown configuration; exact query identity preserved.\n";

$app['config']->set('filament-flex-fields.assets.delivery', ['allowed_origins' => [], 'crossorigin' => 'anonymous', 'referrer_policy' => 'no-referrer', 'integrity' => []]);
request()->attributes->set('fff.csp_nonce', 'testNonce12345678');
$context = $app->make(AssetDeliveryContext::class);
deliveryCheck($context->nonce() === 'testNonce12345678', 'Nonce missing.');
request()->attributes->set('fff.csp_nonce', 'differentNonce1234');
deliveryCheck($context->nonce() === 'testNonce12345678', 'Request nonce was mutated after resolution.');
$app->forgetScopedInstances();
deliveryCheck($app->make(AssetDeliveryContext::class)->nonce() === 'differentNonce1234', 'Nonce leaked between scopes.');
request()->attributes->set('fff.csp_nonce', '<unsafe>');
$app->forgetScopedInstances();
deliveryReject(fn () => $app->make(AssetDeliveryContext::class)->nonce());
request()->attributes->set('fff.csp_nonce', 'testNonce12345678');
$app->forgetScopedInstances();
echo "PASS: CSP nonce resolves once per request and never leaks through worker/request reset.\n";

$app->make(RuntimeRenderContext::class)->enableStandalone();
foreach (['flex-fff-load-bootstrap', 'flex-field-asset-injector'] as $view) {
    $html = view('filament-flex-fields::partials.'.$view)->render();
    deliveryCheck(str_contains($html, 'nonce="testNonce12345678"') && str_contains($html, 'crossorigin="anonymous"')
        && str_contains($html, 'referrerpolicy="no-referrer"') && ($view !== 'flex-fff-load-bootstrap' || str_contains($html, 'data-fff-delivery-policy')), 'Startup tag lost delivery metadata.');
}
$href = FlexFieldAssets::stylesheetHref('flex-text-input');
$html = view('filament-flex-fields::partials.asset-delivery-attributes', ['deliveryUrl' => $href])->render();
deliveryCheck(str_contains($html, 'nonce="testNonce12345678"'), 'CSS tag lost nonce.');
$app->forgetScopedInstances();
$app['config']->set('app.asset_url', 'https://unlisted.example.test');
// Explicit application context rejects unlisted URLs before markup is returned.
deliveryReject(fn () => $app->make(AssetDeliveryContext::class)->attributes('https://unlisted.example.test/x.css'));
echo "PASS: real Blade startup/CSS metadata stays escaped and consistent; server blocks unlisted transport origins.\n";
echo "PASS: basic delivery policy PHP acceptance — 3 groups; manifest and CLI proofs follow; whole-stack strict CSP and transitive ESM integrity remain outside this proof.\n";

$app['config']->set('app.asset_url', null);
$app['config']->set('filament-flex-fields.assets.delivery', ['integrity_from_manifest' => true, 'importmap_integrity' => true, 'require_integrity' => true]);
$app->forgetScopedInstances();
$known = $app->make(AssetDeliveryContext::class)->policy()->integrity;
deliveryCheck(count($known) > 200, 'Sealed integrity catalog omitted build artifacts.');
deliveryCheck(isset($known[FlexFieldAssets::stylesheetHref('flex-text-input')]), 'Typed stylesheet digest is missing.');
deliveryCheck(isset($known[FlexFieldAssets::alpineEntrySrc('flex-core-runtime')]), 'Runtime digest is missing.');
$bootstrapSrc = FilamentAsset::getScriptSrc(FlexFieldAssets::FFF_LOAD_BOOTSTRAP_SCRIPT_ID, FilamentFlexFieldsPlugin::PACKAGE_NAME);
deliveryCheck(isset($known[$bootstrapSrc]), 'Startup script digest is missing.');
$app->make(RuntimeRenderContext::class)->enableStandalone();
$html = view('filament-flex-fields::partials.flex-fff-load-bootstrap')->render();
deliveryCheck(str_contains($html, 'integrity="'.$known[$bootstrapSrc].'"') && str_contains($html, 'rel="modulepreload"') && str_contains($html, 'type="importmap"'), 'Initial startup lost automatic integrity metadata.');
echo "PASS: automatic SRI derives more than 200 known artifact hashes from the sealed release without hashing files during render; startup/module hint hashes agree.\n";

$app['config']->set('filament-flex-fields.assets.delivery', ['crossorigin' => 'use-credentials']);
$app->forgetScopedInstances();
$exit = Artisan::call('fff:assets:doctor', ['--json' => true]);
$report = json_decode(Artisan::output(), true, 16, JSON_THROW_ON_ERROR);
deliveryCheck($exit === 1 && $report['errors'][0]['code'] === 'asset_delivery_invalid', 'Doctor accepted invalid delivery configuration.');
deliveryReject(fn () => $app['events']->dispatch(new CommandStarting('filament:assets',
    new ArrayInput([]), new NullOutput)));
echo "PASS: doctor rejects invalid policy with a redacted structured code; native publication rejects it before copying any files.\n";
